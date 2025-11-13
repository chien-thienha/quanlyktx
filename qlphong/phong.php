<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// KHỞI TẠO TẤT CẢ BIẾN TRƯỚC KHI SỬ DỤNG
$toast_message = '';
$toast_type = '';
$selected_toanha_id = 0;
$selected_toanha_name = '';
$filter_tinhtrang = 'all';
$filter_trangthai = 'all';
$search_keyword = '';
$result = null;

// NHẬN THAM SỐ TÒA NHÀ TỪ URL
if (isset($_GET['toanha_id'])) {
    $selected_toanha_id = intval($_GET['toanha_id']);
}

// Lấy tên tòa nhà nếu có ID
if ($selected_toanha_id > 0) {
    $stmt = $conn->prepare("SELECT toanha FROM toanha WHERE idtoanha = ?");
    $stmt->bind_param("i", $selected_toanha_id);
    $stmt->execute();
    $result_toanha = $stmt->get_result();
    if ($toanha_row = $result_toanha->fetch_assoc()) {
        $selected_toanha_name = $toanha_row['toanha'];
    }
    $stmt->close();
}

// XỬ LÝ FORM SUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_toanha = intval($_POST['idtoanha'] ?? 0);
    $tenphong = trim($_POST['tenphong'] ?? '');
    $tinhtrang = $_POST['tinhtrang'] ?? 'Còn trống';
    $trangthaihoatdong = $_POST['trangthaihoatdong'] ?? 'Hoạt động';
    $edit_id = intval($_POST['edit_id'] ?? 0);
    
    if ($edit_id > 0) {
        // CHỈNH SỬA PHÒNG - CHỈ CẬP NHẬT idtoanha, không cần toanha
        $stmt = $conn->prepare("UPDATE phong SET idtoanha = ?, phong = ?, tinhtrang = ?, trangthaihoatdong = ? WHERE idphong = ?");
        $stmt->bind_param("isssi", $id_toanha, $tenphong, $tinhtrang, $trangthaihoatdong, $edit_id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Cập nhật phòng thành công!";
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_message'] = "Lỗi khi cập nhật: " . $conn->error;
            $_SESSION['toast_type'] = 'error';
        }
        $stmt->close();
    } else {
        // THÊM MỚI PHÒNG - CHỈ INSERT idtoanha, không cần toanha
        if (empty($tenphong)) {
            $_SESSION['toast_message'] = "Vui lòng nhập tên phòng!";
            $_SESSION['toast_type'] = 'error';
        } elseif ($id_toanha <= 0) {
            $_SESSION['toast_message'] = "Vui lòng chọn tòa nhà!";
            $_SESSION['toast_type'] = 'error';
        } else {
            // KIỂM TRA TRÙNG TÊN
            $check_stmt = $conn->prepare("SELECT idphong FROM phong WHERE idtoanha = ? AND phong = ?");
            $check_stmt->bind_param("is", $id_toanha, $tenphong);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['toast_message'] = "Tên phòng '$tenphong' đã tồn tại trong tòa nhà này!";
                $_SESSION['toast_type'] = 'error';
            } else {
                // CHỈ INSERT idtoanha, KHÔNG insert toanha
                $stmt = $conn->prepare("INSERT INTO phong (idtoanha, phong, tinhtrang, trangthaihoatdong) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $id_toanha, $tenphong, $tinhtrang, $trangthaihoatdong);
                
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Thêm phòng thành công!";
                    $_SESSION['toast_type'] = 'success';
                } else {
                    $_SESSION['toast_message'] = "Lỗi khi thêm phòng: " . $conn->error;
                    $_SESSION['toast_type'] = 'error';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
    
    // CHUYỂN HƯỚNG SAU KHI XỬ LÝ
    header("Location: phong.php" . ($selected_toanha_id > 0 ? "?toanha_id=" . $selected_toanha_id : ""));
    exit();
}

// Xử lý xóa phòng
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM phong WHERE idphong = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Xóa phòng thành công!";
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast_message'] = "Lỗi khi xóa: " . $conn->error;
        $_SESSION['toast_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: phong.php" . ($selected_toanha_id > 0 ? "?toanha_id=" . $selected_toanha_id : ""));
    exit();
}

// LẤY THÔNG BÁO TỪ SESSION VÀ XÓA NGAY
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Xử lý filter
if (isset($_GET['tinhtrang'])) $filter_tinhtrang = $_GET['tinhtrang'];
if (isset($_GET['trangthai'])) $filter_trangthai = $_GET['trangthai'];
if (isset($_GET['search'])) $search_keyword = trim($_GET['search']);

// Xây dựng query với JOIN để lấy tên tòa nhà
$query = "SELECT p.*, t.toanha as ten_toa_nha FROM phong p LEFT JOIN toanha t ON p.idtoanha = t.idtoanha WHERE 1=1";
$params = []; $types = "";

if ($selected_toanha_id > 0) {
    $query .= " AND p.idtoanha = ?";
    $params[] = $selected_toanha_id; $types .= "i";
}
if ($filter_tinhtrang !== 'all') {
    $query .= " AND p.tinhtrang = ?";
    $params[] = $filter_tinhtrang; $types .= "s";
}
if ($filter_trangthai !== 'all') {
    $query .= " AND p.trangthaihoatdong = ?";
    $params[] = $filter_trangthai; $types .= "s";
}
if (!empty($search_keyword)) {
    $query .= " AND (p.phong LIKE ? OR t.toanha LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param; $params[] = $search_param; $types .= "ss";
}

$query .= " ORDER BY t.toanha, p.phong ASC";

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

// Lấy danh sách tòa nhà
$toa_nha_result = $conn->query("SELECT idtoanha, toanha FROM toanha ORDER BY toanha");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách phòng <?php echo !empty($selected_toanha_name) ? ' - ' . htmlspecialchars($selected_toanha_name) : ''; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="toanha.css">
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
            <h1>
                <button class="btn-back" onclick="goBackToBuildingList()" title="Quay lại danh sách tòa nhà">
                    <i class="fas fa-arrow-left"></i>
                </button>
                Danh sách phòng
                <?php if (!empty($selected_toanha_name)): ?>
                    <span class="building-name">- <?php echo htmlspecialchars($selected_toanha_name); ?></span>
                <?php endif; ?>
            </h1>
        </div>
        
        <!-- Filter section -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <?php if ($selected_toanha_id > 0): ?>
                    <input type="hidden" name="toanha_id" value="<?php echo $selected_toanha_id; ?>">
                <?php endif; ?>
                
                <div class="filter-row">
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
                        <button type="button" class="btn-add" onclick="openRoomPopup()">
                            <i class="fas fa-plus-circle"></i> Thêm phòng mới
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Tên phòng</th>
                        <th>Tình trạng</th>
                        <th>Trạng thái hoạt động</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo htmlspecialchars($row['phong']); ?></td>
                                <td><?php echo htmlspecialchars($row['tinhtrang']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['trangthaihoatdong'] == 'Hoạt động' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['trangthaihoatdong']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="svphong.php?phong_id=<?php echo $row['idphong']; ?>">
                                        <button class="action-btn view">Xem</button>
                                    </a>
                                    <button class="action-btn edit" onclick="openRoomPopup(
                                        <?php echo $row['idphong']; ?>, 
                                        <?php echo $row['idtoanha']; ?>,
                                        '<?php echo htmlspecialchars($row['phong']); ?>',
                                        '<?php echo $row['tinhtrang']; ?>', 
                                        '<?php echo $row['trangthaihoatdong']; ?>'
                                    )">Sửa</button>
                                    <button class="action-btn delete" onclick="deleteRoom(<?php echo $row['idphong']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">Không có dữ liệu phòng</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Popup chung cho thêm và sửa phòng -->
    <div class="popup-overlay" id="roomPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2 id="popupTitle">Thêm phòng mới</h2>
                <button class="close-popup" onclick="closeRoomPopup()">&times;</button>
            </div>
            <form method="POST" id="roomForm">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="form-group">
                    <label for="idtoanha">Tòa nhà:</label>
                    <select id="idtoanha" name="idtoanha" required <?php echo ($selected_toanha_id > 0) ? 'disabled' : ''; ?>>
                        <option value="">Chọn tòa nhà</option>
                        <?php 
                        if ($toa_nha_result && $toa_nha_result->num_rows > 0) {
                            $toa_nha_result->data_seek(0);
                            while ($toa_nha = $toa_nha_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $toa_nha['idtoanha']; ?>" 
                                <?php echo ($selected_toanha_id > 0 && $toa_nha['idtoanha'] == $selected_toanha_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($toa_nha['toanha']); ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                    <?php if ($selected_toanha_id > 0): ?>
                        <input type="hidden" name="idtoanha" value="<?php echo $selected_toanha_id; ?>">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="tenphong">Tên phòng:</label>
                    <input type="text" id="tenphong" name="tenphong" required placeholder="Nhập tên phòng">
                </div>
                
                <div class="form-group">
                    <label for="tinhtrang">Tình trạng:</label>
                    <select id="tinhtrang" name="tinhtrang" required>
                        <option value="Còn trống">Còn trống</option>
                        <option value="Đã kín">Đã kín</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="trangthaihoatdong">Trạng thái hoạt động:</label>
                    <select id="trangthaihoatdong" name="trangthaihoatdong" required>
                        <option value="Hoạt động">Hoạt động</option>
                        <option value="Không hoạt động">Không hoạt động</option>
                    </select>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="closeRoomPopup()">Hủy</button>
                    <button type="submit" class="btn-submit" id="submitBtn">Thêm phòng</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let searchTimeout = null;

        function goBackToBuildingList() {
            window.location.href = 'toanha.php';
        }

        function resetFilters() {
            let url = 'phong.php';
            <?php if ($selected_toanha_id > 0): ?>
                url += '?toanha_id=<?php echo $selected_toanha_id; ?>';
            <?php endif; ?>
            window.location.href = url;
        }

        // POPUP CHUNG CHO THÊM VÀ SỬA
        function openRoomPopup(id = null, idToaNha = '', tenPhong = '', tinhTrang = '', trangThaiHoatDong = '') {
            const popup = document.getElementById('roomPopup');
            const title = document.getElementById('popupTitle');
            const toaNhaSelect = document.getElementById('idtoanha');
            const tenPhongInput = document.getElementById('tenphong');
            const submitBtn = document.getElementById('submitBtn');
            const editIdInput = document.getElementById('edit_id');
            const tinhTrangSelect = document.getElementById('tinhtrang');
            const trangThaiSelect = document.getElementById('trangthaihoatdong');

            // Reset form
            document.getElementById('roomForm').reset();
            
            // Reset trạng thái các trường
            toaNhaSelect.disabled = false;
            toaNhaSelect.classList.remove('readonly-field');
            tenPhongInput.readOnly = false;
            tenPhongInput.classList.remove('readonly-field');

            if (id) {
                // Chế độ sửa
                title.textContent = 'Sửa thông tin phòng';
                editIdInput.value = id;
                toaNhaSelect.value = idToaNha;
                toaNhaSelect.disabled = true;
                toaNhaSelect.classList.add('readonly-field');
                tenPhongInput.value = tenPhong;
                tenPhongInput.readOnly = true;
                tenPhongInput.classList.add('readonly-field');
                tinhTrangSelect.value = tinhTrang;
                trangThaiSelect.value = trangThaiHoatDong;
                submitBtn.textContent = 'Cập nhật';
            } else {
                // Chế độ thêm
                title.textContent = 'Thêm phòng mới';
                editIdInput.value = '';
                
                <?php if ($selected_toanha_id > 0): ?>
                    toaNhaSelect.value = '<?php echo $selected_toanha_id; ?>';
                    toaNhaSelect.disabled = true;
                    toaNhaSelect.classList.add('readonly-field');
                <?php else: ?>
                    toaNhaSelect.value = '';
                    toaNhaSelect.disabled = false;
                    toaNhaSelect.classList.remove('readonly-field');
                <?php endif; ?>
                
                tenPhongInput.value = '';
                tinhTrangSelect.value = 'Còn trống';
                trangThaiSelect.value = 'Hoạt động';
                submitBtn.textContent = 'Thêm phòng';
            }

            popup.style.display = 'flex';
            setTimeout(() => {
                if (!id) tenPhongInput.focus();
            }, 100);
        }

        function closeRoomPopup() {
            const popup = document.getElementById('roomPopup');
            popup.style.display = 'none';
        }

        function deleteRoom(id) {
            if (confirm('Bạn có chắc chắn muốn xóa phòng này?')) {
                let url = 'phong.php?delete_id=' + id;
                <?php if ($selected_toanha_id > 0): ?>
                    url += '&toanha_id=<?php echo $selected_toanha_id; ?>';
                <?php endif; ?>
                window.location.href = url;
            }
        }

        // Xử lý tìm kiếm
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
        });

        // Đóng popup khi click bên ngoài
        document.getElementById('roomPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRoomPopup();
            }
        });

        // Đóng popup bằng phím ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRoomPopup();
            }
        });
    </script>
</body>
</html>