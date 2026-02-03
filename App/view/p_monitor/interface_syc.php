<?php
require_once __DIR__ . '/../../config/connect.php';

// ตั้งค่าให้ Script รันได้นานๆ
set_time_limit(300);
ini_set('memory_limit', '256M');

// เริ่มกระบวนการ
error_log("Start Printer Sync: " . date("Y-m-d H:i:s"));

// 1. ดึงรายการ Printer ที่ Active ทั้งหมด
try {
    $sql = "SELECT tw_pid, tw_pipaddress FROM tw_printer WHERE tw_pstatus = 'A'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die();
}

$total = count($printers);
$success = 0;
$failed = 0;

// 2. ตั้งค่า OID
$community = 'public';
$timeout = 100000;

$oid_pageCountRoot = ".1.3.6.1.2.1.43.10.2.1.4"; 
$oid_marker_desc = ".1.3.6.1.2.1.43.11.1.1.6";
$oid_marker_max  = ".1.3.6.1.2.1.43.11.1.1.8"; 
$oid_marker_lvl  = ".1.3.6.1.2.1.43.11.1.1.9";


// 3. วนลูปเก็บข้อมูลทีละเครื่อง
foreach ($printers as $printer) {
    $ip = $printer['tw_pipaddress'];
    $pid = $printer['tw_pid'];
    
    error_log("Processing IP: $ip");

    try {
        // เช็คก่อนว่า Ping เจอไหม
        $sysCheck = @snmp2_get($ip, $community, ".1.3.6.1.2.1.1.1.0", $timeout);

        if ($sysCheck) {
            // --- A. ดึง Page Count ---
            $pageWalk = @snmp2_real_walk($ip, $community, $oid_pageCountRoot, $timeout);
            $pageCount = 0;
            if ($pageWalk) {
                foreach ($pageWalk as $val) {
                    $cleanVal = (int)clean_snmp_value($val);
                    if ($cleanVal > $pageCount) $pageCount = $cleanVal;
                }
            }

            // --- B. ดึงหมึก ---
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
                    $m = isset($inkMaxArr[$i]) ? (int)clean_snmp_value($inkMaxArr[$i]) : 0;
                    $c = isset($inkLevelArr[$i]) ? (int)clean_snmp_value($inkLevelArr[$i]) : 0;
                    
                    $p = ($m > 0) ? round(($c / $m) * 100) : 0;
                    if ($c < 0) $p = 0;

                    $log_ink_data[] = [
                        'name' => $n,
                        'percent' => $p
                    ];
                }
            }

            // --- C. บันทึกลง Database ---
            $sqlLog = "INSERT INTO tw_snmp_log (tw_pid, tw_total_page, tw_ink_status_json, tw_recorded_at) 
                    VALUES (:pid, :page, :ink_json, NOW())";
            $stmtLog = $conn->prepare($sqlLog);
            $stmtLog->execute([
                ':pid' => $pid,
                ':page' => $pageCount,
                ':ink_json' => json_encode($log_ink_data)
            ]);

            $success++;
            error_log("  [OK] Page: $pageCount");
            
            // อัพเดทเวลาล่าสุดในตารางเครื่องพิมพ์
            $sqlUpdate = "UPDATE tw_printer SET tw_last_sync = NOW() WHERE tw_pid = :pid";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([':pid' => $pid]);

        } else {
            error_log("  [Offline]");
            $failed++;
            
            // บันทึกสถานะออฟไลน์
            $sqlOffline = "INSERT INTO tw_printer_status (tw_pid, tw_status, tw_checked_at) 
                          VALUES (:pid, 'offline', NOW())";
            $stmtOffline = $conn->prepare($sqlOffline);
            $stmtOffline->execute([':pid' => $pid]);
        }

    } catch (Exception $e) {
        error_log("  [Error] " . $e->getMessage());
        $failed++;
    }
}

// บันทึกผลลัพธ์การ sync
$log_message = "Sync Complete: Total=$total, Success=$success, Failed=$failed";
error_log($log_message);

// บันทึกลงตาราง sync_log (ถ้ามี)
try {
    $sqlSyncLog = "INSERT INTO tw_sync_log (tw_total_printers, tw_success, tw_failed, tw_sync_time) 
                   VALUES (:total, :success, :failed, NOW())";
    $stmtSyncLog = $conn->prepare($sqlSyncLog);
    $stmtSyncLog->execute([
        ':total' => $total,
        ':success' => $success,
        ':failed' => $failed
    ]);
} catch (Exception $e) {
    // ถ้าไม่มีตาราง ก็ข้ามไป
}

echo $log_message;
?>