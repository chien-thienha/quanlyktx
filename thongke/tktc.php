<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// KHỞI TẠO BIẾN
$toast_message = '';
$toast_type = '';
$filter_loaigiaodich = 'all';
$filter_trangthai = 'all';
$search_keyword = '';
$result = null;

// LẤY THÔNG BÁO TỪ SESSION
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// XỬ LÝ FILTER
if (isset($_GET['loaigiaodich'])) $filter_loaigiaodich = $_GET['loaigiaodich'];
if (isset($_GET['trangthai'])) $filter_trangthai = $_GET['trangthai'];
if (isset($_GET['search'])) $search_keyword = trim($_GET['search']);

// XỬ LÝ CẬP NHẬT TRẠNG THÁI GIAO DỊCH
if (isset($_GET['update_status'])) {
    $idtaichinh = $_GET['id'];
    $new_status = $_GET['new_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE taichinh SET trangthai = ? WHERE idtaichinh = ?");
        $stmt->bind_param("si", $new_status, $idtaichinh);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật trạng thái thành công!";
            $_SESSION['toast_type'] = 'success';
        } else {
            throw new Exception("Lỗi khi cập nhật trạng thái");
        }
    } catch (Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: tktc.php");
    exit();
}

// XỬ LÝ XÓA GIAO DỊCH
if (isset($_GET['delete'])) {
    $idtaichinh = $_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM taichinh WHERE idtaichinh = ?");
        $stmt->bind_param("i", $idtaichinh);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa giao dịch thành công!";
            $_SESSION['toast_type'] = 'success';
        } else {
            throw new Exception("Lỗi khi xóa giao dịch");
        }
    } catch (Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: tktc.php");
    exit();
}

// XÂY DỰNG QUERY THỐNG KÊ TÀI CHÍNH - SỬA THEO ĐÚNG CẤU TRÚC BẢNG
$query = "SELECT 
            idtaichinh,
            magiaodich,
            masinhvien,
            tensinhvien,
            loaigiaodich,
            ngaygiaodich,
            sotien,
            trangthai
          FROM taichinh 
          WHERE 1=1";

$params = []; 
$types = "";

// Áp dụng filters - SỬA THEO ĐÚNG GIÁ TRỊ ENUM
if ($filter_loaigiaodich !== 'all') {
    $query .= " AND loaigiaodich = ?";
    $params[] = $filter_loaigiaodich;
    $types .= "s";
}

if ($filter_trangthai !== 'all') {
    $query .= " AND trangthai = ?";
    $params[] = $filter_trangthai;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $query .= " AND (masinhvien LIKE ? OR tensinhvien LIKE ? OR magiaodich LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY ngaygiaodich DESC, idtaichinh DESC";

// Thực thi query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query($query);
}

// LẤY DỮ LIỆU CHO BIỂU ĐỒ THỐNG KÊ - SỬA THEO ĐÚNG DỮ LIỆU THỰC TẾ
$stats_query = "SELECT 
    COUNT(*) as tong_giaodich,
    SUM(CASE WHEN loaigiaodich = 'Tiền phòng' THEN sotien ELSE 0 END) as tong_tienphong,
    SUM(CASE WHEN loaigiaodich = 'Tiền điện, nước' THEN sotien ELSE 0 END) as tong_tiendiennuoc,
    SUM(CASE WHEN loaigiaodich = 'Tiền Internet/Wi-Fi' THEN sotien ELSE 0 END) as tong_tieninternet,
    SUM(CASE WHEN loaigiaodich = 'Tiền vệ sinh, rác' THEN sotien ELSE 0 END) as tong_tienvesinh,
    SUM(CASE WHEN loaigiaodich = 'Tài sản, thiết bị' THEN sotien ELSE 0 END) as tong_taisan,
    SUM(CASE WHEN loaigiaodich = 'Tiền đặt cọc' THEN sotien ELSE 0 END) as tong_tiendatcoc,
    SUM(CASE WHEN loaigiaodich = 'Khác' THEN sotien ELSE 0 END) as tong_khac,
    SUM(CASE WHEN trangthai = 'Đã thanh toán' THEN sotien ELSE 0 END) as tong_dathanhtoan,
    SUM(CASE WHEN trangthai = 'Chưa thanh toán' THEN sotien ELSE 0 END) as tong_chuathanhtoan,
    SUM(sotien) as tong_tien,
    COUNT(CASE WHEN trangthai = 'Đã thanh toán' THEN 1 END) as count_dathanhtoan,
    COUNT(CASE WHEN trangthai = 'Chưa thanh toán' THEN 1 END) as count_chuathanhtoan
FROM taichinh";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Đảm bảo không có giá trị null
if (!$stats) {
    $stats = [
        'tong_giaodich' => 0,
        'tong_tienphong' => 0,
        'tong_tiendiennuoc' => 0,
        'tong_tieninternet' => 0,
        'tong_tienvesinh' => 0,
        'tong_taisan' => 0,
        'tong_tiendatcoc' => 0,
        'tong_khac' => 0,
        'tong_dathanhtoan' => 0,
        'tong_chuathanhtoan' => 0,
        'tong_tien' => 0,
        'count_dathanhtoan' => 0,
        'count_chuathanhtoan' => 0
    ];
}

// Lấy danh sách loại giao dịch
$loai_gd_result = $conn->query("SELECT DISTINCT loaigiaodich FROM taichinh ORDER BY loaigiaodich");

// XỬ LÝ XUẤT EXCEL
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="thongke_taichinh_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<meta charset='UTF-8'>";
    echo "<table border='1'>";
    echo "<tr>
            <th>STT</th>
            <th>Mã giao dịch</th>
            <th>Mã sinh viên</th>
            <th>Tên sinh viên</th>
            <th>Loại giao dịch</th>
            <th>Ngày giao dịch</th>
            <th>Số tiền</th>
            <th>Trạng thái</th>
          </tr>";
    
    $export_result = $conn->query($query);
    $stt = 1;
    while ($row = $export_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $stt . "</td>";
        echo "<td>" . $row['magiaodich'] . "</td>";
        echo "<td>" . $row['masinhvien'] . "</td>";
        echo "<td>" . $row['tensinhvien'] . "</td>";
        echo "<td>" . $row['loaigiaodich'] . "</td>";
        echo "<td>" . $row['ngaygiaodich'] . "</td>";
        echo "<td>" . number_format($row['sotien'], 0, ',', '.') . " VNĐ</td>";
        echo "<td>" . $row['trangthai'] . "</td>";
        echo "</tr>";
        $stt++;
    }
    echo "</table>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê Tài chính</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="thongke.css">
</head>
<body>
    <!-- Toast notifications -->
    <div class="toast-container" id="toastContainer">
        <?php if (!empty($toast_message)): ?>
            <div class="toast <?php echo $toast_type; ?>" id="autoToast">
                <div class="toast-icon">
                    <i class="fas fa-<?php echo $toast_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title"><?php echo $toast_type == 'success' ? 'Thành công!' : 'Lỗi!'; ?></div>
                    <div class="toast-message"><?php echo $toast_message; ?></div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Thống kê & Báo cáo</h1>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <ul class="tabs">
                <li class="tab-item">
                    <a href="tkphong.php" class="tab-link">Thống kê Phòng ở</a>
                </li>
                <li class="tab-item">
                    <a href="tktc.php" class="tab-link active">Thống kê Tài chính</a>
                </li>
                <li class="tab-item">
                    <a href="tkvp.php" class="tab-link">Thống kê Vi phạm</a>
                </li>
            </ul>
        </div>

        <!-- Tab Content - Thống kê Tài chính -->
        <div class="tab-content active">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Tổng giao dịch</h3>
                    <div class="number"><?php echo $stats['tong_giaodich']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Tổng tiền</h3>
                    <div class="number"><?php echo number_format($stats['tong_tien'], 0, ',', '.'); ?> VNĐ</div>
                </div>
                <div class="summary-card">
                    <h3>Đã thanh toán</h3>
                    <div class="number"><?php echo number_format($stats['tong_dathanhtoan'], 0, ',', '.'); ?> VNĐ</div>
                    <small>(<?php echo $stats['count_dathanhtoan']; ?> giao dịch)</small>
                </div>
                <div class="summary-card">
                    <h3>Chưa thanh toán</h3>
                    <div class="number"><?php echo number_format($stats['tong_chuathanhtoan'], 0, ',', '.'); ?> VNĐ</div>
                    <small>(<?php echo $stats['count_chuathanhtoan']; ?> giao dịch)</small>
                </div>
            </div>
            
            <!-- Filter section -->
            <div class="filter-section">
                <form method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filter_loaigiaodich">Loại giao dịch:</label>
                            <select id="filter_loaigiaodich" name="loaigiaodich" onchange="this.form.submit()">
                                <option value="all">Tất cả loại giao dịch</option>
                                <?php 
                                if ($loai_gd_result && $loai_gd_result->num_rows > 0) {
                                    $loai_gd_result->data_seek(0);
                                    while ($loai_gd = $loai_gd_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $loai_gd['loaigiaodich']; ?>" 
                                        <?php echo $filter_loaigiaodich == $loai_gd['loaigiaodich'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loai_gd['loaigiaodich']); ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_trangthai">Trạng thái:</label>
                            <select id="filter_trangthai" name="trangthai" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_trangthai == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="Đã thanh toán" <?php echo $filter_trangthai == 'Đã thanh toán' ? 'selected' : ''; ?>>Đã thanh toán</option>
                                <option value="Chưa thanh toán" <?php echo $filter_trangthai == 'Chưa thanh toán' ? 'selected' : ''; ?>>Chưa thanh toán</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Tìm kiếm:</label>
                            <div class="search-container">
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" 
                                       placeholder="Tìm kiếm thông tin" oninput="handleSearchInput()">
                                <button type="button" class="btn-clear-search" id="btnClearSearch" 
                                        onclick="clearSearch()" style="<?php echo empty($search_keyword) ? 'display: none;' : ''; ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" class="btn-reset" onclick="resetFilters()">
                                <i class="fas fa-times"></i> Xóa bộ lọc
                            </button>
                            <button type="button" class="btn-export" onclick="exportToExcel()">
                                <i class="fas fa-file-export"></i> Xuất Excel
                            </button>
                            <button type="button" class="btn-print" onclick="window.print()">
                                <i class="fas fa-print"></i> In báo cáo
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Chart Section -->
            <div class="chart-section">
                <h2>Biểu đồ thống kê tài chính</h2>
                <div class="chart-container">
                    <canvas id="loaiGiaoDichChart"></canvas>
                    <canvas id="trangThaiChart"></canvas>
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-container">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã giao dịch</th>
                            <th>Mã sinh viên</th>
                            <th>Tên sinh viên</th>
                            <th>Loại giao dịch</th>
                            <th>Ngày giao dịch</th>
                            <th>Số tiền</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo htmlspecialchars($row['magiaodich']); ?></td>
                                    <td><?php echo htmlspecialchars($row['masinhvien']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tensinhvien']); ?></td>
                                    <td>
                                        <span class="status-badge primary">
                                            <?php echo htmlspecialchars($row['loaigiaodich']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($row['ngaygiaodich'])); ?></td>
                                    <td style="font-weight: bold; color: #2c3e50;">
                                        <?php echo number_format($row['sotien'], 0, ',', '.'); ?> VNĐ
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $row['trangthai'] == 'Đã thanh toán' ? 'success' : 'warning'; ?>">
                                            <?php echo $row['trangthai']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($row['trangthai'] == 'Chưa thanh toán'): ?>
                                                <button class="btn btn-small btn-success" 
                                                        onclick="updateStatus(<?php echo $row['idtaichinh']; ?>, 'Đã thanh toán')"
                                                        title="Đánh dấu đã thanh toán">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-small btn-warning" 
                                                        onclick="updateStatus(<?php echo $row['idtaichinh']; ?>, 'Chưa thanh toán')"
                                                        title="Đánh dấu chưa thanh toán">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-small btn-danger" 
                                                    onclick="confirmDelete(<?php echo $row['idtaichinh']; ?>)"
                                                    title="Xóa giao dịch">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align: center;">Không có dữ liệu giao dịch</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout = null;

        function resetFilters() {
            window.location.href = 'tktc.php';
        }

        function exportToExcel() {
            let url = 'tktc.php?action=export_excel';
            const params = new URLSearchParams(window.location.search);
            window.open(url + '&' + params.toString(), '_blank');
        }

        function handleSearchInput() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 800);
        }

        function clearSearch() {
            document.getElementById('search').value = '';
            document.getElementById('filterForm').submit();
        }

        function updateStatus(id, newStatus) {
            if (confirm('Bạn có chắc muốn thay đổi trạng thái giao dịch này?')) {
                window.location.href = `tktc.php?update_status=1&id=${id}&new_status=${newStatus}`;
            }
        }

        function confirmDelete(id) {
            if (confirm('Bạn có chắc muốn xóa giao dịch này? Hành động này không thể hoàn tác.')) {
                window.location.href = `tktc.php?delete=1&id=${id}`;
            }
        }

        // Hiển thị toast
        document.addEventListener('DOMContentLoaded', function() {
            const autoToast = document.getElementById('autoToast');
            if (autoToast) {
                setTimeout(() => autoToast.classList.add('show'), 100);
                setTimeout(() => autoToast.remove(), 5000);
            }

            // Initialize charts
            initializeCharts();
        });

        function initializeCharts() {
            // Chart 1: Phân bổ theo loại giao dịch
            const loaiGiaoDichCtx = document.getElementById('loaiGiaoDichChart').getContext('2d');
            const loaiGiaoDichChart = new Chart(loaiGiaoDichCtx, {
                type: 'pie',
                data: {
                    labels: ['Tiền phòng', 'Tiền điện, nước', 'Tiền Internet', 'Tiền vệ sinh', 'Tài sản', 'Đặt cọc', 'Khác'],
                    datasets: [{
                        data: [
                            <?php echo $stats['tong_tienphong'] ?: 0; ?>,
                            <?php echo $stats['tong_tiendiennuoc'] ?: 0; ?>,
                            <?php echo $stats['tong_tieninternet'] ?: 0; ?>,
                            <?php echo $stats['tong_tienvesinh'] ?: 0; ?>,
                            <?php echo $stats['tong_taisan'] ?: 0; ?>,
                            <?php echo $stats['tong_tiendatcoc'] ?: 0; ?>,
                            <?php echo $stats['tong_khac'] ?: 0; ?>
                        ],
                        backgroundColor: [
                            '#3498db', '#e74c3c', '#f39c12', '#9b59b6', 
                            '#1abc9c', '#d35400', '#95a5a6'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { 
                            display: true, 
                            text: 'Phân bổ theo loại giao dịch',
                            font: { size: 16 }
                        }
                    }
                }
            });

            // Chart 2: Trạng thái thanh toán
            const trangThaiCtx = document.getElementById('trangThaiChart').getContext('2d');
            const trangThaiChart = new Chart(trangThaiCtx, {
                type: 'bar',
                data: {
                    labels: ['Đã thanh toán', 'Chưa thanh toán'],
                    datasets: [{
                        label: 'Số tiền (VNĐ)',
                        data: [
                            <?php echo $stats['tong_dathanhtoan'] ?: 0; ?>,
                            <?php echo $stats['tong_chuathanhtoan'] ?: 0; ?>
                        ],
                        backgroundColor: ['#27ae60', '#e74c3c'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { 
                            display: true, 
                            text: 'Trạng thái thanh toán',
                            font: { size: 16 }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('vi-VN') + ' VNĐ';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>