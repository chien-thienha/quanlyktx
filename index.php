<?php
    session_start();
    // Kiểm tra nếu đã đăng nhập
    if (isset($_SESSION['tendangnhap'])) {
        header("Location: quanlyktx/pagehome/pagehome.php"); // Thêm
        exit();
    } else {
        // Chuyển đến trang đăng nhập
        header("Location: ././login+register/pagelogin.php"); // Đổi thành php nếu cần
        exit();
    }
?>