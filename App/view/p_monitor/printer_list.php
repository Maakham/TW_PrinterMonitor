<?php
require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../models/InkModel.php';
$inkModel = new InkModel($conn);
$allInks = $inkModel->getAllInks(); // หรือจะเขียน query ใหม่เพื่อ filter qty > 0 ก็ได้
// 1. HELPER FUNCTIONS & CONFIG (ย้ายขึ้นบนสุด)

function getTailwindInkColor($name) {
    if (stripos($name, 'black') !== false || stripos($name, 'k') !== false) return 'bg-gray-900';
    if (stripos($name, 'cyan') !== false || stripos($name, 'c') !== false) return 'bg-cyan-500';
    if (stripos($name, 'magenta') !== false || stripos($name, 'm') !== false) return 'bg-pink-600';
    if (stripos($name, 'yellow') !== false || stripos($name, 'y') !== false) return 'bg-yellow-400';
    return 'bg-slate-400';
}

function clean_snmp_value($value) {
    $value = preg_replace('/^[a-zA-Z0-9]+:\s?/', '', trim($value));
    return str_replace('"', '', trim($value));
}

function format_uptime($timeticks) {
    $timeticks = clean_snmp_value($timeticks);
    if (preg_match('/\((.*?)\)/', $timeticks, $match)) {
        $seconds = (int)$match[1] / 100;
    } elseif (is_numeric($timeticks)) {
        $seconds = $timeticks / 100;
    } else {
        return $timeticks;
    }
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    return $dtF->diff($dtT)->format('%a วัน, %h ชม., %i น.');
}

function FetchPrintDetail($conn) {
    $sql = "SELECT tw_pid, tw_pipaddress, tw_pname FROM tw_printer WHERE tw_pstatus = 'A'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// เตรียมข้อมูลพื้นฐาน (สำหรับใช้ตรวจสอบ IP)
$Pip_data = FetchPrintDetail($conn); 
$printer_map = []; 
$printer_list = [];
foreach ($Pip_data as $row){
    $printer_list[] = $row['tw_pipaddress'];
    $printer_map[$row['tw_pipaddress']] = $row['tw_pid'];
}

// 2. AJAX HANDLE SECTION (ส่วนที่คืนค่าให้ Modal)
if (isset($_GET['ajax_ip']) && in_array($_GET['ajax_ip'], $printer_list)) {
    $ip = $_GET['ajax_ip'];
    $printer_id = $printer_map[$ip];
    
    $community = 'public';
    $timeout = 200000; // Timeout 200ms

    // OIDs
    $oid_sysDescr = ".1.3.6.1.2.1.1.1.0"; 
    $oid_sysUpTime = ".1.3.6.1.2.1.1.3.0";
    $oid_pageCountRoot = ".1.3.6.1.2.1.43.10.2.1.4"; 
    $oid_marker_desc = ".1.3.6.1.2.1.43.11.1.1.6";
    $oid_marker_max  = ".1.3.6.1.2.1.43.11.1.1.8"; 
    $oid_marker_lvl  = ".1.3.6.1.2.1.43.11.1.1.9"; 

    try {
        $sysDescr = @snmp2_get($ip, $community, $oid_sysDescr, $timeout);

        if ($sysDescr) {
            $model = clean_snmp_value($sysDescr);
            $upTime = format_uptime(@snmp2_get($ip, $community, $oid_sysUpTime, $timeout));

            // Page Count
            $pageWalk = @snmp2_real_walk($ip, $community, $oid_pageCountRoot, $timeout);
            $pageCount = 0;
            if ($pageWalk) {
                foreach ($pageWalk as $val) {
                    $cleanVal = (int)clean_snmp_value($val);
                    if ($cleanVal > $pageCount) $pageCount = $cleanVal;
                }
            }
            $pageCountDisplay = number_format($pageCount);

            // Ink Data
            $inkNames = @snmp2_real_walk($ip, $community, $oid_marker_desc, $timeout);
            $inkMax   = @snmp2_real_walk($ip, $community, $oid_marker_max, $timeout);
            $inkLevel = @snmp2_real_walk($ip, $community, $oid_marker_lvl, $timeout);

            $log_ink_data = [];
            
            if ($inkNames) {
                $inkNamesArr = array_values($inkNames);
                $inkMaxArr = array_values($inkMax);
                $inkLevelArr = array_values($inkLevel);

                for ($i = 0; $i < count($inkNamesArr); $i++) {
                    $n = clean_snmp_value($inkNamesArr[$i]);
                    $m = (int)clean_snmp_value($inkMaxArr[$i]);
                    $c = (int)clean_snmp_value($inkLevelArr[$i]);
                    $p = ($m > 0) ? round(($c / $m) * 100) : 0;
                    if ($c < 0) $p = 0;

                    $log_ink_data[] = [
                        'name' => $n,
                        'percent' => $p
                    ];
                }
            }

            // --- Database Logging ---
            try {
                $checkSql = "SELECT tw_recorded_at FROM tw_snmp_log WHERE tw_pid = :pid ORDER BY tw_log_id DESC LIMIT 1";
                $chkStmt = $conn->prepare($checkSql);
                $chkStmt->execute([':pid' => $printer_id]);
                $lastLog = $chkStmt->fetch(PDO::FETCH_ASSOC);
                
                $shouldLog = true;
                if($lastLog) {
                    $lastTime = strtotime($lastLog['tw_recorded_at']);
                    if(time() - $lastTime < 300) { 
                        $shouldLog = false;
                    }
                }

                if($shouldLog) {
                    $sqlLog = "INSERT INTO tw_snmp_log (tw_pid, tw_total_page, tw_ink_status_json) VALUES (:pid, :page, :ink_json)";
                    $stmtLog = $conn->prepare($sqlLog);
                    $stmtLog->execute([
                        ':pid' => $printer_id,
                        ':page' => $pageCount,
                        ':ink_json' => json_encode($log_ink_data)
                    ]);
                }
            } catch (Exception $dbEx) {
                error_log("DB Log Error: " . $dbEx->getMessage());
            }

            // --- แสดงผล HTML Modal Content ---
    ?>
<div class="grid grid-cols-1 md:grid-cols-12 gap-6 font-sans text-left">
    <div class="md:col-span-5">
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-5 shadow-sm h-full">
            <h6 class="text-slate-600 font-semibold border-b border-slate-200 pb-3 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-server text-slate-400"></i> รายละเอียดอุปกรณ์
            </h6>
            <div class="space-y-3 text-sm text-slate-700">
                <div>
                    <span class="block font-semibold text-slate-900">รุ่น (Model):</span>
                    <span class="block text-slate-500 break-words"><?php echo $model; ?></span>
                </div>
                <div>
                    <span class="block font-semibold text-slate-900">IP Address:</span>
                    <span class="font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded"><?php echo $ip; ?></span>
                </div>
                <div>
                    <span class="block font-semibold text-slate-900">ระยะเวลาทำงาน (Uptime):</span>
                    <span class="text-slate-600"><?php echo $upTime; ?></span>
                </div>
            </div>
            <div class="my-6 border-t border-slate-200"></div>
            <div class="text-center">
                <small class="text-slate-400 uppercase tracking-wide text-xs font-semibold">จำนวนการพิมพ์ทั้งหมด</small>
                <h2 class="text-4xl font-bold text-blue-700 mt-1"><?php echo $pageCountDisplay; ?></h2>
                <span class="text-xs text-slate-400">แผ่น</span>
            </div>
        </div>
    </div>

    <div class="md:col-span-7">
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm h-full">
            <h6 class="text-slate-600 font-semibold border-b border-slate-200 pb-3 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-droplet text-slate-400"></i> ปริมาณน้ำหมึกคงเหลือ
            </h6>
            <div class="space-y-4">
                <?php
                                if (!empty($log_ink_data)) {
                                    foreach ($log_ink_data as $ink) {
                                        $name = $ink['name'];
                                        $percent = $ink['percent'];
                                        $tailwindClass = getTailwindInkColor($name);
                                    ?>
                <div>
                    <div class="flex justify-between mb-1.5 text-xs font-medium text-slate-600">
                        <span class="truncate pr-2 w-3/4"><?php echo $name; ?></span>
                        <span class="text-slate-900"><?php echo $percent; ?>%</span>
                    </div>
                    <div
                        class="w-full bg-slate-100 rounded-full h-4 shadow-inner border border-slate-100 overflow-hidden">
                        <div class="<?php echo $tailwindClass; ?> h-4 rounded-full transition-all duration-500 ease-out flex items-center justify-center text-[10px] text-white/90 shadow-sm"
                            style="width: <?php echo $percent; ?>%">
                            <?php echo ($percent > 15) ? $percent.'%' : ''; ?>
                        </div>
                    </div>
                </div>
                <?php
                                    }
                                } else {
                                    echo '<div class="p-4 bg-slate-50 text-slate-500 text-sm rounded border border-slate-100 text-center">ไม่พบข้อมูลหมึก</div>';
                                }
                            ?>
            </div>
        </div>
    </div>
</div>
<?php
        } else {
            echo '<div class="p-6 text-center text-red-600 bg-red-50 rounded-lg border border-red-200"><i class="fa-solid fa-circle-exclamation text-2xl mb-2 block"></i>Connection Timeout: ไม่สามารถเชื่อมต่อกับอุปกรณ์ได้</div>';
        }
    } catch (Exception $e) {
        echo '<div class="p-6 text-center text-red-600 bg-red-50 rounded-lg border border-red-200">Error: '.$e->getMessage().'</div>';
    }
    // สำคัญ: หยุดการทำงานทันทีหลังจากส่ง HTML ของ Modal กลับไป
    exit;
}
?>

<?php 
    // require_once __DIR__ . '/view/assets/header.php'; 
    require_once __DIR__ . '/interface_syc.php';
?>

<div class="container mx-auto px-4 mt-6">
    <form action="" method="post" class="flex justify-end">
        <button type="submit" name="interface_syc"
            class="text-xs text-slate-400 hover:text-blue-600 transition-colors underline decoration-dotted">
            Interface Sync
        </button>
    </form>
</div>

<div class="container mx-auto px-4 py-8 font-sans text-slate-800">

    <div class="flex items-center justify-between mb-8 border-b border-slate-200 pb-4">
        <div>
            <h4 class="text-2xl font-bold text-slate-700">ภาพรวมอุปกรณ์</h4>
            <p class="text-sm text-slate-500 mt-1">มอนิเตอร์สถานะเครื่องพิมพ์ทั้งหมด <?php echo count($printer_list); ?>
                เครื่อง</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php 
            $oid_marker_desc = ".1.3.6.1.2.1.43.11.1.1.6";
            $oid_marker_max  = ".1.3.6.1.2.1.43.11.1.1.8"; 
            $oid_marker_lvl  = ".1.3.6.1.2.1.43.11.1.1.9"; 

            foreach ($printer_list as $ip): 
                $is_online = false;
                $model_short = "Offline";
                $sysDescr = @snmp2_get($ip, 'public', ".1.3.6.1.2.1.1.1.0", 100000);
                
                $inkNames = false;
                $inkMax = [];
                $inkLevel = [];

                if ($sysDescr) {
                    $is_online = true;
                    $model_short = clean_snmp_value($sysDescr);
                    if (strlen($model_short) > 25) $model_short = substr($model_short, 0, 25) . "...";

                    $inkNames = @snmp2_real_walk($ip, 'public', $oid_marker_desc, 100000);
                    $inkMax   = @snmp2_real_walk($ip, 'public', $oid_marker_max, 100000);
                    $inkLevel = @snmp2_real_walk($ip, 'public', $oid_marker_lvl, 100000);
                }
            ?>

        <div class="group bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 flex flex-col h-full cursor-pointer relative overflow-hidden"
            onclick="openPrinterModal('<?php echo $ip; ?>')">

            <div class="absolute top-4 right-4 z-10">
                <span class="flex h-3 w-3">
                    <?php if($is_online): ?>
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    <?php else: ?>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-slate-300"></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="p-6 flex-grow flex flex-col">
                <div class="text-center mb-4">
                    <div class="mb-3 transition-transform duration-300 group-hover:scale-110">
                        <i
                            class="fa-solid fa-print text-4xl <?php echo $is_online ? 'text-blue-600' : 'text-slate-300'; ?>"></i>
                    </div>
                    <h6 class="font-bold text-slate-800 text-sm md:text-base truncate leading-tight"
                        title="<?php echo $model_short; ?>">
                        <?php echo $model_short; ?>
                    </h6>
                    <p
                        class="text-xs font-mono text-slate-500 mt-1 bg-slate-50 inline-block px-2 py-0.5 rounded border border-slate-100">
                        <?php echo $ip; ?>
                    </p>
                </div>

                <div class="mt-2 space-y-2 flex-grow">
                    <?php if ($is_online && $inkNames): ?>
                    <div class="space-y-1.5">
                        <?php
                            $inkNamesVal = array_values($inkNames);
                            $inkMaxVal = array_values($inkMax);
                            $inkLevelVal = array_values($inkLevel);

                            for ($i = 0; $i < count($inkNamesVal); $i++) {
                                $name = clean_snmp_value($inkNamesVal[$i]);
                                $max = (int)clean_snmp_value($inkMaxVal[$i]);
                                $current = (int)clean_snmp_value($inkLevelVal[$i]);
                                
                                $percent = ($max > 0) ? round(($current / $max) * 100) : 0;
                                if ($current < 0) $percent = 0;

                                $tailwindClass = getTailwindInkColor($name);
                                
                                $shortName = str_replace(['Black', 'Cyan', 'Magenta', 'Yellow'], ['BK', 'C', 'M', 'Y'], $name);
                                if(strlen($shortName) > 15) $shortName = substr($shortName, 0, 15);
                        ?>
                        <div>
                            <div class="flex justify-between text-[10px] text-slate-500 mb-0.5">
                                <span><?php echo $shortName; ?></span>
                                <span class="font-semibold"><?php echo $percent; ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="<?php echo $tailwindClass; ?> h-1.5 rounded-full"
                                    style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php elseif ($is_online): ?>
                    <div class="flex flex-col items-center justify-center h-20 text-slate-400 text-xs gap-2">
                        <i class="fa-solid fa-spinner fa-spin text-lg"></i>
                        <span>กำลังโหลดข้อมูลหมึก...</span>
                    </div>
                    <?php else: ?>
                    <div
                        class="flex items-center justify-center h-20 bg-slate-50 rounded border border-slate-100 border-dashed text-slate-400 text-xs">
                        ไม่สามารถเชื่อมต่อได้
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                <?php if($is_online): ?>
                <button
                    class="w-full py-2 px-4 rounded-md border border-blue-600 text-blue-600 text-xs font-semibold hover:bg-blue-600 hover:text-white transition-colors duration-200">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i> ดูรายละเอียด
                </button>
                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex gap-2">
    <button onclick="openPrinterModal('<?php echo $ip; ?>')" 
            class="flex-1 py-2 px-2 rounded-md border border-blue-600 text-blue-600 text-xs font-semibold hover:bg-blue-600 hover:text-white transition-colors duration-200">
        <i class="fa-solid fa-magnifying-glass"></i> ดู
    </button>
    
    <button onclick="openCutStockModal(<?php echo $printer_map[$ip]; ?>, '<?php echo $model_short; ?>')" 
            class="flex-1 py-2 px-2 rounded-md bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition-colors duration-200 shadow-sm">
        <i class="fa-solid fa-scissors"></i> เติม/ตัดหมึก
    </button>
</div>
                <?php else: ?>
                <button
                    class="w-full py-2 px-4 rounded-md bg-slate-100 text-slate-400 text-xs font-medium cursor-not-allowed"
                    disabled>
                    ไม่ตอบสนอง
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../view/assets/footer.php'; ?>


<div id="printerModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity backdrop-blur-sm"
        onclick="closePrinterModal()"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl border border-slate-200">

                <div class="absolute right-0 top-0 pr-4 pt-4 z-20">
                    <button type="button" onclick="closePrinterModal()"
                        class="rounded-md bg-white text-slate-400 hover:text-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <span class="sr-only">Close</span>
                        <i class="fa-solid fa-xmark text-2xl"></i>
                    </button>
                </div>

                <div id="modalContent"
                    class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 min-h-[300px] flex items-center justify-center">
                    <div class="text-center">
                        <i class="fa-solid fa-circle-notch fa-spin text-blue-500 text-3xl mb-3"></i>
                        <p class="text-slate-500">กำลังโหลดข้อมูล...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
function openPrinterModal(ip) {
    // 1. แสดง Modal
    const modal = document.getElementById('printerModal');
    modal.classList.remove('hidden');

    // 2. ตั้งค่าสถานะ Loading
    const contentDiv = document.getElementById('modalContent');
    contentDiv.innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 w-full">
                <i class="fa-solid fa-circle-notch fa-spin text-blue-600 text-4xl mb-4"></i>
                <p class="text-slate-500 font-medium">กำลังเชื่อมต่อกับ ${ip}...</p>
                <p class="text-slate-400 text-sm mt-1">กรุณารอสักครู่...</p>
            </div>
        `;

    // 3. เรียก AJAX
    // *** สำคัญ: ต้องมี ajax=1 เพื่อให้ index.php ตัด Navbar ออก ***
    fetch(`?page=printer_monitor&ajax=1&ajax_ip=${ip}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = `
                    <div class="text-center p-6 bg-red-50 rounded-lg w-full">
                        <i class="fa-solid fa-triangle-exclamation text-red-500 text-4xl mb-3"></i>
                        <h3 class="text-red-700 font-bold mb-1">เกิดข้อผิดพลาด</h3>
                        <p class="text-red-600 text-sm">ไม่สามารถดึงข้อมูลได้</p>
                    </div>
                `;
        });
}

function closePrinterModal() {
    document.getElementById('printerModal').classList.add('hidden');
}

document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closePrinterModal();
    }
});
</script>

<div id="cutStockModal" class="fixed inset-0 z-[70] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" onclick="closeCutStockModal()"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            
            <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-md border border-slate-200">
                
                <div class="bg-slate-800 px-4 py-3 border-b border-slate-700 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-white">
                        <i class="fa-solid fa-fill-drip mr-2 text-yellow-400"></i> ตัดสต๊อกหมึก
                    </h3>
                    <button type="button" onclick="closeCutStockModal()" class="text-slate-400 hover:text-white">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form action="controller/InkController.php" method="POST" class="p-6">
                    <input type="hidden" name="action" value="cut_stock">
                    <input type="hidden" name="tw_pid" id="cut_pid">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-500 mb-1">เครื่องปริ้นท์</label>
                        <input type="text" id="cut_pname" readonly class="w-full bg-slate-100 border-transparent rounded-md text-slate-700 font-bold focus:ring-0">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">เลือกหมึกที่ใช้</label>
                        <select name="ink_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-2.5">
                            <option value="" disabled selected>-- เลือกรายการหมึก --</option>
                            <?php foreach($allInks as $ink): ?>
                                <option value="<?php echo $ink['ink_id']; ?>" <?php echo ($ink['ink_qty'] <= 0) ? 'disabled' : ''; ?>>
                                    <?php echo $ink['ink_code']; ?> - <?php echo $ink['ink_name']; ?> 
                                    (<?php echo $ink['ink_color']; ?>) 
                                    [คงเหลือ: <?php echo $ink['ink_qty']; ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">จำนวน (ขวด/ตลับ)</label>
                        <div class="flex items-center">
                            <button type="button" onclick="adjQty(-1)" class="w-10 h-10 rounded-l-md bg-slate-100 border border-slate-300 hover:bg-slate-200">-</button>
                            <input type="number" name="usage_qty" id="usage_qty" value="1" min="1" required class="w-full text-center border-y border-slate-300 h-10 focus:ring-0 z-10">
                            <button type="button" onclick="adjQty(1)" class="w-10 h-10 rounded-r-md bg-slate-100 border border-slate-300 hover:bg-slate-200">+</button>
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-md bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors">
                        <i class="fa-solid fa-save mr-1"></i> ยืนยันการตัดสต๊อก
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Script สำหรับ Modal ตัดหมึก ---
    function openCutStockModal(pid, pname) {
        document.getElementById('cut_pid').value = pid;
        document.getElementById('cut_pname').value = pname;
        document.getElementById('cutStockModal').classList.remove('hidden');
    }

    function closeCutStockModal() {
        document.getElementById('cutStockModal').classList.add('hidden');
    }

    function adjQty(amount) {
        let input = document.getElementById('usage_qty');
        let current = parseInt(input.value) || 0;
        let newVal = current + amount;
        if(newVal < 1) newVal = 1;
        input.value = newVal;
    }
</script>