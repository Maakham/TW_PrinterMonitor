<?php
// ส่วนจัดการ Logic ของ PHP (Backend)
// ตรวจสอบว่าเป็นคำขอแบบ AJAX หรือไม่ (เพื่อส่งเฉพาะเนื้อหา ไม่ส่ง Header/Footer)
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// ฟังก์ชันสำหรับเลือกเนื้อหาที่จะแสดง
function getContent($page) {
    global $conn; // เรียกใช้ connection จาก global scope ถ้ามีการ require config ไว้ข้างบนสุด
    
    switch ($page) {
        case 'printer_monitor':
            $filePath = __DIR__ . '/view/p_monitor/printer_list.php'; 
            
            if (file_exists($filePath)) {
                ob_start();
                include $filePath;
                return ob_get_clean();
            } else {
                return '<div class="p-4 text-red-500 text-center">ไม่พบไฟล์: ' . $filePath . '</div>';
            }
            
        case 'home':
            return '
                <div class="animate-fade-in">
                    <h1 class="text-3xl font-bold text-gray-800 mb-4">ยินดีต้อนรับสู่หน้าแรก</h1>
                </div>';
       case 'asset_stock':
            // ระบุ Path ไปหาไฟล์ ink_stock.php ที่เราเพิ่งสร้าง
            $filePath = __DIR__ . '/view/p_asset/ink_stock.php';
            
            if (file_exists($filePath)) {
                ob_start();
                include $filePath;
                return ob_get_clean();
            } else {
                return '<div class="p-4 text-red-500 text-center">ไม่พบไฟล์: ' . $filePath . '</div>';
            }
        default:
            return '<h1 class="text-2xl text-red-500">404 - ไม่พบหน้าที่ต้องการ</h1>';
    }
}

// *** ส่วนสำคัญ: ถ้าเป็น AJAX Request ให้ส่งแค่เนื้อหาแล้วจบการทำงานทันที ***
if ($is_ajax) {
    echo getContent($page);
    exit; // หยุดการทำงาน ไม่โหลด HTML ส่วนล่าง
}

// ถ้าไม่ใช่ AJAX (เปิดหน้าเว็บครั้งแรก) ให้โหลด HTML เต็มรูปแบบ

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Menu No Reload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* style Active Link */
    .nav-link.active {
        background-color: #3b82f6;
        color: white;
    }
    </style>
</head>

<body>

    <!-- navbar -->
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="font-bold text-xl text-gray-800">TW <span class="text-blue-500">Monitor</span></div>
                <ul class="flex space-x-2">
                    <!-- เมนูต่างๆ เรียกฟังก์ชัน navigate() -->
                    <li><a href="?page=home" onclick="navigate(event, 'home')"
                            class="nav-link px-4 py-2 rounded-md transition hover:bg-blue-100 text-gray-700">หน้าแรก</a>
                    </li>
                    <li><a href="?page=printer_monitor" onclick="navigate(event, 'printer_monitor')"
                            class="nav-link px-4 py-2 rounded-md transition hover:bg-blue-100 text-gray-700">เครื่องพิมพ์</a>
                    </li>
                    <li>
                        <a href="?page=asset_stock" onclick="navigate(event, 'asset_stock')"
                            class="nav-link px-4 py-2 rounded-md transition hover:bg-blue-100 text-gray-700">
                            สต๊อกหมึก
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ส่วนเนื้อหา -->
    <main class="">
        <div id="content-area" class="p-8 rounded-xl min-h-[400px]">
            <?php echo getContent($page); ?>
        </div>
    </main>

    <!-- JavaScript สำหรับจัดการการโหลดหน้า (ห้ามปรับเยอะ ทำงานได้ไงไม่รู้) -->
    <script>
    // ฟังก์ชันหลักในการเปลี่ยนหน้า
    async function navigate(event, pageName) {
        // 1. ป้องกันไม่ให้ Browser เปลี่ยนหน้าตามปกติ (Prevent Default Link Behavior)
        if (event) event.preventDefault();

        const contentDiv = document.getElementById('content-area');

        // 2. แสดงสถานะกำลังโหลด
        contentDiv.innerHTML = `
                <div class="flex flex-col items-center justify-center h-64 text-gray-400">
                    <svg class="animate-spin h-10 w-10 mb-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p>กำลังโหลดข้อมูล...</p>
                </div>
            `;

        try {
            // 3. ใช้ Fetch API ดึงข้อมูลจาก PHP (ส่ง parameter ajax=1 ไปด้วย)
            const response = await fetch(`index.php?page=${pageName}&ajax=1`);

            if (!response.ok) throw new Error('Network response was not ok');

            const html = await response.text();

            // 4. เอา HTML ที่ได้มาใส่ใน Div
            contentDiv.innerHTML = html;

            // 5. เปลี่ยน URL ด้านบน Browser เพื่อให้ดูเหมือนเปลี่ยนหน้าจริง (Optional)
            window.history.pushState({
                page: pageName
            }, '', `?page=${pageName}`);

            // 6. อัปเดตสีปุ่มเมนู (Active State)
            updateActiveMenu(pageName);

        } catch (error) {
            console.error('Error:', error);
            contentDiv.innerHTML = '<p class="text-red-500 text-center mt-10">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
        }
    }

    // ฟังก์ชันเปลี่ยนสีปุ่มเมนู
    function updateActiveMenu(pageName) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            // ตรวจสอบว่าลิงก์ไหนตรงกับหน้าปัจจุบัน
            if (link.getAttribute('href').includes(`page=${pageName}`)) {
                link.classList.add('active');
            }
        });
    }

    // จัดการกรณีผู้ใช้กดปุ่ม Back/Forward ของ Browser
    window.onpopstate = function(event) {
        if (event.state && event.state.page) {
            // โหลดหน้านั้นๆ ใหม่โดยไม่ต้องใส่ event (เพราะไม่ได้คลิก)
            navigate(null, event.state.page);
        } else {
            // กรณีกลับมาหน้าแรกสุด
            navigate(null, 'home');
        }
    };

    // ตั้งค่า Active Menu ตอนโหลดหน้าครั้งแรก
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const currentPage = params.get('page') || 'home';
        updateActiveMenu(currentPage);
    });
    </script>
</body>

</html>