<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// KHỞI TẠO BIẾN
$toast_message = '';
$toast_type = '';
$filter_toanha = 'all';
$filter_tinhtrang = 'all';
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
if (isset($_GET['toanha'])) $filter_toanha = $_GET['toanha'];
if (isset($_GET['tinhtrang'])) $filter_tinhtrang = $_GET['tinhtrang'];
if (isset($_GET['trangthai'])) $filter_trangthai = $_GET['trangthai'];
if (isset($_GET['search'])) $search_keyword = trim($_GET['search']);

// XÂY DỰNG QUERY THỐNG KÊ - SỬA LẠI CHO PHÙ HỢP VỚI DB STRUCTURE
$query = "SELECT 
            p.idphong,
            p.phong,
            t.toanha,
            p.tinhtrang,
            p.trangthaihoatdong,
            COUNT(DISTINCT sv.idsinhvien) as so_sv_dang_o
          FROM phong p 
          LEFT JOIN toanha t ON p.idtoanha = t.idtoanha 
          LEFT JOIN sinhvien sv ON p.phong = sv.phong AND sv.trangthai = 'Đang ở' 
          WHERE 1=1";

$params = []; 
$types = "";

// Áp dụng filters
if ($filter_toanha !== 'all') {
    $query .= " AND t.idtoanha = ?";
    $params[] = $filter_toanha;
    $types .= "i";
}

if ($filter_tinhtrang !== 'all') {
    $query .= " AND p.tinhtrang = ?";
    $params[] = $filter_tinhtrang;
    $types .= "s";
}

if ($filter_trangthai !== 'all') {
    $query .= " AND p.trangthaihoatdong = ?";
    $params[] = $filter_trangthai;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $query .= " AND (p.phong LIKE ? OR t.toanha LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " GROUP BY p.idphong, p.phong, t.toanha, p.tinhtrang, p.trangthaihoatdong 
            ORDER BY t.toanha, p.phong ASC";

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

// LẤY DỮ LIỆU CHO BIỂU ĐỒ - SỬA LẠI CHO ĐÚNG CẤU TRÚC DB
$stats_query = "SELECT 
    COUNT(*) as tong_phong,
    SUM(CASE WHEN tinhtrang = 'Còn trống' THEN 1 ELSE 0 END) as phong_trong,
    SUM(CASE WHEN tinhtrang = 'Đã kín' THEN 1 ELSE 0 END) as phong_kin,
    SUM(CASE WHEN trangthaihoatdong = 'Hoạt động' THEN 1 ELSE 0 END) as phong_hoat_dong,
    SUM(CASE WHEN trangthaihoatdong = 'Không hoạt động' THEN 1 ELSE 0 END) as phong_khong_hoat_dong
FROM phong";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Lấy danh sách tòa nhà
$toa_nha_result = $conn->query("SELECT idtoanha, toanha FROM toanha ORDER BY toanha");

// XỬ LÝ XUẤT EXCEL
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="thongke_phong_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<meta charset='UTF-8'>";
    echo "<table border='1'>";
    echo "<tr>
            <th>STT</th>
            <th>Tên Phòng</th>
            <th>Tòa Nhà</th>
            <th>Tình Trạng</th>
            <th>Trạng Thái HĐ</th>
            <th>Số SV Đang Ở</th>
          </tr>";
    
    $export_result = $conn->query($query);
    $stt = 1;
    while ($row = $export_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $stt . "</td>";
        echo "<td>" . $row['phong'] . "</td>";
        echo "<td>" . $row['toanha'] . "</td>";
        echo "<td>" . $row['tinhtrang'] . "</td>";
        echo "<td>" . $row['trangthaihoatdong'] . "</td>";
        echo "<td>" . $row['so_sv_dang_o'] . "</td>";
        echo "</tr>";
        $stt++;
    }
    echo "</table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê Phòng ở</title>
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
                    <a href="tkphong.php" class="tab-link active">Thống kê Phòng ở</a>
                </li>
                <li class="tab-item">
                    <a href="tktc.php" class="tab-link">Thống kê Tài chính</a>
                </li>
                <li class="tab-item">
                    <a href="tkvp.php" class="tab-link">Thống kê Vi phạm</a>
                </li>
            </ul>
        </div>

        <!-- Tab Content - Thống kê Phòng ở -->
        <div class="tab-content active">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Tổng số phòng</h3>
                    <div class="number"><?php echo $stats['tong_phong']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Phòng còn trống</h3>
                    <div class="number"><?php echo $stats['phong_trong']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Phòng đã kín</h3>
                    <div class="number"><?php echo $stats['phong_kin']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Phòng hoạt động</h3>
                    <div class="number"><?php echo $stats['phong_hoat_dong']; ?></div>
                </div>
            </div>
            
            <!-- Filter section -->
            <div class="filter-section">
                <form method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filter_toanha">Tòa nhà:</label>
                            <select id="filter_toanha" name="toanha" onchange="this.form.submit()">
                                <option value="all">Tất cả tòa nhà</option>
                                <?php 
                                if ($toa_nha_result && $toa_nha_result->num_rows > 0) {
                                    $toa_nha_result->data_seek(0);
                                    while ($toa_nha = $toa_nha_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $toa_nha['idtoanha']; ?>" 
                                        <?php echo $filter_toanha == $toa_nha['idtoanha'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($toa_nha['toanha']); ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_tinhtrang">Tình trạng:</label>
                            <select id="filter_tinhtrang" name="tinhtrang" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_tinhtrang == 'all' ? 'selected' : ''; ?>>Tất cả tình trạng</option>
                                <option value="Còn trống" <?php echo $filter_tinhtrang == 'Còn trống' ? 'selected' : ''; ?>>Còn trống</option>
                                <option value="Đã kín" <?php echo $filter_tinhtrang == 'Đã kín' ? 'selected' : ''; ?>>Đã kín</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_trangthai">Trạng thái hoạt động:</label>
                            <select id="filter_trangthai" name="trangthai" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_trangthai == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="Hoạt động" <?php echo $filter_trangthai == 'Hoạt động' ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="Không hoạt động" <?php echo $filter_trangthai == 'Không hoạt động' ? 'selected' : ''; ?>>Không hoạt động</option>
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
                <h2>Biểu đồ thống kê</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-container">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên phòng</th>
                            <th>Tòa nhà</th>
                            <th>Tình trạng</th>
                            <th>Trạng thái HĐ</th>
                            <th>Số SV đang ở</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo htmlspecialchars($row['phong']); ?></td>
                                    <td><?php echo htmlspecialchars($row['toanha']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $row['tinhtrang'] == 'Còn trống' ? 'empty' : 'full'; ?>">
                                            <?php echo htmlspecialchars($row['tinhtrang']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $row['trangthaihoatdong'] == 'Hoạt động' ? 'active' : 'inactive'; ?>">
                                            <?php echo htmlspecialchars($row['trangthaihoatdong']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['so_sv_dang_o']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center;">Không có dữ liệu phòng</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout = null;

        function resetFilters() {
            window.location.href = 'tkphong.php';
        }

        function exportToExcel() {
            let url = 'tkphong.php?action=export_excel';
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

        // Hiển thị toast
        document.addEventListener('DOMContentLoaded', function() {
            const autoToast = document.getElementById('autoToast');
            if (autoToast) {
                setTimeout(() => autoToast.classList.add('show'), 100);
                setTimeout(() => autoToast.remove(), 5000);
            }

            // Initialize charts
            initializeCharts();
            
            // Xác định tab hiện tại dựa trên URL
            highlightCurrentTab();
        });

        function highlightCurrentTab() {
            const currentPage = window.location.pathname.split('/').pop();
            const tabs = document.querySelectorAll('.tab-link');
            
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('href') === currentPage) {
                    tab.classList.add('active');
                }
            });
        }

        function initializeCharts() {
            // Chart 1: Tình trạng phòng
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Phòng trống', 'Phòng đã kín'],
                    datasets: [{
                        data: [<?php echo $stats['phong_trong']; ?>, <?php echo $stats['phong_kin']; ?>],
                        backgroundColor: ['#3498db', '#e74c3c'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { 
                            display: true, 
                            text: 'Thống kê tình trạng phòng',
                            font: { size: 16 }
                        }
                    }
                }
            });

            // Chart 2: Trạng thái hoạt động
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'bar',
                data: {
                    labels: ['Hoạt động', 'Không hoạt động'],
                    datasets: [{
                        label: 'Số lượng phòng',
                        data: [<?php echo $stats['phong_hoat_dong']; ?>, <?php echo $stats['phong_khong_hoat_dong']; ?>],
                        backgroundColor: ['#27ae60', '#f39c12'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { 
                            display: true, 
                            text: 'Trạng thái hoạt động',
                            font: { size: 16 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    </script>
</body>
</html>