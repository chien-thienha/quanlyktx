<?php
    // config.php (phiên bản MySQLi, tương thích với code login)
    $servername = "127.0.0.1";  // dùng 127.0.0.1 thay vì localhost cho chắc
    $username = "root";
    $password = "";
    $database = "quanlyktx";

    // Kết nối MySQLi
    $conn = new mysqli($servername, $username, $password, $database);

    // Kiểm tra kết nối
    if ($conn->connect_error) {
        die("Không thể kết nối đến database: " . $conn->connect_error);
    }

    // Đặt charset UTF-8
    $conn->set_charset("utf8mb4");
?>
