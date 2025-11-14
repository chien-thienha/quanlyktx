<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// KHỞI TẠO BIẾN
$toast_message = '';
$toast_type = '';
$filter_mucdo = 'all';
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
if (isset($_GET['mucdo'])) $filter_mucdo = $_GET['mucdo'];
if (isset($_GET['trangthai'])) $filter_trangthai = $_GET['trangthai'];
if (isset($_GET['search'])) $search_keyword = trim($_GET['search']);

// XÂY DỰNG QUERY THỐNG KÊ VI PHẠM
$query = "SELECT 
            v.idvipham,
            v.masinhvien,
            sv.tensinhvien,
            v.ngayvipham,
            v.mucdovipham,
            v.trangthai
          FROM vipham v 
          LEFT JOIN sinhvien sv ON v.masinhvien = sv.masinhvien 
          WHERE 1=1";

$params = []; 
$types = "";

// Áp dụng filters
if ($filter_mucdo !== 'all') {
    $query .= " AND v.mucdovipham = ?";
    $params[] = $filter_mucdo;
    $types .= "s";
}

if ($filter_trangthai !== 'all') {
    $query .= " AND v.trangthai = ?";
    $params[] = $filter_trangthai;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $query .= " AND (v.masinhvien LIKE ? OR sv.tensinhvien LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY v.ngayvipham DESC, v.idvipham ASC";

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

// LẤY DỮ LIỆU CHO BIỂU ĐỒ
$stats_query = "SELECT 
    COUNT(*) as tong_vipham,
    SUM(CASE WHEN mucdovipham = 'Nhẹ' THEN 1 ELSE 0 END) as vipham_nhe,
    SUM(CASE WHEN mucdovipham = 'Vừa' THEN 1 ELSE 0 END) as vipham_vua,
    SUM(CASE WHEN mucdovipham = 'Nặng' THEN 1 ELSE 0 END) as vipham_nang,
    SUM(CASE WHEN trangthai = 'Đã xử lý' THEN 1 ELSE 0 END) as vipham_daxuly,
    SUM(CASE WHEN trangthai = 'Chưa xử lý' THEN 1 ELSE 0 END) as vipham_chuaxuly
FROM vipham";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// XỬ LÝ XUẤT EXCEL
if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="thongke_vipham_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "<meta charset='UTF-8'>";
    echo "<table border='1'>";
    echo "<tr>
            <th>STT</th>
            <th>Mã sinh viên</th>
            <th>Tên sinh viên</th>
            <th>Ngày vi phạm</th>
            <th>Mức độ vi phạm</th>
            <th>Trạng thái</th>
          </tr>";
    
    $export_result = $conn->query($query);
    $stt = 1;
    while ($row = $export_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $stt . "</td>";
        echo "<td>" . $row['masinhvien'] . "</td>";
        echo "<td>" . $row['tensinhvien'] . "</td>";
        echo "<td>" . $row['ngayvipham'] . "</td>";
        echo "<td>" . $row['mucdovipham'] . "</td>";
        echo "<td>" . $row['trangthai'] . "</td>";
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
    <title>Thống kê Vi phạm</title>
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

        <!-- Tab Content - Thống kê Vi phạm -->
        <div class="tab-content active">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Tổng số vi phạm</h3>
                    <div class="number"><?php echo $stats['tong_vipham']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vi phạm nhẹ</h3>
                    <div class="number"><?php echo $stats['vipham_nhe']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vi phạm vừa</h3>
                    <div class="number"><?php echo $stats['vipham_vua']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Vi phạm nặng</h3>
                    <div class="number"><?php echo $stats['vipham_nang']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Đã xử lý</h3>
                    <div class="number"><?php echo $stats['vipham_daxuly']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Chưa xử lý</h3>
                    <div class="number"><?php echo $stats['vipham_chuaxuly']; ?></div>
                </div>
            </div>
            
            <!-- Filter section -->
            <div class="filter-section">
                <form method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="filter_mucdo">Mức độ vi phạm:</label>
                            <select id="filter_mucdo" name="mucdo" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_mucdo == 'all' ? 'selected' : ''; ?>>Tất cả mức độ</option>
                                <option value="Nhẹ" <?php echo $filter_mucdo == 'Nhẹ' ? 'selected' : ''; ?>>Nhẹ</option>
                                <option value="Vừa" <?php echo $filter_mucdo == 'Vừa' ? 'selected' : ''; ?>>Vừa</option>
                                <option value="Nặng" <?php echo $filter_mucdo == 'Nặng' ? 'selected' : ''; ?>>Nặng</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_trangthai">Trạng thái xử lý:</label>
                            <select id="filter_trangthai" name="trangthai" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter_trangthai == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="Đã xử lý" <?php echo $filter_trangthai == 'Đã xử lý' ? 'selected' : ''; ?>>Đã xử lý</option>
                                <option value="Chưa xử lý" <?php echo $filter_trangthai == 'Chưa xử lý' ? 'selected' : ''; ?>>Chưa xử lý</option>
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
                    <canvas id="severityChart"></canvas>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-container">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã sinh viên</th>
                            <th>Tên sinh viên</th>
                            <th>Ngày vi phạm</th>
                            <th>Mức độ vi phạm</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $stt++; ?></td>
                                    <td><?php echo htmlspecialchars($row['masinhvien']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tensinhvien']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['ngayvipham'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php 
                                            if ($row['mucdovipham'] == 'Nhẹ') echo 'light';
                                            elseif ($row['mucdovipham'] == 'Vừa') echo 'medium';
                                            else echo 'heavy';
                                        ?>">
                                            <?php echo htmlspecialchars($row['mucdovipham']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $row['trangthai'] == 'Đã xử lý' ? 'resolved' : 'pending'; ?>">
                                            <?php echo htmlspecialchars($row['trangthai']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center;">Không có dữ liệu vi phạm</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout = null;

        function resetFilters() {
            window.location.href = 'tkvp.php';
        }

        function exportToExcel() {
            let url = 'tkvp.php?action=export_excel';
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
            // Chart 1: Mức độ vi phạm
            const severityCtx = document.getElementById('severityChart').getContext('2d');
            const severityChart = new Chart(severityCtx, {
                type: 'pie',
                data: {
                    labels: ['Vi phạm nhẹ', 'Vi phạm vừa', 'Vi phạm nặng'],
                    datasets: [{
                        data: [
                            <?php echo $stats['vipham_nhe']; ?>, 
                            <?php echo $stats['vipham_vua']; ?>, 
                            <?php echo $stats['vipham_nang']; ?>
                        ],
                        backgroundColor: ['#f39c12', '#e74c3c', '#c0392b'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { 
                            display: true, 
                            text: 'Phân bố theo mức độ vi phạm',
                            font: { size: 16 }
                        }
                    }
                }
            });

            // Chart 2: Trạng thái xử lý
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: ['Đã xử lý', 'Chưa xử lý'],
                    datasets: [{
                        label: 'Số lượng vi phạm',
                        data: [<?php echo $stats['vipham_daxuly']; ?>, <?php echo $stats['vipham_chuaxuly']; ?>],
                        backgroundColor: ['#27ae60', '#3498db'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { 
                            display: true, 
                            text: 'Trạng thái xử lý vi phạm',
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