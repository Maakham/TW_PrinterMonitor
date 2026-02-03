<?php
class InkModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ฟังก์ชันเพิ่มข้อมูลหมึกใหม่
    public function insertInk($data) {
        try {
            $sql = "INSERT INTO tw_ink_stock (ink_code, ink_name, ink_color, ink_qty, ink_min_alert) 
                    VALUES (:code, :name, :color, :qty, :min)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':code' => $data['ink_code'],
                ':name' => $data['ink_name'],
                ':color' => $data['ink_color'],
                ':qty' => $data['ink_qty'],
                ':min' => $data['ink_min_alert']
            ]);
            return true;
        } catch (PDOException $e) {
            // บันทึก Error log ถ้าจำเป็น
            error_log($e->getMessage());
            return false;
        }
    }

    // ฟังก์ชันดึงข้อมูลทั้งหมด
    public function getAllInks() {
        $sql = "SELECT * FROM tw_ink_stock ORDER BY ink_id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ฟังก์ชันตัดสต๊อกหมึก
    public function cutStock($pid, $ink_id, $qty) {
        try {
            $this->conn->beginTransaction();

            // 1. เช็คของก่อนว่าพอไหม
            $stmtCheck = $this->conn->prepare("SELECT ink_qty FROM tw_ink_stock WHERE ink_id = :ink_id");
            $stmtCheck->execute([':ink_id' => $ink_id]);
            $stock = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$stock || $stock['ink_qty'] < $qty) {
                $this->conn->rollBack();
                return "Stock ไม่เพียงพอ (เหลือ " . ($stock['ink_qty'] ?? 0) . ")";
            }

            // 2. ตัดยอดคงเหลือ
            $stmtUpdate = $this->conn->prepare("UPDATE tw_ink_stock SET ink_qty = ink_qty - :qty WHERE ink_id = :ink_id");
            $stmtUpdate->execute([':qty' => $qty, ':ink_id' => $ink_id]);

            // 3. บันทึก History
            $stmtLog = $this->conn->prepare("INSERT INTO tw_ink_usage (tw_pid, ink_id, usage_qty) VALUES (:pid, :ink_id, :qty)");
            $stmtLog->execute([':pid' => $pid, ':ink_id' => $ink_id, ':qty' => $qty]);

            $this->conn->commit();
            return true; // สำเร็จ
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log($e->getMessage());
            return "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>