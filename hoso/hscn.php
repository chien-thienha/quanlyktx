<?php
session_start();
require_once('../auth_check.php');
include('../config.php');

// Kiểm tra xem user đã đăng nhập chưa
if (!isset($_SESSION['iduser'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['iduser'];
$message = "";
$error = "";

// Lấy thông tin user từ database
$sql = "SELECT * FROM hoso WHERE iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    $error = "Không tìm thấy thông tin người dùng!";
    $user = [
        'iduser' => $user_id,
        'hovaten' => '',
        'gioitinh' => '',
        'ngaysinh' => '',
        'sodienthoai' => '',
        'email' => '',
        'diachi' => ''
    ];
}
$stmt->close();

// Xử lý cập nhật thông tin cá nhân
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $hovaten = $_POST['hovaten'];
    $gioitinh = $_POST['gioitinh'];
    $ngaysinh = $_POST['ngaysinh'];
    $sodienthoai = $_POST['sodienthoai'];
    $email = $_POST['email'];
    $diachi = $_POST['diachi'];
    
    // Kiểm tra số điện thoại
    if (!is_numeric($sodienthoai) || strlen($sodienthoai) != 10) {
        $error = "Số điện thoại phải là 10 chữ số!";
    } else {
        $update_sql = "UPDATE hoso SET hovaten = ?, gioitinh = ?, ngaysinh = ?, sodienthoai = ?, email = ?, diachi = ? WHERE iduser = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssisssi", $hovaten, $gioitinh, $ngaysinh, $sodienthoai, $email, $diachi, $user_id);
        
        if ($update_stmt->execute()) {
            $message = "Cập nhật thông tin thành công!";
            // Cập nhật lại thông tin user
            $user['hovaten'] = $hovaten;
            $user['gioitinh'] = $gioitinh;
            $user['ngaysinh'] = $ngaysinh;
            $user['sodienthoai'] = $sodienthoai;
            $user['email'] = $email;
            $user['diachi'] = $diachi;
        } else {
            $error = "Lỗi khi cập nhật thông tin: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Kiểm tra mật khẩu cũ (giả sử có bảng users chứa password)
    $check_sql = "SELECT password FROM users WHERE iduser = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $user_data = $check_result->fetch_assoc();
        
        // Kiểm tra mật khẩu cũ (giả sử dùng password_verify nếu mã hóa)
        if (password_verify($old_password, $user_data['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pass_sql = "UPDATE users SET password = ? WHERE iduser = ?";
                    $pass_stmt = $conn->prepare($pass_sql);
                    $pass_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($pass_stmt->execute()) {
                        $message = "Đổi mật khẩu thành công!";
                    } else {
                        $error = "Lỗi khi đổi mật khẩu: " . $pass_stmt->error;
                    }
                    $pass_stmt->close();
                } else {
                    $error = "Mật khẩu mới phải có ít nhất 6 ký tự!";
                }
            } else {
                $error = "Mật khẩu xác nhận không khớp!";
            }
        } else {
            $error = "Mật khẩu cũ không đúng!";
        }
    } else {
        $error = "Không tìm thấy thông tin tài khoản!";
    }
    $check_stmt->close();
}

// Xử lý xóa tài khoản
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    $confirm_delete = $_POST['confirm_delete'];
    
    if ($confirm_delete === 'YES') {
        // Xóa thông tin từ bảng hoso
        $delete_sql = "DELETE FROM hoso WHERE iduser = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Xóa tài khoản từ bảng users (nếu có)
        $delete_user_sql = "DELETE FROM users WHERE iduser = ?";
        $delete_user_stmt = $conn->prepare($delete_user_sql);
        $delete_user_stmt->bind_param("i", $user_id);
        $delete_user_stmt->execute();
        $delete_user_stmt->close();
        
        // Hủy session và chuyển hướng
        session_destroy();
        header("Location: login.php?message=Tài khoản đã được xóa thành công");
        exit();
    } else {
        $error = "Vui lòng nhập 'YES' để xác nhận xóa tài khoản!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="hscn.css">
</head>
<body>
    <div class="profile-container">
        <h2 class="profile-title">Hồ sơ cá nhân</h2>

        <!-- Hiển thị thông báo -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-content">
            <div class="profile-left">
                <img src="https://cdn-icons-png.flaticon.com/512/219/219970.png" alt="Avatar" class="profile-avatar">
                <button class="upload-btn"><i class="fa-solid fa-camera"></i> Đổi ảnh</button>
            </div>

            <div class="profile-right">
                <form class="profile-form" method="POST">
                    <div class="form-group">
                        <label>ID:</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['iduser']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Họ và tên:</label>
                        <input type="text" name="hovaten" value="<?php echo htmlspecialchars($user['hovaten']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Ngày sinh:</label>
                        <input type="date" name="ngaysinh" value="<?php echo htmlspecialchars($user['ngaysinh']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Giới tính:</label>
                        <select name="gioitinh">
                            <option value="Nam" <?php echo ($user['gioitinh'] == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?php echo ($user['gioitinh'] == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Số điện thoại:</label>
                        <input type="text" name="sodienthoai" value="<?php echo htmlspecialchars($user['sodienthoai']); ?>" required pattern="[0-9]{10}" title="Số điện thoại phải là 10 chữ số">
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Địa chỉ:</label>
                        <input type="text" name="diachi" value="<?php echo htmlspecialchars($user['diachi']); ?>">
                    </div>

                    <div class="profile-actions">
                        <button type="submit" name="update_profile" class="save-btn"><i class="fa-solid fa-save"></i> Lưu thay đổi</button>
                        <button type="reset" class="cancel-btn"><i class="fa-solid fa-rotate-left"></i> Hủy</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 🔐 KHU VỰC QUẢN LÝ TÀI KHOẢN -->
        <div class="account-section">
            <h3><i class="fa-solid fa-user-shield"></i> Quản lý tài khoản</h3>
            
            <div class="account-actions">
                <div class="password-box">
                    <form method="POST">
                        <label for="old-pass">Mật khẩu hiện tại:</label>
                        <input type="password" id="old-pass" name="old_password" placeholder="Nhập mật khẩu cũ" required>

                        <label for="new-pass">Mật khẩu mới:</label>
                        <input type="password" id="new-pass" name="new_password" placeholder="Nhập mật khẩu mới" required minlength="6">

                        <label for="confirm-pass">Xác nhận mật khẩu mới:</label>
                        <input type="password" id="confirm-pass" name="confirm_password" placeholder="Nhập lại mật khẩu mới" required>

                        <button type="submit" name="change_password" class="change-pass-btn"><i class="fa-solid fa-key"></i> Đổi mật khẩu</button>
                    </form>
                </div>

                <div class="delete-box">
                    <h4><i class="fa-solid fa-triangle-exclamation"></i> Xóa tài khoản</h4>
                    <p class="warning-text">⚠️ Hành động này không thể hoàn tác. Tất cả dữ liệu liên quan đến tài khoản của bạn sẽ bị xóa vĩnh viễn.</p>
                    <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản? Đây là hành động không thể hoàn tác!')">
                        <input type="text" name="confirm_delete" placeholder="Nhập 'YES' để xác nhận" required style="margin-bottom: 10px; padding: 5px; width: 200px;">
                        <button type="submit" name="delete_account" class="delete-btn"><i class="fa-solid fa-user-xmark"></i> Xóa tài khoản</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hiển thị cảnh báo khi nhấn nút xóa tài khoản
        document.querySelector('.delete-btn')?.addEventListener('click', function(e) {
            if (!confirm('Bạn có chắc chắn muốn xóa tài khoản? Tất cả dữ liệu sẽ bị mất vĩnh viễn!')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>