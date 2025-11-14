<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// KHỞI TẠO TẤT CẢ BIẾN TRƯỚC KHI SỬ DỤNG
$toast_message = '';
$toast_type = '';
$phong_id = 0;
$phong_info = null;
$result = null;

// NHẬN THAM SỐ PHÒNG TỪ URL
if (isset($_GET['phong_id'])) {
    $phong_id = intval($_GET['phong_id']);
    
    // Lấy thông tin phòng
    $stmt = $conn->prepare("SELECT p.*, t.toanha as ten_toa_nha 
                           FROM phong p 
                           LEFT JOIN toanha t ON p.idtoanha = t.idtoanha 
                           WHERE p.idphong = ?");
    $stmt->bind_param("i", $phong_id);
    $stmt->execute();
    $phong_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// XỬ LÝ FORM THÊM SINH VIÊN VÀO PHÒNG
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_student'])) {
        $masinhvien = trim($_POST['masinhvien'] ?? '');
        $tensinhvien = trim($_POST['tensinhvien'] ?? '');
        $ngaysinh = $_POST['ngaysinh'] ?? '';
        $ngayvao = $_POST['ngayvao'] ?? date('Y-m-d');
        $trangthai = $_POST['trangthai'] ?? 'Đang ở';
        
        if (empty($masinhvien) || empty($tensinhvien)) {
            $_SESSION['toast_message'] = "Vui lòng nhập đầy đủ mã sinh viên và tên sinh viên!";
            $_SESSION['toast_type'] = 'error';
        } elseif ($phong_id <= 0) {
            $_SESSION['toast_message'] = "Không xác định được phòng!";
            $_SESSION['toast_type'] = 'error';
        } else {
            // KIỂM TRA TRÙNG MÃ SINH VIÊN
            $check_stmt = $conn->prepare("SELECT idsinhvien FROM sinhvien WHERE masinhvien = ?");
            $check_stmt->bind_param("s", $masinhvien);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $_SESSION['toast_message'] = "Mã sinh viên '$masinhvien' đã tồn tại!";
                $_SESSION['toast_type'] = 'error';
            } else {
                // Lấy thông tin phòng để gán vào sinhvien
                $phong_stmt = $conn->prepare("SELECT phong, idtoanha FROM phong WHERE idphong = ?");
                $phong_stmt->bind_param("i", $phong_id);
                $phong_stmt->execute();
                $phong_data = $phong_stmt->get_result()->fetch_assoc();
                $phong_stmt->close();
                
                // Lấy tên tòa nhà
                $toanha_stmt = $conn->prepare("SELECT toanha FROM toanha WHERE idtoanha = ?");
                $toanha_stmt->bind_param("i", $phong_data['idtoanha']);
                $toanha_stmt->execute();
                $toanha_data = $toanha_stmt->get_result()->fetch_assoc();
                $toanha_stmt->close();
                
                // THÊM SINH VIÊN VÀO PHÒNG - sử dụng bảng sinhvien
                $stmt = $conn->prepare("INSERT INTO sinhvien (masinhvien, tensinhvien, toanha, phong, ngaysinh, ngayvao, trangthai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $masinhvien, $tensinhvien, $toanha_data['toanha'], $phong_data['phong'], $ngaysinh, $ngayvao, $trangthai);
                
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Thêm sinh viên vào phòng thành công!";
                    $_SESSION['toast_type'] = 'success';
                    
                    // CẬP NHẬT TÌNH TRẠNG PHÒNG THÀNH "Đã kín" nếu cần
                    $update_phong_stmt = $conn->prepare("UPDATE phong SET tinhtrang = 'Đã kín' WHERE idphong = ?");
                    $update_phong_stmt->bind_param("i", $phong_id);
                    $update_phong_stmt->execute();
                    $update_phong_stmt->close();
                } else {
                    $_SESSION['toast_message'] = "Lỗi khi thêm sinh viên: " . $conn->error;
                    $_SESSION['toast_type'] = 'error';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
    
    // XỬ LÝ CẬP NHẬT THÔNG TIN SINH VIÊN
    if (isset($_POST['edit_student'])) {
        $edit_id = intval($_POST['edit_id'] ?? 0);
        $masinhvien = trim($_POST['masinhvien'] ?? '');
        $tensinhvien = trim($_POST['tensinhvien'] ?? '');
        $ngaysinh = $_POST['ngaysinh'] ?? '';
        $ngayvao = $_POST['ngayvao'] ?? '';
        $trangthai = $_POST['trangthai'] ?? 'Đang ở';
        
        if ($edit_id > 0) {
            // CẬP NHẬT THÔNG TIN SINH VIÊN
            $stmt = $conn->prepare("UPDATE sinhvien SET masinhvien = ?, tensinhvien = ?, ngaysinh = ?, ngayvao = ?, trangthai = ? WHERE idsinhvien = ?");
            $stmt->bind_param("sssssi", $masinhvien, $tensinhvien, $ngaysinh, $ngayvao, $trangthai, $edit_id);
            
            if ($stmt->execute()) {
                $_SESSION['toast_message'] = "Cập nhật thông tin sinh viên thành công!";
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_message'] = "Lỗi khi cập nhật: " . $conn->error;
                $_SESSION['toast_type'] = 'error';
            }
            $stmt->close();
        }
    }
    
    // CHUYỂN HƯỚNG SAU KHI XỬ LÝ
    header("Location: svphong.php?phong_id=" . $phong_id);
    exit();
}

// Xử lý xóa sinh viên khỏi phòng
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM sinhvien WHERE idsinhvien = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Xóa sinh viên khỏi phòng thành công!";
        $_SESSION['toast_type'] = 'success';
        
        // KIỂM TRA VÀ CẬP NHẬT TÌNH TRẠNG PHÒNG
        $check_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sinhvien WHERE phong = ?");
        $check_count_stmt->bind_param("s", $phong_info['phong']);
        $check_count_stmt->execute();
        $count_result = $check_count_stmt->get_result()->fetch_assoc();
        $check_count_stmt->close();
        
        if ($count_result['total'] == 0) {
            $update_phong_stmt = $conn->prepare("UPDATE phong SET tinhtrang = 'Còn trống' WHERE idphong = ?");
            $update_phong_stmt->bind_param("i", $phong_id);
            $update_phong_stmt->execute();
            $update_phong_stmt->close();
        }
    } else {
        $_SESSION['toast_message'] = "Lỗi khi xóa: " . $conn->error;
        $_SESSION['toast_type'] = 'error';
    }
    $stmt->close();
    
    header("Location: svphong.php?phong_id=" . $phong_id);
    exit();
}

// LẤY THÔNG BÁO TỪ SESSION VÀ XÓA NGAY
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// LẤY DANH SÁCH SINH VIÊN TRONG PHÒNG
if ($phong_id > 0 && $phong_info) {
    $query = "SELECT * FROM sinhvien WHERE phong = ? ORDER BY ngayvao DESC, idsinhvien DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $phong_info['phong']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sinh viên trong phòng <?php echo $phong_info ? htmlspecialchars($phong_info['phong']) : ''; ?></title>
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
                <button class="btn-back" onclick="goBackToRoomList()" title="Quay lại danh sách phòng">
                    <i class="fas fa-arrow-left"></i>
                </button>
                Danh sách sinh viên trong phòng
                <?php if ($phong_info): ?>
                    <span class="building-name">- <?php echo htmlspecialchars($phong_info['phong']); ?></span>
                <?php endif; ?>
            </h1>
        </div>
        
        <!-- Thông tin phòng -->
        <?php if ($phong_info): ?>
        <div class="room-info">
            <h3>Thông tin phòng</h3>
            <div class="room-details">
                <div class="room-detail-item">
                    <span class="room-detail-label">Tòa nhà:</span>
                    <span class="room-detail-value"><?php echo htmlspecialchars($phong_info['ten_toa_nha']); ?></span>
                </div>
                <div class="room-detail-item">
                    <span class="room-detail-label">Tên phòng:</span>
                    <span class="room-detail-value"><?php echo htmlspecialchars($phong_info['phong']); ?></span>
                </div>
                <div class="room-detail-item">
                    <span class="room-detail-label">Tình trạng:</span>
                    <span class="room-detail-value"><?php echo htmlspecialchars($phong_info['tinhtrang']); ?></span>
                </div>
                <div class="room-detail-item">
                    <span class="room-detail-label">Trạng thái hoạt động:</span>
                    <span class="room-detail-value status-badge <?php echo $phong_info['trangthaihoatdong'] == 'Hoạt động' ? 'active' : 'inactive'; ?>">
                        <?php echo htmlspecialchars($phong_info['trangthaihoatdong']); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Controls -->
        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-actions">
                    <button type="button" class="btn-add" onclick="openStudentPopup()">
                        <i class="fas fa-user-plus"></i> Thêm sinh viên vào phòng
                    </button>
                    <button type="button" class="btn-add" onclick="openViolationPopup()">
                        <i class="fas fa-exclamation-triangle"></i> Thêm vi phạm
                    </button>
                    <button type="button" class="btn-add" onclick="openContractPopup()">
                        <i class="fas fa-file-contract"></i> Thêm hợp đồng
                    </button>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã sinh viên</th>
                        <th>Tên sinh viên</th>
                        <th>Ngày sinh</th>
                        <th>Ngày vào</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $stt = 1; while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo htmlspecialchars($row['masinhvien']); ?></td>
                                <td><?php echo htmlspecialchars($row['tensinhvien']); ?></td>
                                <td><?php echo !empty($row['ngaysinh']) && $row['ngaysinh'] != '0000-00-00' ? date('d/m/Y', strtotime($row['ngaysinh'])) : 'Chưa cập nhật'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['ngayvao'])); ?></td>
                                <td>
                                    <span class="status-badge <?php 
                                        echo $row['trangthai'] == 'Đang ở' ? 'dang-o' : 
                                             ($row['trangthai'] == 'Đã ra' ? 'da-ra' : 'tam-vang'); 
                                    ?>">
                                        <?php echo htmlspecialchars($row['trangthai']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn edit" onclick="openStudentPopup(
                                        <?php echo $row['idsinhvien']; ?>,
                                        '<?php echo htmlspecialchars($row['masinhvien']); ?>',
                                        '<?php echo htmlspecialchars($row['tensinhvien']); ?>',
                                        '<?php echo $row['ngaysinh']; ?>',
                                        '<?php echo $row['ngayvao']; ?>',
                                        '<?php echo $row['trangthai']; ?>'
                                    )">Sửa</button>
                                    <button class="action-btn delete" onclick="deleteStudent(<?php echo $row['idsinhvien']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                Chưa có sinh viên nào trong phòng này
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Popup thêm/sửa sinh viên -->
    <div class="popup-overlay" id="studentPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2 id="popupTitle">Thêm sinh viên vào phòng</h2>
                <button class="close-popup" onclick="closeStudentPopup()">&times;</button>
            </div>
            <form method="POST" id="studentForm">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="form-group">
                    <label for="masinhvien">Mã sinh viên:</label>
                    <input type="text" id="masinhvien" name="masinhvien" required placeholder="Nhập mã sinh viên">
                </div>
                
                <div class="form-group">
                    <label for="tensinhvien">Tên sinh viên:</label>
                    <input type="text" id="tensinhvien" name="tensinhvien" required placeholder="Nhập tên sinh viên">
                </div>
                
                <div class="form-group">
                    <label for="ngaysinh">Ngày sinh:</label>
                    <input type="date" id="ngaysinh" name="ngaysinh">
                </div>
                
                <div class="form-group">
                    <label for="ngayvao">Ngày vào:</label>
                    <input type="date" id="ngayvao" name="ngayvao" required>
                </div>
                
                <div class="form-group">
                    <label for="trangthai">Trạng thái:</label>
                    <select id="trangthai" name="trangthai" required>
                        <option value="Đang ở">Đang ở</option>
                        <option value="Đã ra">Đã ra</option>
                        <option value="Tạm vắng">Tạm vắng</option>
                    </select>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="closeStudentPopup()">Hủy</button>
                    <button type="submit" class="btn-submit" id="submitBtn" name="add_student">Thêm sinh viên</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popup thêm vi phạm -->
    <div class="popup-overlay" id="violationPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2>Thêm vi phạm</h2>
                <button class="close-popup" onclick="closeViolationPopup()">&times;</button>
            </div>
            <form method="POST" id="violationForm">
                <div class="form-group">
                    <label for="violation_masinhvien">Mã sinh viên:</label>
                    <select id="violation_masinhvien" name="masinhvien" required>
                        <option value="">Chọn sinh viên</option>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                                <option value="<?php echo $row['masinhvien']; ?>">
                                    <?php echo htmlspecialchars($row['masinhvien'] . ' - ' . $row['tensinhvien']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="violation_tensinhvien">Tên sinh viên:</label>
                    <input type="text" id="violation_tensinhvien" name="tensinhvien" readonly placeholder="Tự động điền khi chọn mã SV">
                </div>
                
                <div class="form-group">
                    <label for="ngayvipham">Ngày vi phạm:</label>
                    <input type="date" id="ngayvipham" name="ngayvipham" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="trangthaivipham">Trạng thái:</label>
                    <select id="trangthaivipham" name="trangthaivipham" required>
                        <option value="Chưa xử lý">Chưa xử lý</option>
                        <option value="Đã xử lý">Đã xử lý</option>
                        <option value="Đang xử lý">Đang xử lý</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="mucdovipham">Mức độ vi phạm:</label>
                    <select id="mucdovipham" name="mucdovipham" required>
                        <option value="Nhẹ">Nhẹ</option>
                        <option value="Trung bình">Trung bình</option>
                        <option value="Nặng">Nặng</option>
                        <option value="Rất nặng">Rất nặng</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="noidungvipham">Nội dung vi phạm:</label>
                    <textarea id="noidungvipham" name="noidungvipham" rows="3" placeholder="Nhập nội dung vi phạm" required></textarea>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="closeViolationPopup()">Hủy</button>
                    <button type="submit" class="btn-submit" name="add_violation">Thêm vi phạm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Popup thêm hợp đồng -->
    <div class="popup-overlay" id="contractPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2>Thêm hợp đồng mới</h2>
                <button class="close-popup" onclick="closeContractPopup()">&times;</button>
            </div>
            <form method="POST" id="contractForm">
                <div class="form-group">
                    <label for="mahopdong">Mã hợp đồng:</label>
                    <input type="text" id="mahopdong" name="mahopdong" required placeholder="Nhập mã hợp đồng">
                </div>
                
                <div class="form-group">
                    <label for="contract_masinhvien">Sinh viên:</label>
                    <select id="contract_masinhvien" name="masinhvien" required>
                        <option value="">Chọn sinh viên</option>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                                <option value="<?php echo $row['masinhvien']; ?>">
                                    <?php echo htmlspecialchars($row['masinhvien'] . ' - ' . $row['tensinhvien']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="toanha_contract">Tòa nhà:</label>
                    <input type="text" id="toanha_contract" name="toanha" value="<?php echo $phong_info ? htmlspecialchars($phong_info['ten_toa_nha']) : ''; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="phong_contract">Phòng:</label>
                    <input type="text" id="phong_contract" name="phong" value="<?php echo $phong_info ? htmlspecialchars($phong_info['phong']) : ''; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="ngaybatdau">Ngày bắt đầu:</label>
                    <input type="date" id="ngaybatdau" name="ngaybatdau" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="ngayketthuc">Ngày kết thúc:</label>
                    <input type="date" id="ngayketthuc" name="ngayketthuc" required value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="trangthaihopdong">Trạng thái:</label>
                    <select id="trangthaihopdong" name="trangthaihopdong" required>
                        <option value="Đang ở">Đang ở</option>
                        <option value="Đã kết thúc">Đã kết thúc</option>
                        <option value="Tạm ngừng">Tạm ngừng</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ghichu">Ghi chú:</label>
                    <textarea id="ghichu" name="ghichu" rows="3" placeholder="Nhập ghi chú (nếu có)"></textarea>
                </div>
                
                <div class="popup-actions">
                    <button type="button" class="btn-cancel" onclick="closeContractPopup()">Hủy</button>
                    <button type="submit" class="btn-submit" name="add_contract">Thêm hợp đồng</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function goBackToRoomList() {
            let url = 'phong.php';
            <?php if ($phong_info): ?>
                url += '?toanha_id=<?php echo $phong_info["idtoanha"]; ?>';
            <?php endif; ?>
            window.location.href = url;
        }

        // POPUP THÊM/SỬA SINH VIÊN
        function openStudentPopup(id = null, maSinhVien = '', tenSinhVien = '', ngaySinh = '', ngayVao = '', trangThai = 'Đang ở') {
            const popup = document.getElementById('studentPopup');
            const title = document.getElementById('popupTitle');
            const maSinhVienInput = document.getElementById('masinhvien');
            const tenSinhVienInput = document.getElementById('tensinhvien');
            const ngaySinhInput = document.getElementById('ngaysinh');
            const ngayVaoInput = document.getElementById('ngayvao');
            const trangThaiSelect = document.getElementById('trangthai');
            const submitBtn = document.getElementById('submitBtn');
            const editIdInput = document.getElementById('edit_id');

            // Reset form
            document.getElementById('studentForm').reset();
            
            // Đặt ngày mặc định là hôm nay nếu không có giá trị
            if (!ngayVao) {
                ngayVao = new Date().toISOString().split('T')[0];
            }

            if (id) {
                // Chế độ sửa
                title.textContent = 'Sửa thông tin sinh viên';
                editIdInput.value = id;
                maSinhVienInput.value = maSinhVien;
                tenSinhVienInput.value = tenSinhVien;
                ngaySinhInput.value = ngaySinh;
                ngayVaoInput.value = ngayVao;
                trangThaiSelect.value = trangThai;
                submitBtn.textContent = 'Cập nhật';
                submitBtn.name = 'edit_student';
            } else {
                // Chế độ thêm
                title.textContent = 'Thêm sinh viên vào phòng';
                editIdInput.value = '';
                maSinhVienInput.value = '';
                tenSinhVienInput.value = '';
                ngaySinhInput.value = '';
                ngayVaoInput.value = ngayVao;
                trangThaiSelect.value = 'Đang ở';
                submitBtn.textContent = 'Thêm sinh viên';
                submitBtn.name = 'add_student';
            }

            popup.style.display = 'flex';
            setTimeout(() => {
                if (!id) maSinhVienInput.focus();
            }, 100);
        }

        function closeStudentPopup() {
            const popup = document.getElementById('studentPopup');
            popup.style.display = 'none';
        }

        // POPUP THÊM VI PHẠM
        function openViolationPopup() {
            const popup = document.getElementById('violationPopup');
            popup.style.display = 'flex';
            setTimeout(() => {
                document.getElementById('violation_masinhvien').focus();
            }, 100);
        }

        function closeViolationPopup() {
            const popup = document.getElementById('violationPopup');
            popup.style.display = 'none';
        }

        // POPUP THÊM HỢP ĐỒNG
        function openContractPopup() {
            const popup = document.getElementById('contractPopup');
            popup.style.display = 'flex';
            setTimeout(() => {
                document.getElementById('mahopdong').focus();
            }, 100);
        }

        function closeContractPopup() {
            const popup = document.getElementById('contractPopup');
            popup.style.display = 'none';
        }

        function deleteStudent(id) {
            if (confirm('Bạn có chắc chắn muốn xóa sinh viên này khỏi phòng?')) {
                window.location.href = 'svphong.php?delete_id=' + id + '&phong_id=<?php echo $phong_id; ?>';
            }
        }

        // Hiển thị toast
        document.addEventListener('DOMContentLoaded', function() {
            const autoToast = document.getElementById('autoToast');
            if (autoToast) {
                setTimeout(() => autoToast.classList.add('show'), 100);
                setTimeout(() => autoToast.remove(), 5000);
            }
            
            // Đặt ngày mặc định cho các trường ngày
            const ngayVaoInput = document.getElementById('ngayvao');
            if (ngayVaoInput && !ngayVaoInput.value) {
                ngayVaoInput.value = new Date().toISOString().split('T')[0];
            }

            // Xử lý khi chọn mã sinh viên trong form vi phạm
            const violationMaSinhVien = document.getElementById('violation_masinhvien');
            const violationTenSinhVien = document.getElementById('violation_tensinhvien');
            
            if (violationMaSinhVien) {
                violationMaSinhVien.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const studentInfo = selectedOption.text.split(' - ');
                        if (studentInfo.length > 1) {
                            violationTenSinhVien.value = studentInfo[1];
                        }
                    } else {
                        violationTenSinhVien.value = '';
                    }
                });
            }
        });

        // Đóng popup khi click bên ngoài
        document.querySelectorAll('.popup-overlay').forEach(popup => {
            popup.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Đóng popup bằng phím ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStudentPopup();
                closeViolationPopup();
                closeContractPopup();
            }
        });
    </script>
</body>
</html>