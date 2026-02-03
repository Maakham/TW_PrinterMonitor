<script>
// สร้าง Instance ของ Modal เตรียมไว้
const printerModal = new bootstrap.Modal(document.getElementById('printerDetailModal'));
const modalContent = document.getElementById('modalContent');

function openPrinterModal(ip) {
    // 1. เปิด Modal
    printerModal.show();
    // 2. แสดงสถานะ Loading ระหว่างรอ PHP ดึง SNMP
    modalContent.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="text-muted">กำลังดึงข้อมูล SNMP จาก ${ip}...</h5>
                    <small class="text-muted">กรุณารอสักครู่ (ขึ้นอยู่กับความเร็ว Network)</small>
                </div>
            `;
    // 3. ยิง Request ไปหาไฟล์ตัวเอง (AJAX) โดยส่ง param 'ajax_ip'
    fetch('?ajax_ip=' + ip)
        .then(response => response.text())
        .then(html => {
            // 4. เอา HTML ที่ได้จาก PHP มาใส่ใน Modal
            modalContent.innerHTML = html;
        })
        .catch(error => {
            modalContent.innerHTML = `
                        <div class="alert alert-danger text-center">
                            <strong>เกิดข้อผิดพลาด!</strong> ไม่สามารถดึงข้อมูลได้ <br> (${error})
                        </div>`;
        });
}
</script>
</body>

</html>