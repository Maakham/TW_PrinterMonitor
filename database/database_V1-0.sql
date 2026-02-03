CREATE TABLE tw_printer (
    tw_pid INT AUTO_INCREMENT PRIMARY KEY,  -- เพิ่ม Primary Key และ Auto Increment
    tw_pname VARCHAR(100),                  -- ชื่อเครื่องที่เรียกกัน (เช่น Printer บัญชี)
    tw_pbrand VARCHAR(50),                  -- **เพิ่ม:** ยี่ห้อ (HP, Canon, Brother)
    tw_pmodel VARCHAR(100),                 -- **เพิ่ม:** รุ่น (Model) เพื่อรู้ว่าใช้หมึกรหัสอะไร
    tw_pserial VARCHAR(100),                -- **เพิ่ม:** S/N เพื่อระบุตัวตนที่ชัดเจน
    tw_plocation VARCHAR(100),              -- **เพิ่ม:** แผนกหรือจุดที่ตั้งเครื่อง
    tw_pipaddress VARCHAR(50),
    tw_pstatus CHAR(1) DEFAULT 'A',         -- **เพิ่ม:** สถานะ (A=Active, I=Inactive/เสีย)
    tw_pcreate_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tw_pmodify_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE tw_ink_master (
    tw_ink_id INT AUTO_INCREMENT PRIMARY KEY,
    tw_ink_code VARCHAR(50),                -- รหัสหมึก (เช่น TN-263, 85A)
    tw_ink_name VARCHAR(100),               -- ชื่อหมึก/รายละเอียด
    tw_ink_color VARCHAR(20),               -- สี (Black, Cyan, Magenta, Yellow)
    tw_ink_pmodel VARCHAR(20),              -- ใช้สำหรับแสดงว่าใช้งานกับเครื่องพิมพ์ชื่อว่าอะไร
    tw_ink_min_stock INT DEFAULT 2,         -- จุดสั่งซื้อ (ถ้าเหลือน้อยกว่านี้ให้เตือน)
    tw_ink_current_stock INT DEFAULT 0,     -- จำนวนคงเหลือปัจจุบัน
    tw_ink_create_at TIMESTAMP,              -- วันที่สร้าง
    tw_ink_modify_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE tw_usage_log (
    tw_log_id INT AUTO_INCREMENT PRIMARY KEY,
    tw_pid INT,                             -- Link กับตารางเครื่องพิมพ์ (Foreign Key)
    tw_ink_id INT,                          -- Link กับตารางหมึก (Foreign Key)
    tw_meter_start INT,                     -- **สำคัญมาก:** เลขมิเตอร์ (Counter) ณ ตอนเปลี่ยน
    tw_changed_by VARCHAR(100),             -- ชื่อคนเปลี่ยนหมึก
    tw_changed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- วันที่เปลี่ยน
    tw_remark TEXT                          -- หมายเหตุ (เช่น เปลี่ยนเพราะหมึกแตก, เปลี่ยนตามรอบ)
);

-- ตารางสำหรับเก็บประวัติ SNMP (เพิ่มใหม่)
CREATE TABLE tw_snmp_log (
    tw_log_id INT AUTO_INCREMENT PRIMARY KEY,
    tw_pid INT NOT NULL,                  -- ลิงก์กับตารางเครื่องพิมพ์ (Foreign Key)
    tw_total_page INT DEFAULT 0,          -- เก็บค่า $pageCount (จำนวนพิมพ์รวม)
    tw_ink_status_json TEXT,              -- เก็บค่าหมึกทั้งหมดเป็น JSON (เพราะแต่ละรุ่นสีไม่เหมือนกัน)
    tw_recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- เวลาที่บันทึก
    INDEX (tw_pid),
    INDEX (tw_recorded_at)
);