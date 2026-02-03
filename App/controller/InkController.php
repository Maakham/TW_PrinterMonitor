<?php
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../models/InkModel.php';

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ตรวจสอบ Action ว่าเป็นการ Add
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        
        $inkModel = new InkModel($conn);
        
        // รับค่าและ Clean Data เบื้องต้น
        $data = [
            'ink_code' => trim($_POST['ink_code']),
            'ink_name' => trim($_POST['ink_name']),
            'ink_color' => $_POST['ink_color'],
            'ink_qty' => (int)$_POST['ink_qty'],
            'ink_min_alert' => (int)$_POST['ink_min_alert']
        ];

        // เรียกใช้ Model เพื่อบันทึก
        if ($inkModel->insertInk($data)) {
            $_SESSION['flash_message'] = ['type' => 'success', 'msg' => 'เพิ่มรายการหมึกสำเร็จ'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'];
        }
    }

    // Redirect กลับไปหน้าเดิม (แก้ path ตามจริงของคุณ)
    header("Location: ../index.php?page=asset_stock"); 
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'cut_stock') {
        
        $inkModel = new InkModel($conn);
        
        $pid = (int)$_POST['tw_pid'];
        $ink_id = (int)$_POST['ink_id'];
        $qty = (int)$_POST['usage_qty'];

        $result = $inkModel->cutStock($pid, $ink_id, $qty);

        if ($result === true) {
            $_SESSION['flash_message'] = ['type' => 'success', 'msg' => 'เบิกหมึกสำเร็จ บันทึกเรียบร้อย'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'msg' => $result]; // ส่งข้อความ Error กลับไป
        }

        // Redirect กลับไปหน้า Monitor
        header("Location: ../index.php?page=printer_monitor"); 
        exit;
    }
?>