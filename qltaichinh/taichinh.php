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

// Xử lý các tham số filter
$loaigiaodich_filter = $_GET['loaigiaodich'] ?? '';
$trangthai_filter = $_GET['trangthai'] ?? '';
$search_keyword = $_GET['search'] ?? '';

// Xây dựng câu truy vấn với bảng taichinh
$sql = "SELECT * FROM taichinh WHERE 1=1";
$params = [];
$types = "";

if (!empty($loaigiaodich_filter) && $loaigiaodich_filter != 'Tất cả') {
    $sql .= " AND loaigiaodich = ?";
    $params[] = $loaigiaodich_filter;
    $types .= "s";
}

if (!empty($trangthai_filter) && $trangthai_filter != 'Tất cả') {
    $sql .= " AND trangthai = ?";
    $params[] = $trangthai_filter;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $search_param = "%$search_keyword%";
    $sql .= " AND (masinhvien LIKE ? OR tensinhvien LIKE ? OR magiaodich LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Thêm ORDER BY để sắp xếp kết quả
$sql .= " ORDER BY idtaichinh DESC";

try {
    // Thực thi truy vấn với MySQLi
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        $transactions = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch(Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Xử lý thêm giao dịch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    try {
        $magiaodich = $_POST['magiaodich'];
        $masinhvien = $_POST['masinhvien'];
        $tensinhvien = $_POST['tensinhvien'];
        $loaigiaodich = $_POST['loaigiaodich'];
        $sotien = $_POST['sotien'];
        $ngaygiaodich = $_POST['ngaygiaodich'];
        $trangthai = $_POST['trangthai'] ?? 'Chưa thanh toán';
        
        $insert_sql = "INSERT INTO taichinh (magiaodich, masinhvien, tensinhvien, loaigiaodich, sotien, ngaygiaodich, trangthai) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssdss", $magiaodich, $masinhvien, $tensinhvien, $loaigiaodich, $sotien, $ngaygiaodich, $trangthai);
        
        if ($insert_stmt->execute()) {
            $_SESSION['toast_message'] = "Thêm giao dịch thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi thêm giao dịch: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $insert_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi thêm giao dịch: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý xóa giao dịch
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $delete_sql = "DELETE FROM taichinh WHERE idtaichinh = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa giao dịch thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi xóa giao dịch: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi xóa giao dịch: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Xử lý cập nhật giao dịch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction'])) {
    try {
        $id = $_POST['id'];
        $magiaodich = $_POST['magiaodich'];
        $masinhvien = $_POST['masinhvien'];
        $tensinhvien = $_POST['tensinhvien'];
        $loaigiaodich = $_POST['loaigiaodich'];
        $sotien = $_POST['sotien'];
        $ngaygiaodich = $_POST['ngaygiaodich'];
        $trangthai = $_POST['trangthai'];
        
        $update_sql = "UPDATE taichinh SET masinhvien = ?, tensinhvien = ?, 
                       loaigiaodich = ?, sotien = ?, ngaygiaodich = ?, trangthai = ? WHERE idtaichinh = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssdssi", $masinhvien, $tensinhvien, $loaigiaodich, $sotien, $ngaygiaodich, $trangthai, $id);
        
        if ($update_stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật giao dịch thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi cập nhật giao dịch: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $update_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi cập nhật giao dịch: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
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
    <title>Quản lý tài chính</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="taichinh.css">
</head>
<body>
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

    <div class="finance-page">
        <h1>Quản lý tài chính</h1>
        <div class="tab-menu">
            <a href="taichinh.php">
                <button class="tab-btn active">Danh sách giao dịch</button>
            </a>
            <a href="khoanthu.php">
                <button class="tab-btn">Danh sách khoản thu</button>
            </a>
        </div>

        <form method="GET" action="" class="controls" id="filterForm">
            <div class="filter-section">
                <div class="filter-group">
                    <label>Loại giao dịch:</label>
                    <select name="loaigiaodich" class="filter-select" onchange="this.form.submit()">
                        <option value="Tất cả">Tất cả</option>
                        <option value="Tiền phòng" <?= ($loaigiaodich_filter == 'Tiền phòng') ? 'selected' : '' ?>>Tiền phòng</option>
                        <option value="Tiền điện, nước" <?= ($loaigiaodich_filter == 'Tiền điện, nước') ? 'selected' : '' ?>>Tiền điện, nước</option>
                        <option value="Tiền Internet/Wi-Fi" <?= ($loaigiaodich_filter == 'Tiền Internet/Wi-Fi') ? 'selected' : '' ?>>Tiền Internet/Wi-Fi</option>
                        <option value="Tiền vệ sinh, rác" <?= ($loaigiaodich_filter == 'Tiền vệ sinh, rác') ? 'selected' : '' ?>>Tiền vệ sinh, rác</option>
                        <option value="Tài sản, thiết bị" <?= ($loaigiaodich_filter == 'Tài sản, thiết bị') ? 'selected' : '' ?>>Tài sản, thiết bị</option>
                        <option value="Tiền đặt cọc" <?= ($loaigiaodich_filter == 'Tiền đặt cọc') ? 'selected' : '' ?>>Tiền đặt cọc</option>
                        <option value="Khác" <?= ($loaigiaodich_filter == 'Khác') ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Trạng thái:</label>
                    <select name="trangthai" class="filter-select" onchange="this.form.submit()">
                        <option value="Tất cả">Tất cả</option>
                        <option value="Đã thanh toán" <?= ($trangthai_filter == 'Đã thanh toán') ? 'selected' : '' ?>>Đã thanh toán</option>
                        <option value="Chưa thanh toán" <?= ($trangthai_filter == 'Chưa thanh toán') ? 'selected' : '' ?>>Chưa thanh toán</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Tìm kiếm:</label>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Tìm kiếm thông tin" class="search-input" 
                            value="<?= htmlspecialchars($search_keyword) ?>" id="searchInput" oninput="handleSearchInput()">
                        
                        <!-- Nút Xóa tìm kiếm -->
                        <button type="button" class="clear-search-btn" id="clearSearchBtn" onclick="clearSearch()" style="display: <?= $search_keyword ? 'flex' : 'none' ?>;">
                            <i class="fas fa-times"></i>
                        </button>

                        <!-- Loading spinner -->
                        <div class="search-loading" id="searchLoading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <button type="button" class="status-btn" onclick="showAddForm()">
                        <i class="fas fa-plus-circle"></i> Thêm giao dịch
                    </button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã giao dịch</th>
                        <th>Mã sinh viên</th>
                        <th>Tên sinh viên</th>
                        <th>Loại giao dịch</th>
                        <th>Ngày giao dịch</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">Không có dữ liệu giao dịch</td>
                    </tr>
                    <?php else: ?>
                    <?php $stt = 1; ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr data-id="<?= $transaction['idtaichinh'] ?>" data-ngaygiaodich="<?= $transaction['ngaygiaodich'] ?>">
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($transaction['magiaodich']) ?></td>
                        <td><?= htmlspecialchars($transaction['masinhvien']) ?></td>
                        <td><?= htmlspecialchars($transaction['tensinhvien']) ?></td>
                        <td><?= htmlspecialchars($transaction['loaigiaodich']) ?></td>
                        <td><?= htmlspecialchars($transaction['ngaygiaodich']) ?></td>
                        <td>
                            <span class="status-badge <?= $transaction['trangthai'] == 'Đã thanh toán' ? 'success' : 'pending' ?>">
                                <?= htmlspecialchars($transaction['trangthai']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewTransaction(<?= $transaction['idtaichinh'] ?>)">Xem</button>
                                <button class="action-btn edit" onclick="editTransaction(<?= $transaction['idtaichinh'] ?>)">Sửa</button>
                                <button class="action-btn delete" onclick="deleteTransaction(<?= $transaction['idtaichinh'] ?>)">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal form thêm/sửa giao dịch -->
    <div id="transactionModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Thêm giao dịch</h2>
            <form method="POST" action="" id="transactionForm">
                <input type="hidden" name="id" id="transactionId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mã giao dịch:</label>
                        <input type="text" name="magiaodich" id="magiaodich" required <?php echo isset($_POST['update_transaction']) ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label>Mã sinh viên:</label>
                        <input type="text" name="masinhvien" id="masinhvien" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tên sinh viên:</label>
                        <input type="text" name="tensinhvien" id="tensinhvien" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Loại giao dịch:</label>
                        <select name="loaigiaodich" id="loaigiaodich" required>
                            <option value="Tiền phòng">Tiền phòng</option>
                            <option value="Tiền điện, nước">Tiền điện, nước</option>
                            <option value="Tiền Internet/Wi-Fi">Tiền Internet/Wi-Fi</option>
                            <option value="Tiền vệ sinh, rác">Tiền vệ sinh, rác</option>
                            <option value="Tài sản, thiết bị">Tài sản, thiết bị</option>
                            <option value="Tiền đặt cọc">Tiền đặt cọc</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Số tiền (VNĐ):</label>
                        <input type="number" name="sotien" id="sotien" required min="0" step="1000">
                    </div>
                    
                    <div class="form-group">
                        <label>Ngày giao dịch:</label>
                        <input type="date" name="ngaygiaodich" id="ngaygiaodich" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Trạng thái:</label>
                        <select name="trangthai" id="trangthaiSelect">
                            <option value="Chưa thanh toán">Chưa thanh toán</option>
                            <option value="Đã thanh toán">Đã thanh toán</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()">Hủy</button>
                    <button type="submit" name="update_transaction" id="updateBtn" style="display:none;">Cập nhật</button>
                    <button type="submit" name="add_transaction" id="addBtn">Thêm giao dịch</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let searchTimeout = null;

    // Xử lý tìm kiếm real-time với delay 2s
    function handleSearchInput() {
    const searchInput = document.getElementById('searchInput');
    const searchLoading = document.getElementById('searchLoading');
    const clearBtn = document.getElementById('clearSearchBtn');
    const searchValue = searchInput.value.trim();
    
    // Hiển thị loading
    searchLoading.style.display = 'block';
    clearBtn.style.display = searchValue ? 'flex' : 'none';
    
    // Xóa timeout cũ nếu có
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Đặt timeout 2 giây
    searchTimeout = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 2000);
}

    function showAddForm() {
        document.getElementById('modalTitle').textContent = 'Thêm giao dịch';
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('transactionModal').style.display = 'block';
        document.getElementById('transactionForm').reset();
        document.getElementById('transactionId').value = '';
        
        // Bỏ readonly cho mã giao dịch khi thêm mới
        document.getElementById('magiaodich').readOnly = false;
        
        // Đặt ngày giao dịch là ngày hiện tại
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('ngaygiaodich').value = today;
        
        // Tạo mã giao dịch tự động (8 ký tự thay vì 6)
        const timestamp = new Date().getTime().toString();
        document.getElementById('magiaodich').value = 'GD' + timestamp.slice(-8);
    }

    function editTransaction(id) {
        document.getElementById('modalTitle').textContent = 'Sửa giao dịch';
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('updateBtn').style.display = 'block';
        document.getElementById('transactionModal').style.display = 'block';
        
        const row = document.querySelector(`tr[data-id="${id}"]`);
        
        if (row) {
            const cells = row.querySelectorAll('td');
            
            document.getElementById('transactionId').value = id;
            document.getElementById('magiaodich').value = cells[1].textContent.trim(); // STT là cột 0, Mã GD là cột 1
            // Đặt readonly cho mã giao dịch khi sửa
            document.getElementById('magiaodich').readOnly = true;
            document.getElementById('masinhvien').value = cells[2].textContent.trim(); // Mã SV là cột 2
            document.getElementById('tensinhvien').value = cells[3].textContent.trim(); // Tên SV là cột 3
            document.getElementById('loaigiaodich').value = cells[4].textContent.trim(); // Loại GD là cột 4
            document.getElementById('ngaygiaodich').value = row.getAttribute('data-ngaygiaodich');
            
            const statusBadge = cells[6].querySelector('.status-badge'); // Trạng thái là cột 6
            document.getElementById('trangthaiSelect').value = statusBadge.textContent.trim();
        }
    }

    function viewTransaction(id) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        
        if (row) {
            const cells = row.querySelectorAll('td');
            const transactionInfo = {
                magiaodich: cells[1].textContent.trim(), // Mã GD là cột 1
                masinhvien: cells[2].textContent.trim(), // Mã SV là cột 2
                tensinhvien: cells[3].textContent.trim(), // Tên SV là cột 3
                loaigiaodich: cells[4].textContent.trim(), // Loại GD là cột 4
                ngaygiaodich: row.getAttribute('data-ngaygiaodich'),
                trangthai: cells[6].querySelector('.status-badge').textContent.trim() // Trạng thái là cột 6
            };
            
            alert(`Thông tin giao dịch:\n
                Mã GD: ${transactionInfo.magiaodich}
                Mã SV: ${transactionInfo.masinhvien}
                Tên SV: ${transactionInfo.tensinhvien}
                Loại: ${transactionInfo.loaigiaodich}
                Ngày GD: ${transactionInfo.ngaygiaodich}
                Trạng thái: ${transactionInfo.trangthai}`);
                }
            }

    function deleteTransaction(id) {
        if (confirm('Bạn có chắc muốn xóa giao dịch này?')) {
            window.location.href = '?delete_id=' + id;
        }
    }

    function closeModal() {
        document.getElementById('transactionModal').style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const modal = document.getElementById('transactionModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Xóa nội dung tìm kiếm và submit form
function clearSearch() {
    const searchInput = document.getElementById('searchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const searchLoading = document.getElementById('searchLoading');

    searchInput.value = '';
    clearBtn.style.display = 'none';
    searchLoading.style.display = 'block';

    // Submit form ngay lập tức
    document.getElementById('filterForm').submit();
}

// Hiển thị nút X khi có nội dung
document.getElementById('searchInput').addEventListener('input', function() {
    const clearBtn = document.getElementById('clearSearchBtn');
    if (this.value.trim()) {
        clearBtn.style.display = 'flex';
    } else {
        clearBtn.style.display = 'none';
    }
});
    </script>
</body>
</html>