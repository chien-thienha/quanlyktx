<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

if (!isset($conn)) {
    die("Lỗi: Không thể kết nối đến database. Vui lòng kiểm tra file config.php");
}

// Biến thông báo toast
$toast_message = '';
$toast_type = '';

// Xử lý thêm hợp đồng
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_contract'])) {
    $ma_hopdong = trim($_POST['ma_hopdong'] ?? '');
    $id_sinhvien = intval($_POST['id_sinhvien'] ?? 0);
    $toa_nha = trim($_POST['toa_nha'] ?? '');
    $ten_phong = trim($_POST['ten_phong'] ?? '');
    $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? '';
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? 'Đang ở';
    $ghi_chu = trim($_POST['ghi_chu'] ?? '');
    
    if (!empty($ma_hopdong) && $id_sinhvien > 0 && !empty($toa_nha) && !empty($ten_phong) && !empty($ngay_bat_dau) && !empty($ngay_ket_thuc)) {
        // Kiểm tra trùng mã hợp đồng
        $check_stmt = $conn->prepare("SELECT id_hopdong FROM hopdong WHERE ma_hopdong = ?");
        $check_stmt->bind_param("s", $ma_hopdong);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['toast_message'] = "Mã hợp đồng đã tồn tại! Vui lòng chọn mã khác.";
            $_SESSION['toast_type'] = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO hopdong (ma_hopdong, id_sinhvien, toa_nha, ten_phong, ngay_bat_dau, ngay_ket_thuc, trang_thai, ghi_chu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissdsss", $ma_hopdong, $id_sinhvien, $toa_nha, $ten_phong, $ngay_bat_dau, $ngay_ket_thuc, $trang_thai, $ghi_chu);
            
            if ($stmt->execute()) {
                $_SESSION['toast_message'] = "Thêm hợp đồng thành công!";
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_message'] = "Lỗi khi thêm hợp đồng: " . $conn->error;
                $_SESSION['toast_type'] = 'error';
            }
            $stmt->close();
        }
        $check_stmt->close();
        
        header("Location: hopdong.php");
        exit();
    } else {
        $_SESSION['toast_message'] = "Vui lòng nhập đầy đủ thông tin hợp đồng!";
        $_SESSION['toast_type'] = 'error';
        header("Location: hopdong.php");
        exit();
    }
}

// Xử lý sửa hợp đồng
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_contract'])) {
    $id = intval($_POST['edit_id'] ?? 0);
    $toa_nha = trim($_POST['toa_nha'] ?? '');
    $ten_phong = trim($_POST['ten_phong'] ?? '');
    $ngay_bat_dau = $_POST['ngay_bat_dau'] ?? '';
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'] ?? '';
    $trang_thai = $_POST['trang_thai'] ?? 'Đang ở';
    $ghi_chu = trim($_POST['ghi_chu'] ?? '');
    
    if ($id > 0 && !empty($toa_nha) && !empty($ten_phong) && !empty($ngay_bat_dau) && !empty($ngay_ket_thuc)) {
        $stmt = $conn->prepare("UPDATE hopdong SET toa_nha = ?, ten_phong = ?, ngay_bat_dau = ?, ngay_ket_thuc = ?, trang_thai = ?, ghi_chu = ? WHERE id_hopdong = ?");
        $stmt->bind_param("ssssssi", $toa_nha, $ten_phong, $ngay_bat_dau, $ngay_ket_thuc, $trang_thai, $ghi_chu, $id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Sửa hợp đồng thành công!";
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_message'] = "Lỗi khi sửa hợp đồng: " . $conn->error;
            $_SESSION['toast_type'] = 'error';
        }
        $stmt->close();
        
        header("Location: hopdong.php");
        exit();
    } else {
        $_SESSION['toast_message'] = "Lỗi: Dữ liệu không hợp lệ!";
        $_SESSION['toast_type'] = 'error';
        header("Location: hopdong.php");
        exit();
    }
}

// Xử lý xóa hợp đồng
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM hopdong WHERE id_hopdong = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Xóa hợp đồng thành công!";
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast_message'] = "Lỗi khi xóa hợp đồng: " . $conn->error;
        $_SESSION['toast_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: hopdong.php");
    exit();
}

// LẤY THÔNG BÁO TỪ SESSION VÀ XÓA NGAY
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Xử lý filter và search
$filter_trang_thai = $_GET['trang_thai'] ?? 'all';
$search_keyword = $_GET['search'] ?? '';

// Xây dựng query với filter và search - ĐÃ SỬA
$query = "SELECT hd.*, sv.tensinhvien, sv.masinhvien 
          FROM hopdong hd 
          JOIN sinhvien sv ON hd.id_sinhvien = sv.idsinhvien 
          WHERE 1=1";
$params = [];
$types = "";

if ($filter_trang_thai !== 'all') {
    $query .= " AND hd.trang_thai = ?";
    $params[] = $filter_trang_thai;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $query .= " AND (hd.ma_hopdong LIKE ? OR sv.tensinhvien LIKE ? OR sv.masinhvien LIKE ? OR hd.toa_nha LIKE ? OR hd.ten_phong LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
}

$query .= " ORDER BY hd.id_hopdong DESC";

// Thực thi query với filter
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Lấy danh sách sinh viên để select - ĐÃ SỬA
$sinh_vien_result = $conn->query("SELECT idsinhvien, masinhvien, tensinhvien FROM sinhvien ORDER BY tensinhvien");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách hợp đồng</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="hopdong.css">
</head>
<body>
    <!-- Toast notifications container -->
    <div class="toast-container" id="toastContainer">
        <?php if (!empty($toast_message)): ?>
            <div class="toast <?php echo $toast_type; ?>" id="autoToast">
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
                    <div class="toast-message"><?php echo $toast_message; ?></div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="container">
        <h1>Danh sách hợp đồng</h1>
        
        <!-- Filter section với tìm kiếm -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filter_trang_thai">
                            Trạng thái hợp đồng
                            <?php if ($filter_trang_thai !== 'all'): ?>
                                <span class="active-filter-indicator">✓</span>
                            <?php endif; ?>
                        </label>
                        <select id="filter_trang_thai" name="trang_thai" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_trang_thai == 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                            <option value="Đang ở" <?php echo $filter_trang_thai == 'Đang ở' ? 'selected' : ''; ?>>Đang ở</option>
                            <option value="Đã rời đi" <?php echo $filter_trang_thai == 'Đã rời đi' ? 'selected' : ''; ?>>Đã rời đi</option>
                            <option value="Tạm nghỉ" <?php echo $filter_trang_thai == 'Tạm nghỉ' ? 'selected' : ''; ?>>Tạm nghỉ</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Tìm kiếm</label>
                        <div class="search-container">
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" 
                                   placeholder="Tìm kiếm hợp đồng, sinh viên, phòng..." 
                                   oninput="handleSearchInput()">
                            <div class="search-loading" id="searchLoading" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <button type="button" class="btn-clear-search" id="btnClearSearch" 
                                    onclick="clearSearch()" 
                                    style="<?php echo empty($search_keyword) ? 'display: none;' : ''; ?>">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="btn-reset" onclick="resetFilters()">
                            <i class="fas fa-times"></i> Xóa bộ lọc
                        </button>
                        <button type="button" class="btn-add" onclick="openContractPopup()">
                            <i class="fas fa-plus-circle"></i> Thêm hợp đồng mới
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="contract-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã hợp đồng</th>
                        <th>Sinh viên</th>
                        <th>Tòa nhà</th>
                        <th>Phòng</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php 
                            $stt = 1;
                            while ($row = $result->fetch_assoc()): 
                            $status_class = '';
                            if ($row['trang_thai'] == 'Đang ở') $status_class = 'active';
                            elseif ($row['trang_thai'] == 'Đã rời đi') $status_class = 'expired';
                            elseif ($row['trang_thai'] == 'Tạm nghỉ') $status_class = 'pending';
                        ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo htmlspecialchars($row['ma_hopdong']); ?></td>
                                <!-- ĐÃ SỬA: tensinhvien thay vì ho_ten -->
                                <td><?php echo htmlspecialchars($row['tensinhvien'] . ' (' . $row['masinhvien'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($row['toa_nha']); ?></td>
                                <td><?php echo htmlspecialchars($row['ten_phong']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['ngay_bat_dau'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['ngay_ket_thuc'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($row['trang_thai']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn view" onclick="viewContractFromTable(<?php echo $row['id_hopdong']; ?>)">Xem</button>
                                    <button class="action-btn edit" onclick="openContractPopup(<?php echo $row['id_hopdong']; ?>, '<?php echo htmlspecialchars($row['ma_hopdong']); ?>', <?php echo $row['id_sinhvien']; ?>, '<?php echo htmlspecialchars($row['toa_nha']); ?>', '<?php echo htmlspecialchars($row['ten_phong']); ?>', '<?php echo $row['ngay_bat_dau']; ?>', '<?php echo $row['ngay_ket_thuc']; ?>', '<?php echo $row['trang_thai']; ?>', '<?php echo htmlspecialchars($row['ghi_chu']); ?>')">Sửa</button>
                                    <button class="action-btn delete" onclick="deleteContract(<?php echo $row['id_hopdong']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">
                                <?php 
                                if ($filter_trang_thai !== 'all' || !empty($search_keyword)) {
                                    echo "Không có hợp đồng nào phù hợp với bộ lọc đã chọn.";
                                } else {
                                    echo "Không có dữ liệu hợp đồng";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Popup xem chi tiết hợp đồng -->
<div class="popup-overlay" id="viewPopup">
    <div class="popup-content">
        <div class="popup-header">
            <h2>Chi tiết hợp đồng</h2>
            <button class="close-popup" onclick="closeViewPopup()">&times;</button>
        </div>
        <div class="contract-details">
            <div class="detail-group">
                <h3>Thông tin hợp đồng</h3>
                <div class="detail-row">
                    <span class="detail-label">Mã hợp đồng:</span>
                    <span class="detail-value" id="view_ma_hopdong"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Trạng thái:</span>
                    <span class="detail-value" id="view_trang_thai"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ngày bắt đầu:</span>
                    <span class="detail-value" id="view_ngay_bat_dau"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ngày kết thúc:</span>
                    <span class="detail-value" id="view_ngay_ket_thuc"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ghi chú:</span>
                    <span class="detail-value" id="view_ghi_chu"></span>
                </div>
            </div>
            
            <div class="detail-group">
                <h3>Thông tin sinh viên</h3>
                <div class="detail-row">
                    <span class="detail-label">Mã sinh viên:</span>
                    <span class="detail-value" id="view_ma_sv"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Họ tên:</span>
                    <span class="detail-value" id="view_ten_sv"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ngày sinh:</span>
                    <span class="detail-value" id="view_ngay_sinh"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Lớp:</span>
                    <span class="detail-value" id="view_lop"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Khoa:</span>
                    <span class="detail-value" id="view_khoa"></span>
                </div>
            </div>
            
            <div class="detail-group">
                <h3>Thông tin phòng ở</h3>
                <div class="detail-row">
                    <span class="detail-label">Tòa nhà:</span>
                    <span class="detail-value" id="view_toa_nha"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phòng:</span>
                    <span class="detail-value" id="view_ten_phong"></span>
                </div>
            </div>
        </div>
        <div class="popup-actions">
            <button type="button" class="btn-cancel" onclick="closeViewPopup()">Đóng</button>
            <button type="button" class="btn-edit" onclick="editFromView()">Sửa hợp đồng</button>
        </div>
    </div>
</div>

    <!-- Popup chung cho thêm và sửa hợp đồng -->
    <div class="popup-overlay" id="contractPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2 id="popupTitle">Thêm hợp đồng mới</h2>
                <button class="close-popup" onclick="closeContractPopup()">&times;</button>
            </div>
            <form method="POST" id="contractForm">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="form-group">
                    <label for="ma_hopdong">Mã hợp đồng:</label>
                    <input type="text" id="ma_hopdong" name="ma_hopdong" required placeholder="Nhập mã hợp đồng">
                </div>
                
                <div class="form-group">
                    <label for="id_sinhvien">Sinh viên:</label>
                    <select id="id_sinhvien" name="id_sinhvien" required>
                        <option value="">-- Chọn sinh viên --</option>
                        <?php while ($sv = $sinh_vien_result->fetch_assoc()): ?>
                            <!-- ĐÃ SỬA: idsinhvien và tensinhvien -->
                            <option value="<?php echo $sv['idsinhvien']; ?>"><?php echo htmlspecialchars($sv['tensinhvien'] . ' - ' . $sv['masinhvien']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="toa_nha">Tòa nhà:</label>
                    <input type="text" id="toa_nha" name="toa_nha" required placeholder="Nhập tên tòa nhà">
                </div>
                
                <div class="form-group">
                    <label for="ten_phong">Phòng:</label>
                    <input type="text" id="ten_phong" name="ten_phong" required placeholder="Nhập tên phòng">
                </div>
                
                <div class="form-group">
                    <label for="ngay_bat_dau">Ngày bắt đầu:</label>
                    <input type="date" id="ngay_bat_dau" name="ngay_bat_dau" required>
                </div>
                
                <div class="form-group">
                    <label for="ngay_ket_thuc">Ngày kết thúc:</label>
                    <input type="date" id="ngay_ket_thuc" name="ngay_ket_thuc" required>
                </div>
                
                <div class="form-group">
                    <label for="trang_thai">Trạng thái:</label>
                    <select id="trang_thai" name="trang_thai" required>
                        <option value="Đang ở">Đang ở</option>
                        <option value="Đã rời đi">Đã rời đi</option>
                        <option value="Tạm nghỉ">Tạm nghỉ</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ghi_chu">Ghi chú:</label>
                    <textarea id="ghi_chu" name="ghi_chu" rows="3" placeholder="Nhập ghi chú (nếu có)"></textarea>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="closeContractPopup()">Hủy</button>
                    <button type="submit" name="add_contract" id="submitBtn" class="btn-submit">Thêm hợp đồng</button>
                </div>
            </form>
        </div>
    </div>

<script>
let isEditMode = false;
let searchTimeout = null;

// 1. Định nghĩa hàm formatDisplayDate TRƯỚC
function formatDisplayDate(dateString) {
    if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') return 'N/A';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'N/A';
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

// 2. Các hàm tiện ích khác
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            ${type === 'success' ? '<i class="fas fa-check-circle"></i>' : 
              type === 'error' ? '<i class="fas fa-exclamation-circle"></i>' : 
              '<i class="fas fa-info-circle"></i>'}
        </div>
        <div class="toast-content">
            <div class="toast-title">
                ${type === 'success' ? 'Thành công!' : 
                 type === 'error' ? 'Lỗi!' : 
                 'Thông báo!'}
            </div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// 3. Hàm chính cho hợp đồng
function openContractPopup(id = null, maHopDong = '', idSinhVien = '', toaNha = '', tenPhong = '', ngayBatDau = '', ngayKetThuc = '', trangThai = '', ghiChu = '') {
    const popup = document.getElementById('contractPopup');
    const title = document.getElementById('popupTitle');
    const maHopDongInput = document.getElementById('ma_hopdong');
    const submitBtn = document.getElementById('submitBtn');
    const editIdInput = document.getElementById('edit_id');

    if (id && id > 0) {
        // Chế độ sửa
        isEditMode = true;
        title.textContent = 'Sửa hợp đồng';
        editIdInput.value = id;
        maHopDongInput.value = maHopDong || '';
        maHopDongInput.readOnly = true;
        
        const idSinhVienSelect = document.getElementById('id_sinhvien');
        if (idSinhVienSelect) {
            idSinhVienSelect.value = idSinhVien || '';
            idSinhVienSelect.disabled = true;
        }
        
        document.getElementById('toa_nha').value = toaNha || '';
        document.getElementById('ten_phong').value = tenPhong || '';
        document.getElementById('ngay_bat_dau').value = ngayBatDau || '';
        document.getElementById('ngay_ket_thuc').value = ngayKetThuc || '';
        document.getElementById('trang_thai').value = trangThai || 'Đang ở';
        document.getElementById('ghi_chu').value = ghiChu || '';
        
        submitBtn.name = 'edit_contract';
        submitBtn.textContent = 'Cập nhật';
        submitBtn.className = 'btn-edit';
    } else {
        // Chế độ thêm
        isEditMode = false;
        title.textContent = 'Thêm hợp đồng mới';
        editIdInput.value = '';
        maHopDongInput.readOnly = false;
        
        const idSinhVienSelect = document.getElementById('id_sinhvien');
        if (idSinhVienSelect) {
            idSinhVienSelect.disabled = false;
        }
        
        // Reset form
        const form = document.getElementById('contractForm');
        if (form) {
            form.reset();
        }
        
        // Đặt ngày mặc định
        const today = new Date().toISOString().split('T')[0];
        const ngayBatDauInput = document.getElementById('ngay_bat_dau');
        const ngayKetThucInput = document.getElementById('ngay_ket_thuc');
        
        if (ngayBatDauInput) ngayBatDauInput.value = today;
        if (ngayKetThucInput) {
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            ngayKetThucInput.value = nextYear.toISOString().split('T')[0];
        }
        
        submitBtn.name = 'add_contract';
        submitBtn.textContent = 'Thêm hợp đồng';
        submitBtn.className = 'btn-submit';
    }

    popup.style.display = 'flex';
    if (maHopDongInput) maHopDongInput.focus();
}

function closeContractPopup() {
    const popup = document.getElementById('contractPopup');
    const form = document.getElementById('contractForm');
    
    if (popup) popup.style.display = 'none';
    if (form) form.reset();
    isEditMode = false;
}

function viewContractFromTable(id) {
    // Tìm hàng trong bảng chứa hợp đồng này
    const rows = document.querySelectorAll('.contract-table tbody tr');
    let contractData = null;
    
    rows.forEach(row => {
        const editButton = row.querySelector('.action-btn.edit');
        if (editButton) {
            const onclickText = editButton.getAttribute('onclick');
            if (onclickText && onclickText.includes(`openContractPopup(${id},`)) {
                // Trích xuất dữ liệu từ onclick
                const match = onclickText.match(/openContractPopup\((\d+), '([^']*)', (\d+), '([^']*)', '([^']*)', '([^']*)', '([^']*)', '([^']*)', '([^']*)'\)/);
                if (match) {
                    contractData = {
                        id_hopdong: match[1],
                        ma_hopdong: match[2],
                        id_sinhvien: match[3],
                        toa_nha: match[4],
                        ten_phong: match[5],
                        ngay_bat_dau: match[6],
                        ngay_ket_thuc: match[7],
                        trang_thai: match[8],
                        ghi_chu: match[9].replace(/\\'/g, "'") // Unescape quotes
                    };
                    
                    // Tìm thêm thông tin sinh viên từ ô trong bảng
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 3) {
                        const svText = cells[2].textContent;
                        const svMatch = svText.match(/(.+) \((.+)\)/);
                        if (svMatch) {
                            contractData.tensinhvien = svMatch[1];
                            contractData.masinhvien = svMatch[2];
                        }
                    }

                    // Thêm các trường mặc định cho sinh viên
                    contractData.ngaysinh = '2000-01-01';
                    contractData.lop = 'DTC15';
                    contractData.khoa = 'Công nghệ thông tin';
                }
            }
        }
    });
    
    if (contractData) {
        displayContractDetails(contractData);
    } else {
        showToast('Không tìm thấy thông tin hợp đồng', 'error');
    }
}

function displayContractDetails(contract) {
    if (!contract) {
        showToast('Không có dữ liệu hợp đồng', 'error');
        return;
    }

    // Thông tin hợp đồng
    document.getElementById('view_ma_hopdong').textContent = contract.ma_hopdong || 'N/A';
    document.getElementById('view_trang_thai').textContent = contract.trang_thai || 'N/A';
    document.getElementById('view_ngay_bat_dau').textContent = formatDisplayDate(contract.ngay_bat_dau);
    document.getElementById('view_ngay_ket_thuc').textContent = formatDisplayDate(contract.ngay_ket_thuc);
    document.getElementById('view_ghi_chu').textContent = contract.ghi_chu || 'Không có ghi chú';
    
    // Thông tin sinh viên
    document.getElementById('view_ma_sv').textContent = contract.masinhvien || 'N/A';
    document.getElementById('view_ten_sv').textContent = contract.tensinhvien || 'N/A';
    document.getElementById('view_ngay_sinh').textContent = formatDisplayDate(contract.ngaysinh);
    document.getElementById('view_lop').textContent = contract.lop || 'N/A';
    document.getElementById('view_khoa').textContent = contract.khoa || 'N/A';
    
    // Thông tin phòng
    document.getElementById('view_toa_nha').textContent = contract.toa_nha || 'N/A';
    document.getElementById('view_ten_phong').textContent = contract.ten_phong || 'N/A';
    
    // Lưu ID hợp đồng
    document.getElementById('viewPopup').setAttribute('data-contract-id', contract.id_hopdong);
    
    // Hiển thị popup
    openViewPopup();
}

function openViewPopup() {
    const popup = document.getElementById('viewPopup');
    popup.style.display = 'flex';
}

function closeViewPopup() {
    const popup = document.getElementById('viewPopup');
    popup.style.display = 'none';
}

function editFromView() {
    const contractId = document.getElementById('viewPopup').getAttribute('data-contract-id');
    closeViewPopup();
    
    // Tìm và click nút sửa tương ứng
    setTimeout(() => {
        const editButton = document.querySelector(`button[onclick*="openContractPopup(${contractId},"]`);
        if (editButton) {
            editButton.click();
        }
    }, 300);
}

function deleteContract(id) {
    if (confirm('Bạn có chắc chắn muốn xóa hợp đồng này?')) {
        window.location.href = 'hopdong.php?delete_id=' + id;
    }
}

// 4. Hàm cho filter và search
function resetFilters() {
    window.location.href = 'hopdong.php';
}

function handleSearchInput() {
    const searchInput = document.getElementById('search');
    const searchLoading = document.getElementById('searchLoading');
    const btnClearSearch = document.getElementById('btnClearSearch');
    const searchValue = searchInput.value.trim();
    
    if (searchValue.length > 0) {
        btnClearSearch.style.display = 'block';
    } else {
        btnClearSearch.style.display = 'none';
    }
    
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    if (searchValue === '') {
        submitSearch();
        return;
    }
    
    if (searchLoading) searchLoading.style.display = 'block';
    
    searchTimeout = setTimeout(() => {
        submitSearch();
        if (searchLoading) searchLoading.style.display = 'none';
    }, 500);
}

function submitSearch() {
    const searchInput = document.getElementById('search');
    const searchValue = searchInput.value.trim();
    const url = new URL(window.location.href);
    
    if (searchValue) {
        url.searchParams.set('search', searchValue);
    } else {
        url.searchParams.delete('search');
    }
    
    const filterTrangThai = document.getElementById('filter_trang_thai');
    if (filterTrangThai && filterTrangThai.value !== 'all') {
        url.searchParams.set('trang_thai', filterTrangThai.value);
    } else {
        url.searchParams.delete('trang_thai');
    }
    
    window.location.href = url.toString();
}

function clearSearch() {
    const searchInput = document.getElementById('search');
    const btnClearSearch = document.getElementById('btnClearSearch');
    const searchLoading = document.getElementById('searchLoading');
    
    searchInput.value = '';
    btnClearSearch.style.display = 'none';
    if (searchLoading) searchLoading.style.display = 'none';
    submitSearch();
}

// 5. Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Auto toast
    const autoToast = document.getElementById('autoToast');
    if (autoToast) {
        setTimeout(() => autoToast.classList.add('show'), 100);
        setTimeout(() => {
            autoToast.classList.remove('show');
            setTimeout(() => autoToast.remove(), 300);
        }, 5000);
    }
    
    // Close popups on outside click
    const contractPopup = document.getElementById('contractPopup');
    const viewPopup = document.getElementById('viewPopup');
    
    if (contractPopup) {
        contractPopup.addEventListener('click', function(e) {
            if (e.target === this) closeContractPopup();
        });
    }
    
    if (viewPopup) {
        viewPopup.addEventListener('click', function(e) {
            if (e.target === this) closeViewPopup();
        });
    }

    // Close popups with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeContractPopup();
            closeViewPopup();
        }
    });
});
</script>
</body>
</html>