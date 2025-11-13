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

// Xử lý thêm nội quy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_noiquy'])) {
    try {
        $noidungnoiquy = isset($_POST['noidungnoiquy']) ? trim($_POST['noidungnoiquy']) : '';
        $xulyvipham = isset($_POST['xulyvipham']) ? trim($_POST['xulyvipham']) : '';
        $ghichu = isset($_POST['ghichu']) ? trim($_POST['ghichu']) : '';
        
        // Kiểm tra dữ liệu bắt buộc
        if (empty($noidungnoiquy)) {
            throw new Exception("Vui lòng nhập nội dung nội quy");
        }
        
        $insert_sql = "INSERT INTO noiquy (noidungnoiquy, xulyvipham, ghichu) 
                      VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $noidungnoiquy, $xulyvipham, $ghichu);
        
        if ($insert_stmt->execute()) {
            $_SESSION['toast_message'] = "Thêm nội quy thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            throw new Exception("Lỗi khi thêm nội quy: " . $conn->error);
        }
        $insert_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý xóa nội quy
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $delete_sql = "DELETE FROM noiquy WHERE idnoiquy = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa nội quy thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            throw new Exception("Lỗi khi xóa nội quy: " . $conn->error);
        }
        $delete_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Xử lý cập nhật nội quy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_noiquy'])) {
    try {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $noidungnoiquy = isset($_POST['noidungnoiquy']) ? trim($_POST['noidungnoiquy']) : '';
        $xulyvipham = isset($_POST['xulyvipham']) ? trim($_POST['xulyvipham']) : '';
        $ghichu = isset($_POST['ghichu']) ? trim($_POST['ghichu']) : '';
        
        // Kiểm tra dữ liệu bắt buộc
        if (empty($id) || empty($noidungnoiquy)) {
            throw new Exception("Vui lòng nhập nội dung nội quy");
        }
        
        $update_sql = "UPDATE noiquy SET noidungnoiquy = ?, xulyvipham = ?, ghichu = ? 
                      WHERE idnoiquy = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssi", $noidungnoiquy, $xulyvipham, $ghichu, $id);
        
        if ($update_stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật nội quy thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            throw new Exception("Lỗi khi cập nhật nội quy: " . $conn->error);
        }
        $update_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý tìm kiếm
$search_keyword = $_GET['search'] ?? '';

// Lấy danh sách nội quy từ database
$sql = "SELECT * FROM noiquy WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_keyword)) {
    $sql .= " AND (noidungnoiquy LIKE ? OR xulyvipham LIKE ? OR ghichu LIKE ?)";
    $search_param = "%$search_keyword%";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss";
}

$sql .= " ORDER BY idnoiquy ASC";

try {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $noiquy_list = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        $noiquy_list = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Quản lý nội quy</title>
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
                <button class="tab-btn">Lịch sử vi phạm</button>
            </a>
            <a href="noiquy.php">
                <button class="tab-btn active">Nội quy ký túc xá</button>
            </a>
        </div>

        <form method="GET" action="" class="controls">
            <div class="filter-section">
                <div class="search-section">
                    <label for="search">Tìm kiếm:</label>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Tìm kiếm thông tin" class="search-input" id="searchInput" 
                                value="<?= htmlspecialchars($search_keyword) ?>" oninput="handleSearchInput()">
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
                    <button type="button" class="status-btn" onclick="showAddForm()">
                        <i class="fas fa-plus-circle"></i> Thêm nội quy
                    </button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Nội dung nội quy</th>
                        <th>Mức xử lý vi phạm</th>
                        <th>Ghi chú</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($noiquy_list)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Không có dữ liệu nội quy</td>
                    </tr>
                    <?php else: ?>
                    <?php $stt = 1; ?>
                    <?php foreach ($noiquy_list as $noiquy): ?>
                    <tr>
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($noiquy['noidungnoiquy']) ?></td>
                        <td><?= htmlspecialchars($noiquy['xulyvipham'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($noiquy['ghichu'] ?? '-') ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editNoiquy(<?= $noiquy['idnoiquy'] ?>)">Sửa</button>
                                <button class="action-btn delete" onclick="deleteNoiquy(<?= $noiquy['idnoiquy'] ?>)">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal form thêm/sửa nội quy -->
    <div id="noiquyModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Thêm nội quy</h2>
            <form method="POST" action="" id="noiquyForm">
                <input type="hidden" name="id" id="noiquyId">
                
                <div class="form-group">
                    <label>Nội dung nội quy:</label>
                    <textarea name="noidungnoiquy" id="noidungnoiquyInput" rows="3" required 
                              placeholder="Nhập nội dung nội quy..."></textarea>
                </div>

                <div class="form-group">
                    <label>Mức xử lý vi phạm:</label>
                    <input type="text" name="xulyvipham" id="xulyviphamInput" 
                           placeholder="Nhập mức xử lý vi phạm...">
                </div>

                <div class="form-group">
                    <label>Ghi chú:</label>
                    <input type="text" name="ghichu" id="ghichuInput" 
                           placeholder="Nhập ghi chú (nếu có)...">
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()">Hủy</button>
                    <button type="submit" name="update_noiquy" id="updateBtn" style="display:none;">Cập nhật</button>
                    <button type="submit" name="add_noiquy" id="addBtn">Thêm nội quy</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function clearSearch() {
        window.location.href = 'noiquy.php';
    }

    function showAddForm() {
        document.getElementById('modalTitle').textContent = 'Thêm nội quy';
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('noiquyModal').style.display = 'block';
        document.getElementById('noiquyForm').reset();
        document.getElementById('noiquyId').value = '';
    }

    function editNoiquy(id) {
        // Trong thực tế, bạn cần gọi API để lấy thông tin nội quy theo ID
        // Ở đây tôi sẽ hiển thị form với dữ liệu mẫu
        document.getElementById('modalTitle').textContent = 'Sửa nội quy';
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('updateBtn').style.display = 'block';
        document.getElementById('noiquyModal').style.display = 'block';
        
        // Đặt ID vào form
        document.getElementById('noiquyId').value = id;
        
        // Trong thực tế, bạn cần gọi AJAX để lấy dữ liệu từ server
        // Ở đây tôi chỉ hiển thị form trống
        document.getElementById('noidungnoiquyInput').value = '';
        document.getElementById('xulyviphamInput').value = '';
        document.getElementById('ghichuInput').value = '';
        
        alert('Chức năng sửa sẽ được triển khai với AJAX để lấy dữ liệu từ server');
    }

    function deleteNoiquy(id) {
        if (confirm('Bạn có chắc muốn xóa nội quy này?')) {
            window.location.href = '?delete_id=' + id;
        }
    }

    function closeModal() {
        document.getElementById('noiquyModal').style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const modal = document.getElementById('noiquyModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>