1. เมื่อทำการติดตั่งส่วนที่ต้องทำการเปลี่ยน

2. ทำการเข้า Xampp --> Apache --> php.init --> ;extension=snmp ทำการเอา ; ออก 

printcontrol/
  index.php
  functions.php
  status.php          # JSON (ใช้กับ fetch polling)
  stream.php          # SSE (ใช้กับ EventSource)
  assets/
    css/index.css
    js/index.js
  data/               # จะถูกสร้างอัตโนมัติสำหรับ baseline counter


Version 1.0.2
1. เพิ่มเติมข้อมูลภายในระบบทั้งหมดให้ดูง่าย เช่น Device Name, Ink ID, S/N Toner ฯลฯ
2. เพิ่มเติมส่วนการเก็บข้อมูลเข้า Database เพื่อทำการนำข้อมูลมาเปรียบเที่ยบ
3. แสดงเมนูสำหรับลิ้งค์ไปที่ร้านค้าสำหรับสั่งซื้อ 