<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// Kiểm tra kết nối database
if (!isset($conn)) {
    die("Lỗi: Không thể kết nối đến database. Vui lòng kiểm tra file config.php");
}

// Biến thông báo toast
$toast_message = '';
$toast_type = '';

// Xử lý API lấy chi tiết vi phạm
if (isset($_GET['action']) && $_GET['action'] == 'get_vipham_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $id = intval($_GET['id']);
    
    try {
        $sql = "SELECT * FROM vipham WHERE idvipham = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $vipham = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'masinhvien' => $vipham['masinhvien'],
                'tensinhvien' => $vipham['tensinhvien'],
                'ngayvipham' => $vipham['ngayvipham'],
                'mucdovipham' => $vipham['mucdovipham'],
                'trangthai' => $vipham['trangthai']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Không tìm thấy vi phạm']);
        }
        $stmt->close();
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Lỗi truy vấn: ' . $e->getMessage()]);
    }
    exit;
}

// Xử lý tìm kiếm và lọc
$search_keyword = $_GET['search'] ?? '';
$trang_thai = $_GET['trang_thai'] ?? '';
$muc_do = $_GET['muc_do'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Xử lý thêm vi phạm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vipham'])) {
    try {
        // Kiểm tra và lấy dữ liệu từ POST
        $masinhvien = isset($_POST['masinhvien']) ? trim($_POST['masinhvien']) : '';
        $tensinhvien = isset($_POST['tensinhvien']) ? trim($_POST['tensinhvien']) : '';
        $ngayvipham = isset($_POST['ngayvipham']) ? trim($_POST['ngayvipham']) : '';
        $mucdovipham = isset($_POST['mucdovipham']) ? trim($_POST['mucdovipham']) : '';
        $trangthai = isset($_POST['trangthai']) ? trim($_POST['trangthai']) : '';
        
        // Kiểm tra dữ liệu bắt buộc
        if (empty($masinhvien) || empty($tensinhvien) || empty($ngayvipham) || empty($mucdovipham)) {
            throw new Exception("Vui lòng điền đầy đủ thông tin bắt buộc");
        }
        
        $insert_sql = "INSERT INTO vipham (masinhvien, tensinhvien, ngayvipham, mucdovipham, trangthai) 
                      VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssss", $masinhvien, $tensinhvien, $ngayvipham, $mucdovipham, $trangthai);
        
        if ($insert_stmt->execute()) {
            $_SESSION['toast_message'] = "Thêm vi phạm thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi thêm vi phạm: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $insert_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi thêm vi phạm: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý xóa vi phạm
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $delete_sql = "DELETE FROM vipham WHERE idvipham = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa vi phạm thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi xóa vi phạm: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi xóa vi phạm: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Xử lý cập nhật vi phạm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vipham'])) {
    try {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $masinhvien = isset($_POST['masinhvien']) ? trim($_POST['masinhvien']) : '';
        $tensinhvien = isset($_POST['tensinhvien']) ? trim($_POST['tensinhvien']) : '';
        $ngayvipham = isset($_POST['ngayvipham']) ? trim($_POST['ngayvipham']) : '';
        $mucdovipham = isset($_POST['mucdovipham']) ? trim($_POST['mucdovipham']) : '';
        $trangthai = isset($_POST['trangthai']) ? trim($_POST['trangthai']) : '';
        
        // Kiểm tra dữ liệu bắt buộc
        if (empty($id) || empty($masinhvien) || empty($tensinhvien) || empty($ngayvipham) || empty($mucdovipham)) {
            throw new Exception("Vui lòng điền đầy đủ thông tin bắt buộc");
        }
        
        $update_sql = "UPDATE vipham SET masinhvien = ?, tensinhvien = ?, ngayvipham = ?, 
                      mucdovipham = ?, trangthai = ? WHERE idvipham = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssi", $masinhvien, $tensinhvien, $ngayvipham, $mucdovipham, $trangthai, $id);
        
        if ($update_stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật vi phạm thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi cập nhật vi phạm: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $update_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi cập nhật vi phạm: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Lấy danh sách vi phạm từ database với các điều kiện lọc
$sql = "SELECT * FROM vipham WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    // Tìm kiếm tất cả các trường: masinhvien, tensinhvien, mucdovipham, trangthai
    $sql .= " AND (masinhvien LIKE ? OR tensinhvien LIKE ? OR mucdovipham LIKE ? OR trangthai LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($trang_thai)) {
    $sql .= " AND trangthai = ?";
    $params[] = $trang_thai;
    $types .= "s";
}

if (!empty($muc_do)) {
    $sql .= " AND mucdovipham = ?";
    $params[] = $muc_do;
    $types .= "s";
}

if (!empty($from_date)) {
    $sql .= " AND ngayvipham >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if (!empty($to_date)) {
    $sql .= " AND ngayvipham <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY idvipham DESC";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $vipham_list = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        $vipham_list = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch(Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Hiển thị thông báo toast nếu có
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý vi phạm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="noiquy.css">
</head>
<body>
    <div class="page">
        <!-- Toast Notification -->
        <?php if ($toast_message): ?>
        <div class="toast <?= $toast_type ?>" id="toast">
            <div class="toast-icon">
                <?php if ($toast_type == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php elseif ($toast_type == 'error'): ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php else: ?>
                    <i class="fas fa-info-circle"></i>
                <?php endif; ?>
            </div>
            <div class="toast-content">
                <div class="toast-title">
                    <?php 
                    if ($toast_type == 'success') echo 'Thành công!';
                    elseif ($toast_type == 'error') echo 'Lỗi!';
                    else echo 'Thông báo!';
                    ?>
                </div>
                <div class="toast-message"><?= htmlspecialchars($toast_message) ?></div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toast = document.getElementById('toast');
                if (toast) {
                    setTimeout(() => toast.classList.add('show'), 100);
                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }, 5000);
                }
            });
        </script>
        <?php endif; ?>

        <h1>Quản lý vi phạm</h1>
        <div class="tab-menu">
            <a href="vipham.php">
                <button class="tab-btn active">Lịch sử vi phạm</button>
            </a>
            <a href="noiquy.php">
                <button class="tab-btn">Nội quy ký túc xá</button>
            </a>
        </div>

        <form method="GET" action="" class="controls" id="filterForm">
            <div class="filter-section">
                <!-- Filter Trạng thái -->
                <div class="filter-group">
                    <label>Trạng thái:</label>
                    <select name="trang_thai" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="Đã xử lý" <?= $trang_thai == 'Đã xử lý' ? 'selected' : '' ?>>Đã xử lý</option>
                        <option value="Chưa xử lý" <?= $trang_thai == 'Chưa xử lý' ? 'selected' : '' ?>>Chưa xử lý</option>
                    </select>
                </div>

                <!-- Filter Mức độ vi phạm -->
                <div class="filter-group">
                    <label>Mức độ vi phạm:</label>
                    <select name="muc_do" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả mức độ</option>
                        <option value="Nhe" <?= $muc_do == 'Nhe' ? 'selected' : '' ?>>Nhẹ</option>
                        <option value="Vừa" <?= $muc_do == 'Vừa' ? 'selected' : '' ?>>Vừa</option>
                        <option value="Nặng" <?= $muc_do == 'Nặng' ? 'selected' : '' ?>>Nặng</option>
                    </select>
                </div>

                <div class="filter-date">
                    <label for="from-date">Từ ngày:</label>
                    <input type="date" name="from_date" id="from-date" value="<?= $from_date ?>">
                </div>

                <div class="filter-date">
                    <label for="to-date">Đến ngày:</label>
                    <input type="date" name="to_date" id="to-date" value="<?= $to_date ?>">
                </div>

                <div class="filter-date">
                    <button type="submit" class="filter-btn"><i class="fa fa-filter"></i> Lọc</button>
                </div>

                <div class="filter-date">
                    <button type="button" class="filter-btn" onclick="clearFilters()"><i class="fa fa-refresh"></i> Xóa bộ lọc</button>
                </div>

                <div class="search-section">
                    <label for="search">Tìm kiếm:</label>
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Tìm kiếm thông tin" 
                               class="search-input" id="searchInput" 
                               value="<?= htmlspecialchars($search_keyword) ?>" 
                               oninput="handleSearchInput()">
                        <div class="search-loading" id="searchLoading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <?php if (!empty($search_keyword)): ?>
                        <button type="button" class="btn-clear-search" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="filter-group">
                    <button type="button" class="status-btn" onclick="showAddForm()"><i class="fas fa-plus-circle"></i>Thêm vi phạm</button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã sinh viên</th>
                        <th>Tên sinh viên</th>
                        <th>Ngày vi phạm</th>
                        <th>Mức độ vi phạm</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vipham_list)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Không có dữ liệu vi phạm</td>
                    </tr>
                    <?php else: ?>
                    <?php $stt = 1; ?>
                    <?php foreach ($vipham_list as $vipham): ?>
                    <tr data-id="<?= $vipham['idvipham'] ?>">
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($vipham['masinhvien']) ?></td>
                        <td><?= htmlspecialchars($vipham['tensinhvien']) ?></td>
                        <td><?= date('d/m/Y', strtotime($vipham['ngayvipham'])) ?></td>
                        <td>
                            <?php 
                                // Sửa logic xác định class cho mức độ vi phạm
                                $mucdo = $vipham['mucdovipham'];
                                $mucdo_class = 'error'; // Mặc định là màu đỏ
                                
                                if ($mucdo == 'NHẸ' || $mucdo == 'Nhe' || $mucdo == 'Nhẹ') {
                                    $mucdo_class = 'success'; // Màu xanh cho NHẸ
                                } elseif ($mucdo == 'VỪA' || $mucdo == 'Vừa' || $mucdo == 'Vua') {
                                    $mucdo_class = 'warning'; // Màu vàng cho VỪA
                                } elseif ($mucdo == 'NẶNG' || $mucdo == 'Nặng' || $mucdo == 'Nang') {
                                    $mucdo_class = 'error'; // Màu đỏ cho NẶNG
                                }
                                ?>
                                <span class="status-badge <?= $mucdo_class ?>">
                                    <?= $vipham['mucdovipham'] ?>
                                </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $vipham['trangthai'] == 'Đã xử lý' ? 'status-processed' : 'status-pending' ?>">
                                <?= $vipham['trangthai'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewVipham(<?= $vipham['idvipham'] ?>)">Xem</button>
                                <button class="action-btn edit" onclick="editVipham(<?= $vipham['idvipham'] ?>)">Sửa</button>
                                <button class="action-btn delete" onclick="deleteVipham(<?= $vipham['idvipham'] ?>)">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal form thêm/sửa vi phạm -->
    <div id="viphamModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Thêm vi phạm</h2>
            <form method="POST" action="" id="viphamForm">
                <input type="hidden" name="id" id="viphamId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mã sinh viên:</label>
                        <input type="text" name="masinhvien" id="masinhvienInput" required>
                    </div>
                    <div class="form-group">
                        <label>Tên sinh viên:</label>
                        <input type="text" name="tensinhvien" id="tensinhvienInput" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày vi phạm:</label>
                        <input type="date" name="ngayvipham" id="ngayviphamInput" required>
                    </div>
                    <div class="form-group">
                        <label>Mức độ vi phạm:</label>
                        <select name="mucdovipham" id="mucdoviphamInput" required>
                            <option value="Nhe">Nhẹ</option>
                            <option value="Vừa">Vừa</option>
                            <option value="Nặng">Nặng</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Trạng thái:</label>
                        <select name="trangthai" id="trangthaiInput" required>
                            <option value="Chưa xử lý">Chưa xử lý</option>
                            <option value="Đã xử lý">Đã xử lý</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()">Hủy</button>
                    <button type="submit" name="update_vipham" id="updateBtn" style="display:none;">Cập nhật</button>
                    <button type="submit" name="add_vipham" id="addBtn">Thêm vi phạm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let searchTimeout = null;
    let isSearching = false;

    function clearFilters() {
        window.location.href = 'vipham.php';
    }

    function clearSearch() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterForm').submit();
    }

    // Xử lý tìm kiếm real-time với delay 2 giây
    function handleSearchInput() {
        const searchInput = document.getElementById('searchInput');
        const searchValue = searchInput.value.trim();
        const searchLoading = document.getElementById('searchLoading');
        
        // Hiển thị loading khi bắt đầu nháp
        if (searchValue !== '' && !isSearching) {
            searchLoading.style.display = 'block';
            isSearching = true;
        }
        
        // Xóa timeout cũ nếu có
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Nếu xóa hết nội dung, tìm kiếm ngay lập tức
        if (searchValue === '') {
            searchLoading.style.display = 'none';
            isSearching = false;
            document.getElementById('filterForm').submit();
            return;
        }
        
        // Đặt timeout 2 giây cho tìm kiếm
        searchTimeout = setTimeout(() => {
            performSearch();
        }, 2000);
    }

    function performSearch() {
        const searchLoading = document.getElementById('searchLoading');
        
        // Ẩn loading khi hoàn thành
        searchLoading.style.display = 'none';
        isSearching = false;
        
        // Submit form để thực hiện tìm kiếm
        document.getElementById('filterForm').submit();
    }

    // Xử lý sự kiện phím Enter - tìm kiếm ngay lập tức
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            // Hủy timeout nếu có
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            // Thực hiện tìm kiếm ngay lập tức
            performSearch();
            e.preventDefault();
        }
    });

    function showAddForm() {
        document.getElementById('modalTitle').textContent = 'Thêm vi phạm';
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('viphamModal').style.display = 'block';
        document.getElementById('viphamForm').reset();
        document.getElementById('viphamId').value = '';
        // Set ngày mặc định là hôm nay
        document.getElementById('ngayviphamInput').valueAsDate = new Date();
    }

    function editVipham(id) {
        document.getElementById('modalTitle').textContent = 'Sửa vi phạm';
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('updateBtn').style.display = 'block';
        document.getElementById('viphamModal').style.display = 'block';
        
        // Hiển thị loading state
        document.getElementById('masinhvienInput').value = 'Đang tải...';
        document.getElementById('tensinhvienInput').value = 'Đang tải...';
        document.getElementById('ngayviphamInput').value = '';
        document.getElementById('mucdoviphamInput').value = 'Nhe';
        document.getElementById('trangthaiInput').value = 'Chưa xử lý';
        
        // Gửi AJAX request để lấy thông tin vi phạm
        fetch(`?action=get_vipham_details&id=${id}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Lỗi kết nối');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('viphamId').value = id;
                    document.getElementById('masinhvienInput').value = data.masinhvien || '';
                    document.getElementById('tensinhvienInput').value = data.tensinhvien || '';
                    document.getElementById('ngayviphamInput').value = data.ngayvipham || '';
                    document.getElementById('mucdoviphamInput').value = data.mucdovipham || 'Nhe';
                    document.getElementById('trangthaiInput').value = data.trangthai || 'Chưa xử lý';
                } else {
                    throw new Error(data.error || 'Lỗi không xác định');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Lỗi khi tải thông tin vi phạm: ' . error.message);
                closeModal();
            });
    }

    function viewVipham(id) {
        // Chuyển đến trang chi tiết hoặc hiển thị modal xem
        alert('Xem chi tiết vi phạm ID: ' + id);
        // window.location.href = 'chitiet_vipham.php?id=' + id;
    }

    function deleteVipham(id) {
        if (confirm('Bạn có chắc muốn xóa vi phạm này?')) {
            window.location.href = '?delete_id=' + id;
        }
    }

    function closeModal() {
        document.getElementById('viphamModal').style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const modal = document.getElementById('viphamModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>