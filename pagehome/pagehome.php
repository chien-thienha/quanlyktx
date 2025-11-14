<?php
// Thêm kiểm tra đăng nhập
require_once('../auth_check.php');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - Ký túc xá ICTU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="pagehome.css">
</head>
<body>
    <!-- Topbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <!-- Menu toggle button -->
            <button class="navbar-toggler me-3" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="pagehome.php">
                <i class="fas fa-building me-2"></i>KÝ TÚC XÁ ICTU
            </a>
            
            <div class="d-flex align-items-center">
                <!-- Notification -->
                <a href="../qlthongbao/thongbao.html" class="text-white me-3 position-relative">
                    <i class="fas fa-bell fa-lg"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        3
                    </span>
                </a>
                
                <!-- User Avatar -->
                <div class="dropdown">
                    <div class="avatar-circle" id="avatarDropdown" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($_SESSION['tendangnhap'], 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <h6><?php echo $_SESSION['tendangnhap']; ?></h6>
                            <small class="text-muted"><?php echo isset($_SESSION['vaitro']) ? $_SESSION['vaitro'] : 'User'; ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../../quanlyktx/hoso/hscn.php">
                                <i class="fas fa-user me-2"></i>Hồ sơ cá nhân
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="../logout.php">
                                <i class="fas fa-right-from-bracket me-2"></i>Đăng xuất
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-lg-2 col-md-3 sidebar bg-dark">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="quanlytoanha">
                                <i class="fas fa-house me-2"></i>
                                Quản lý tòa nhà
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="quanlysinhvien">
                                <i class="fas fa-user-graduate me-2"></i>
                                Quản lý sinh viên
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="quanlytaichinh">
                                <i class="fas fa-sack-dollar me-2"></i>
                                Quản lý tài chính
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="quanlyvipham">
                                <i class="fas fa-scale-balanced me-2"></i>
                                Quản lý vi phạm
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="quanlyhopdong">
                                <i class="fas fa-file-signature me-2"></i>
                                Quản lý hợp đồng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-module="thongke,baocao">
                                <i class="fas fa-chart-pie me-2"></i>
                                Thống kê, báo cáo
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4 main-content">
                <!-- Welcome Section -->
                <div class="welcome-section text-center py-5" id="welcomeSection">
                    <img src="../img/ictu.png" alt="ICTU" class="mb-4 main-logo">
                    <h1 class="display-4 fw-bold text-primary">XIN CHÀO <?php echo strtoupper($_SESSION['tendangnhap']); ?></h1>
                    <p class="lead text-muted">Chào mừng bạn đến với hệ thống quản lý ký túc xá ICTU!</p>
                </div>

                <!-- Module Content Area -->
                <div id="moduleContent" class="module-content" style="display: none;">
                    <!-- Nội dung module sẽ được load vào đây -->
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="pagehome.js"></script>
</body>
</html>