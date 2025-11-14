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

// Xử lý tìm kiếm
$search_keyword = $_GET['search'] ?? '';

// Lấy danh sách khoản thu từ database
$sql = "SELECT * FROM khoanthu WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    $search_param = "%$search_keyword%";
    $sql .= " AND (khoanthu LIKE ? OR mota LIKE ? OR giatrikhoanthu LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY idkhoanthu ASC";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $khoanthu_list = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        $khoanthu_list = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch(Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Xử lý thêm khoản thu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_khoanthu'])) {
    try {
        $khoanthu = $_POST['khoanthu'];
        $giatrikhoanthu = $_POST['giatrikhoanthu'];
        $mota = $_POST['mota'];
        
        $insert_sql = "INSERT INTO khoanthu (khoanthu, giatrikhoanthu, mota) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $khoanthu, $giatrikhoanthu, $mota);
        
        if ($insert_stmt->execute()) {
            $_SESSION['toast_message'] = "Thêm khoản thu thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi thêm khoản thu: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $insert_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi thêm khoản thu: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý xóa khoản thu
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $delete_sql = "DELETE FROM khoanthu WHERE idkhoanthu = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa khoản thu thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi xóa khoản thu: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi xóa khoản thu: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Xử lý cập nhật khoản thu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_khoanthu'])) {
    try {
        $id = $_POST['id'];
        $khoanthu = $_POST['khoanthu'];
        $giatrikhoanthu = $_POST['giatrikhoanthu'];
        $mota = $_POST['mota'];
        
        $update_sql = "UPDATE khoanthu SET khoanthu = ?, giatrikhoanthu = ?, mota = ? WHERE idkhoanthu = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $khoanthu, $giatrikhoanthu, $mota, $id);
        
        if ($update_stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật khoản thu thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi cập nhật khoản thu: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $update_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi cập nhật khoản thu: " . $e->getMessage();
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
    <title>Danh sách khoản thu</title>
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
                <button class="tab-btn">Danh sách giao dịch</button>
            </a>
            <a href="khoanthu.php">
                <button class="tab-btn active">Danh sách khoản thu</button>
            </a>
        </div>

        <form method="GET" action="" class="controls" id="filterForm">
            <div class="filter-section">
                <div class="filter-group">
                    <label>Tìm kiếm:</label>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Tìm kiếm thông tin" class="search-input" 
                               value="<?= htmlspecialchars($search_keyword) ?>" id="searchInput" oninput="handleSearchInput()">
                        
                        <button type="button" class="clear-search-btn" id="clearSearchBtn" onclick="clearSearch()" style="display: <?= $search_keyword ? 'flex' : 'none' ?>;">
                            <i class="fas fa-times"></i>
                        </button>

                        <div class="search-loading" id="searchLoading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
                <div class="filter-group">
                    <button type="button" class="status-btn" onclick="showAddForm()">
                        <i class="fas fa-plus-circle"></i> Thêm khoản thu mới
                    </button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Khoản thu</th>
                        <th>Giá trị khoản thu</th>
                        <th>Mô tả</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($khoanthu_list)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Không có dữ liệu khoản thu</td>
                    </tr>
                    <?php else: ?>
                    <?php $stt = 1; ?>
                    <?php foreach ($khoanthu_list as $khoanthu): ?>
                    <tr data-id="<?= $khoanthu['idkhoanthu'] ?>">
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($khoanthu['khoanthu']) ?></td>
                        <td><?= htmlspecialchars($khoanthu['giatrikhoanthu']) ?></td>
                        <td><?= htmlspecialchars($khoanthu['mota']) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editKhoanThu(<?= $khoanthu['idkhoanthu'] ?>)">Sửa</button>
                                <button class="action-btn delete" onclick="deleteKhoanThu(<?= $khoanthu['idkhoanthu'] ?>)">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal form thêm/sửa khoản thu -->
    <div id="khoanthuModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Thêm khoản thu</h2>
            <form method="POST" action="" id="khoanthuForm">
                <input type="hidden" name="id" id="khoanthuId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Khoản thu:</label>
                        <input type="text" name="khoanthu" id="khoanthuInput" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giá trị khoản thu:</label>
                        <input type="text" name="giatrikhoanthu" id="giatrikhoanthuInput" required placeholder="VD: 500.000đ / tháng">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Mô tả:</label>
                        <textarea name="mota" id="motaInput" required rows="3" placeholder="Mô tả chi tiết về khoản thu"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()">Hủy</button>
                    <button type="submit" name="update_khoanthu" id="updateBtn" style="display:none;">Cập nhật</button>
                    <button type="submit" name="add_khoanthu" id="addBtn">Thêm khoản thu</button>
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
        document.getElementById('modalTitle').textContent = 'Thêm khoản thu';
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('khoanthuModal').style.display = 'block';
        document.getElementById('khoanthuForm').reset();
        document.getElementById('khoanthuId').value = '';
    }

    function editKhoanThu(id) {
    document.getElementById('modalTitle').textContent = 'Sửa khoản thu';
    document.getElementById('addBtn').style.display = 'none';
    document.getElementById('updateBtn').style.display = 'block';
    document.getElementById('khoanthuModal').style.display = 'block';
    
    // Tìm row trong table
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
        const cells = row.querySelectorAll('td');
        document.getElementById('khoanthuId').value = id;
        document.getElementById('khoanthuInput').value = cells[1].textContent.trim();
        document.getElementById('giatrikhoanthuInput').value = cells[2].textContent.trim();
        document.getElementById('motaInput').value = cells[3].textContent.trim();
    } else {
        alert('Không tìm thấy thông tin khoản thu');
        closeModal();
    }
}

    function deleteKhoanThu(id) {
        if (confirm('Bạn có chắc muốn xóa khoản thu này?')) {
            window.location.href = '?delete_id=' + id;
        }
    }

    function closeModal() {
        document.getElementById('khoanthuModal').style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const modal = document.getElementById('khoanthuModal');
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