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
$trangthai_filter = $_GET['trangthai'] ?? '';
$search_keyword = $_GET['search'] ?? '';

// Xây dựng câu truy vấn đơn giản vì đã có sẵn toanha và phong trong bảng sinhvien
$sql = "SELECT * FROM sinhvien WHERE 1=1";
$params = [];
$types = "";

if (!empty($trangthai_filter) && $trangthai_filter != 'Tất cả') {
    $sql .= " AND trangthai = ?";
    $params[] = $trangthai_filter;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $sql .= " AND (masinhvien LIKE ? OR tensinhvien LIKE ? OR toanha LIKE ? OR phong LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Thêm ORDER BY để sắp xếp kết quả
$sql .= " ORDER BY idsinhvien DESC";

try {
    // Thực thi truy vấn với MySQLi
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        $students = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch(Exception $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

// Xử lý thêm sinh viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    try {
        $toanha = $_POST['toanha'];
        $phong = $_POST['phong'];
        $masinhvien = $_POST['masinhvien'];
        $tensinhvien = $_POST['tensinhvien'] ?? null;
        $ngayvao = $_POST['ngayvao'];
        $trangthai = $_POST['trangthai'] ?? 'Đang ở';
        
        // INSERT với đúng tên cột từ bảng sinhvien (đã bỏ ngaysinh)
        $insert_sql = "INSERT INTO sinhvien (toanha, phong, masinhvien, tensinhvien, ngayvao, trangthai) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssss", $toanha, $phong, $masinhvien, $tensinhvien, $ngayvao, $trangthai);
        
        if ($insert_stmt->execute()) {
            $_SESSION['toast_message'] = "Thêm sinh viên thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi thêm sinh viên: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $insert_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi thêm sinh viên: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Xử lý xóa sinh viên
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = $_GET['delete_id'];
        $delete_sql = "DELETE FROM sinhvien WHERE idsinhvien = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['toast_message'] = "Xóa sinh viên thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi xóa sinh viên: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $delete_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi xóa sinh viên: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Xử lý cập nhật sinh viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    try {
        $id = $_POST['id'];
        $toanha = $_POST['toanha'];
        $phong = $_POST['phong'];
        $masinhvien = $_POST['masinhvien'];
        $tensinhvien = $_POST['tensinhvien'] ?? null;
        $ngayvao = $_POST['ngayvao'];
        $trangthai = $_POST['trangthai'] ?? 'Đang ở';
        
        // UPDATE với đúng tên cột từ bảng sinhvien (đã bỏ ngaysinh)
        $update_sql = "UPDATE sinhvien SET toanha = ?, phong = ?, masinhvien = ?, tensinhvien = ?, 
                       ngayvao = ?, trangthai = ? WHERE idsinhvien = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssi", $toanha, $phong, $masinhvien, $tensinhvien, $ngayvao, $trangthai, $id);
        
        if ($update_stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật sinh viên thành công!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_message'] = "Lỗi khi cập nhật sinh viên: " . $conn->error;
            $_SESSION['toast_type'] = "error";
        }
        $update_stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } catch(Exception $e) {
        $_SESSION['toast_message'] = "Lỗi khi cập nhật sinh viên: " . $e->getMessage();
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
    <title>Danh sách sinh viên</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="sinhvien.css">
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
        // Hiển thị toast
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

    <div class="student-page">
        <div class="header">
            <h1>Danh sách sinh viên</h1>
        </div>

        <form method="GET" action="" class="controls" id="filterForm">
            <div class="filter-section">
                <div class="filter-group">
                    <label>Trạng thái:</label>
                    <select name="trangthai" class="filter-select" onchange="this.form.submit()">
                        <option value="Tất cả">Tất cả</option>
                        <option value="Đang ở" <?= ($trangthai_filter == 'Đang ở') ? 'selected' : '' ?>>Đang ở</option>
                        <option value="Đã rời đi" <?= ($trangthai_filter == 'Đã rời đi') ? 'selected' : '' ?>>Đã rời đi</option>
                    </select>
                </div>

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
                    <button type="button" class="status-btn" onclick="showAddForm()"><i class="fas fa-plus-circle"></i> Thêm sinh viên mới</button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã sinh viên</th>
                        <th>Tên sinh viên</th>
                        <th>Tòa nhà</th>
                        <th>Phòng</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Không có dữ liệu sinh viên</td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    $stt = 1;
                    foreach ($students as $student): 
                    ?>
                    <tr data-id="<?= $student['idsinhvien'] ?>" data-ngayvao="<?= $student['ngayvao'] ?>">
                        <td><?= $stt++ ?></td>
                        <td><?= htmlspecialchars($student['masinhvien']) ?></td>
                        <td><?= htmlspecialchars($student['tensinhvien'] ?? '') ?></td>
                        <td><?= htmlspecialchars($student['toanha']) ?></td>
                        <td><?= htmlspecialchars($student['phong']) ?></td>
                        <td>
                            <span class="status-badge <?= $student['trangthai'] == 'Đang ở' ? 'pending' : 'completed' ?>">
                                <?= htmlspecialchars($student['trangthai']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewStudent(<?= $student['idsinhvien'] ?>)">Xem</button>
                                <button class="action-btn edit" onclick="editStudent(<?= $student['idsinhvien'] ?>)">Sửa</button>
                                <button class="action-btn delete" onclick="deleteStudent(<?= $student['idsinhvien'] ?>)">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal form thêm/sửa sinh viên -->
    <div id="studentModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Thêm sinh viên</h2>
            <form method="POST" action="" id="studentForm">
                <input type="hidden" name="id" id="studentId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tòa nhà:</label>
                        <input type="text" name="toanha" id="toanha" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phòng:</label>
                        <input type="text" name="phong" id="phong" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Mã sinh viên:</label>
                        <input type="text" name="masinhvien" id="masinhvien" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tên sinh viên:</label>
                        <input type="text" name="tensinhvien" id="tensinhvien">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Ngày vào:</label>
                        <input type="date" name="ngayvao" id="ngayvao" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Trạng thái:</label>
                        <select name="trangthai" id="trangthaiSelect">
                            <option value="Đang ở">Đang ở</option>
                            <option value="Đã rời đi">Đã rời đi</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()">Hủy</button>
                    <button type="submit" name="update_student" id="updateBtn" style="display:none;">Cập nhật</button>
                    <button type="submit" name="add_student" id="addBtn">Thêm sinh viên</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let searchTimeout = null;

    // Xử lý tìm kiếm real-time
    function handleSearchInput() {
        const searchInput = document.getElementById('searchInput');
        const searchLoading = document.getElementById('searchLoading');
        const searchValue = searchInput.value.trim();
        
        // Hiển thị loading
        searchLoading.style.display = 'block';
        
        // Xóa timeout cũ nếu có
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Đặt timeout 2 giây
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 2000);
    }

    // Xóa tìm kiếm
    function clearSearch() {
        const searchInput = document.getElementById('searchInput');
        searchInput.value = '';
        document.getElementById('filterForm').submit();
    }

    function showAddForm() {
        document.getElementById('modalTitle').textContent = 'Thêm sinh viên';
        document.getElementById('addBtn').style.display = 'block';
        document.getElementById('updateBtn').style.display = 'none';
        document.getElementById('studentModal').style.display = 'block';
        document.getElementById('studentForm').reset();
        document.getElementById('studentId').value = '';
        
        // Đặt ngày vào là ngày hiện tại
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('ngayvao').value = today;
    }

    function editStudent(id) {
        // Hiển thị modal chỉnh sửa
        document.getElementById('modalTitle').textContent = 'Sửa thông tin sinh viên';
        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('updateBtn').style.display = 'block';
        document.getElementById('studentModal').style.display = 'block';
        
        // Tìm dòng chứa thông tin sinh viên
        const row = document.querySelector(`tr[data-id="${id}"]`);
        
        if (row) {
            const cells = row.querySelectorAll('td');
            
            // Lấy dữ liệu từ các thuộc tính data-*
            const studentId = id;
            const masinhvien = cells[1].textContent.trim();
            const tensinhvien = cells[2].textContent.trim();
            const toanha = cells[3].textContent.trim();
            const phong = cells[4].textContent.trim();
            const ngayvao = row.getAttribute('data-ngayvao');
            
            // Lấy trạng thái từ badge
            const statusBadge = cells[5].querySelector('.status-badge');
            const trangthai = statusBadge.textContent.trim();
            
            // Điền dữ liệu vào form
            document.getElementById('studentId').value = studentId;
            document.getElementById('toanha').value = toanha;
            document.getElementById('phong').value = phong;
            document.getElementById('masinhvien').value = masinhvien;
            document.getElementById('tensinhvien').value = tensinhvien;
            document.getElementById('ngayvao').value = ngayvao;
            document.getElementById('trangthaiSelect').value = trangthai;
        } else {
            alert('Không tìm thấy thông tin sinh viên');
            closeModal();
        }
    }

    function viewStudent(id) {
        // Tìm dòng chứa thông tin sinh viên
        const row = document.querySelector(`tr[data-id="${id}"]`);
        
        if (row) {
            const cells = row.querySelectorAll('td');
            const studentInfo = {
                masinhvien: cells[1].textContent.trim(),
                tensinhvien: cells[2].textContent.trim(),
                toanha: cells[3].textContent.trim(),
                phong: cells[4].textContent.trim(),
                trangthai: cells[5].querySelector('.status-badge').textContent.trim(),
                ngayvao: row.getAttribute('data-ngayvao')
            };
            
            alert(`Thông tin sinh viên:\n
Mã SV: ${studentInfo.masinhvien}
Tên SV: ${studentInfo.tensinhvien}
Tòa nhà: ${studentInfo.toanha}
Phòng: ${studentInfo.phong}
Trạng thái: ${studentInfo.trangthai}
Ngày vào: ${studentInfo.ngayvao}`);
        }
    }

    function deleteStudent(id) {
        if (confirm('Bạn có chắc muốn xóa sinh viên này?')) {
            window.location.href = '?delete_id=' + id;
        }
    }

    function closeModal() {
        document.getElementById('studentModal').style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const modal = document.getElementById('studentModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>