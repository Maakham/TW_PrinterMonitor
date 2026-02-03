<?php
require_once __DIR__ . '/../../config/connect.php';

// 1. PHP LOGIC: HANDLE FORM SUBMIT
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- ADD / EDIT ---
        if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
            $code = trim($_POST['ink_code']);
            $name = trim($_POST['ink_name']);
            $color = $_POST['ink_color'];
            $qty = (int)$_POST['ink_qty'];
            $min = (int)$_POST['ink_min_alert'];
            
            if ($_POST['action'] == 'add') {
                $sql = "INSERT INTO tw_ink_stock (ink_code, ink_name, ink_color, ink_qty, ink_min_alert) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$code, $name, $color, $qty, $min]);
                $msg = '<div class="alert-success">เพิ่มข้อมูลสำเร็จ</div>';
            } else {
                $id = (int)$_POST['ink_id'];
                $sql = "UPDATE tw_ink_stock SET ink_code=?, ink_name=?, ink_color=?, ink_qty=?, ink_min_alert=? WHERE ink_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$code, $name, $color, $qty, $min, $id]);
                $msg = '<div class="alert-success">แก้ไขข้อมูลสำเร็จ</div>';
            }
        }
        // --- DELETE ---
        if (isset($_POST['action']) && $_POST['action'] == 'delete') {
            $id = (int)$_POST['ink_id'];
            $stmt = $conn->prepare("DELETE FROM tw_ink_stock WHERE ink_id = ?");
            $stmt->execute([$id]);
            $msg = '<div class="alert-success">ลบข้อมูลสำเร็จ</div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert-error">Error: ' . $e->getMessage() . '</div>';
    }
}

// 2. FETCH DATA
$stmt = $conn->query("SELECT * FROM tw_ink_stock ORDER BY ink_code ASC");
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// คำนวณ Summary
$total_items = count($stocks);
$low_stock_count = 0;
foreach($stocks as $s) {
    if($s['ink_qty'] <= $s['ink_min_alert']) $low_stock_count++;
}

// Helper: สี Badge
function getInkColorClass($color) {
    switch(strtolower($color)) {
        case 'cyan': return 'bg-cyan-100 text-cyan-700 border-cyan-200';
        case 'magenta': return 'bg-pink-100 text-pink-700 border-pink-200';
        case 'yellow': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'black': return 'bg-gray-100 text-gray-700 border-gray-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}
function getInkDot($color) {
    switch(strtolower($color)) {
        case 'cyan': return 'bg-cyan-500';
        case 'magenta': return 'bg-pink-500';
        case 'yellow': return 'bg-yellow-400';
        case 'black': return 'bg-gray-900';
        default: return 'bg-slate-400';
    }
}
?>
<?php
// เรียกใช้ Model
require_once __DIR__ . '/../../models/InkModel.php';
$inkModel = new InkModel($conn);
$stocks = $inkModel->getAllInks(); // ดึงข้อมูลมาแสดง

// เช็ค Session Flash Message (สำหรับแจ้งเตือนหลังบันทึก)
if (session_status() === PHP_SESSION_NONE) session_start();
?>

<?php if (isset($_SESSION['flash_message'])): ?>
<div
    class="mb-4 rounded-md p-4 <?php echo $_SESSION['flash_message']['type'] == 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
    <div class="flex">
        <div class="flex-shrink-0">
            <i
                class="fa-solid <?php echo $_SESSION['flash_message']['type'] == 'success' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm font-medium"><?php echo $_SESSION['flash_message']['msg']; ?></p>
        </div>
    </div>
</div>
<?php unset($_SESSION['flash_message']); // ลบข้อความออกหลังแสดงแล้ว ?>
<?php endif; ?>
<style>
.alert-success {
    @apply bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4;
}

.alert-error {
    @apply bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4;
}
</style>

<div class="container mx-auto px-4 py-6 font-sans text-slate-800">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h4 class="text-2xl font-bold text-slate-700"><i class="fa-solid fa-boxes-stacked mr-2"></i> สต๊อกหมึกพิมพ์
                (Asset)</h4>
            <p class="text-sm text-slate-500 mt-1">จัดการรายการรับ-จ่ายหมึก และตรวจสอบจำนวนคงเหลือ</p>
        </div>

        <div class="flex gap-3">
            <div class="bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm flex items-center gap-3">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-full"><i class="fa-solid fa-list"></i></div>
                <div>
                    <span class="block text-xs text-slate-500">ทั้งหมด</span>
                    <span class="font-bold text-lg"><?php echo $total_items; ?></span>
                </div>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm flex items-center gap-3">
                <div class="p-2 bg-red-50 text-red-600 rounded-full"><i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <span class="block text-xs text-slate-500">ใกล้หมด</span>
                    <span
                        class="font-bold text-lg <?php echo $low_stock_count > 0 ? 'text-red-600':'text-slate-700'; ?>"><?php echo $low_stock_count; ?></span>
                </div>
            </div>
            <button onclick="document.getElementById('addInkModal').classList.remove('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm flex items-center gap-2 transition-colors">
                <i class="fa-solid fa-plus"></i> เพิ่มหมึกใหม่
            </button>
        </div>
    </div>

    <?php if($msg) echo $msg; ?>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-600">
                <thead class="text-xs text-slate-700 uppercase bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 font-semibold">รหัส (Code)</th>
                        <th class="px-6 py-4 font-semibold">ชื่อรายการ</th>
                        <th class="px-6 py-4 font-semibold">สี (Color)</th>
                        <th class="px-6 py-4 font-semibold text-center">คงเหลือ (Qty)</th>
                        <th class="px-6 py-4 font-semibold text-center">สถานะ</th>
                        <th class="px-6 py-4 font-semibold text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(empty($stocks)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">ไม่พบข้อมูลหมึกในระบบ</td>
                    </tr>
                    <?php else: foreach($stocks as $row): 
                        $is_low = $row['ink_qty'] <= $row['ink_min_alert'];
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-900">
                            <?php echo htmlspecialchars($row['ink_code']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php echo htmlspecialchars($row['ink_name']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                class="px-2.5 py-1 rounded-full text-xs font-medium border flex items-center w-fit gap-2 <?php echo getInkColorClass($row['ink_color']); ?>">
                                <span class="w-2 h-2 rounded-full <?php echo getInkDot($row['ink_color']); ?>"></span>
                                <?php echo ucfirst($row['ink_color']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                class="font-bold text-base <?php echo $is_low ? 'text-red-600' : 'text-slate-700'; ?>">
                                <?php echo number_format($row['ink_qty']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if($is_low): ?>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-600 border border-red-200">LOW
                                STOCK</span>
                            <?php else: ?>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-600 border border-green-200">NORMAL</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick='openModal("edit", <?php echo json_encode($row); ?>)'
                                    class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button
                                    onclick='confirmDelete(<?php echo $row["ink_id"]; ?>, "<?php echo $row["ink_code"]; ?>")'
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addInkModal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"
        onclick="document.getElementById('addInkModal').classList.add('hidden')"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">

            <div
                class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">

                <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-slate-900" id="modal-title">
                        <i class="fa-solid fa-fill-drip text-blue-500 mr-2"></i> เพิ่มรายการหมึกใหม่
                    </h3>
                    <button type="button" onclick="document.getElementById('addInkModal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-500">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form action="controller/InkController.php" method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="ink_code" class="block text-sm font-medium text-slate-700 mb-1">รหัสสินค้า /
                                    SKU</label>
                                <input type="text" name="ink_code" id="ink_code" required
                                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2"
                                    placeholder="เช่น T6641">
                            </div>
                            <div>
                                <label for="ink_color"
                                    class="block text-sm font-medium text-slate-700 mb-1">สีหมึก</label>
                                <select name="ink_color" id="ink_color"
                                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
                                    <option value="Black">⚫ Black (สีดำ)</option>
                                    <option value="Cyan">🔵 Cyan (สีฟ้า)</option>
                                    <option value="Magenta">🔴 Magenta (สีแดงม่วง)</option>
                                    <option value="Yellow">🟡 Yellow (สีเหลือง)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="ink_name" class="block text-sm font-medium text-slate-700 mb-1">ชื่อรุ่น /
                                รายละเอียด</label>
                            <input type="text" name="ink_name" id="ink_name" required
                                class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2"
                                placeholder="เช่น Epson 003 Black Ink Bottle">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="ink_qty"
                                    class="block text-sm font-medium text-slate-700 mb-1">จำนวนตั้งต้น</label>
                                <input type="number" name="ink_qty" id="ink_qty" min="0" value="0" required
                                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
                            </div>
                            <div>
                                <label for="ink_min_alert"
                                    class="block text-sm font-medium text-slate-700 mb-1">จุดเตือนขั้นต่ำ</label>
                                <input type="number" name="ink_min_alert" id="ink_min_alert" min="0" value="5" required
                                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm border p-2">
                                <p class="text-xs text-slate-400 mt-1">*แจ้งเตือนเมื่อเหลือน้อยกว่านี้</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                        <button type="submit"
                            class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto transition-colors">
                            <i class="fa-solid fa-save mr-2"></i> บันทึกข้อมูล
                        </button>
                        <button type="button" onclick="document.getElementById('addInkModal').classList.add('hidden')"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">
                            ยกเลิก
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" action="" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="ink_id" id="deleteInkId">
</form>

<script>
const modal = document.getElementById('inkModal');

function openModal(mode, data = null) {
    modal.classList.remove('hidden');

    if (mode === 'edit' && data) {
        document.getElementById('modalTitle').innerText = 'แก้ไขรายการหมึก';
        document.getElementById('modalAction').value = 'edit';
        document.getElementById('modalInkId').value = data.ink_id;

        document.getElementById('inpCode').value = data.ink_code;
        document.getElementById('inpName').value = data.ink_name;
        document.getElementById('inpColor').value = data.ink_color; // ต้องตรงกับ value ใน option (Case Sensitive)
        document.getElementById('inpQty').value = data.ink_qty;
        document.getElementById('inpMin').value = data.ink_min_alert;
    } else {
        document.getElementById('modalTitle').innerText = 'เพิ่มรายการหมึก';
        document.getElementById('modalAction').value = 'add';
        document.getElementById('modalInkId').value = '';

        // Reset Form
        document.getElementById('inpCode').value = '';
        document.getElementById('inpName').value = '';
        document.getElementById('inpColor').value = 'Black';
        document.getElementById('inpQty').value = '0';
        document.getElementById('inpMin').value = '5';
    }
}

function closeModal() {
    modal.classList.add('hidden');
}

function confirmDelete(id, code) {
    if (confirm(`ยืนยันการลบรายการหมึกรหัส "${code}" หรือไม่?`)) {
        document.getElementById('deleteInkId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>