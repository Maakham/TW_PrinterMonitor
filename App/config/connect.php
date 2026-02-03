<?php

    // ตั้งค่าการเชื่อมต่อฐานข้อมูล
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "it_taywin";

    define('BASE_URL_Error', 'http://localhost/printcontrol/');
    define('BASE_URL_APP', BASE_URL_Error . 'App/');
    
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $current_path = $_SERVER['REQUEST_URI'];
    if (strpos($current_path, '/printcontrol/App/') === false) {
        header("Location: " . BASE_URL_APP);
        exit;
    }
    } catch(PDOException $e) {
        // die("Connection failed: " . $e->getMessage());
        header("Location: " . BASE_URL_Error . "index.php?error=" . urlencode($e->getMessage()));
        // header("location : 404.php");
        exit;
    }

    
?>