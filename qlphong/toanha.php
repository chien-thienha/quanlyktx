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

// Xử lý thêm tòa nhà
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_building'])) {
    $ten_toa_nha = trim($_POST['ten_toa_nha'] ?? '');
    $tinhtrang = $_POST['tinhtrang'] ?? 'Còn trống';
    $trangthaihoatdong = $_POST['trangthaihoatdong'] ?? 'Hoạt động';
    
    if (!empty($ten_toa_nha)) {
        // Kiểm tra trùng tên tòa nhà
        $check_stmt = $conn->prepare("SELECT idtoanha FROM toanha WHERE toanha = ?");
        $check_stmt->bind_param("s", $ten_toa_nha);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['toast_message'] = "Tên tòa nhà đã tồn tại! Vui lòng chọn tên khác.";
            $_SESSION['toast_type'] = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO toanha (toanha, tinhtrang, trangthaihoatdong) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $ten_toa_nha, $tinhtrang, $trangthaihoatdong);
            
            if ($stmt->execute()) {
                $_SESSION['toast_message'] = "Thêm tòa nhà thành công!";
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_message'] = "Lỗi khi thêm tòa nhà: " . $conn->error;
                $_SESSION['toast_type'] = 'error';
            }
            $stmt->close();
        }
        $check_stmt->close();
        
        // CHUYỂN HƯỚNG NGAY SAU KHI XỬ LÝ
        header("Location: toanha.php");
        exit();
    } else {
        $_SESSION['toast_message'] = "Vui lòng nhập tên tòa nhà!";
        $_SESSION['toast_type'] = 'error';
        header("Location: toanha.php");
        exit();
    }
}

// Xử lý sửa tòa nhà
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_building'])) {
    $id = intval($_POST['edit_id'] ?? 0);
    $tinhtrang = $_POST['tinhtrang'] ?? 'Còn trống';
    $trangthaihoatdong = $_POST['trangthaihoatdong'] ?? 'Hoạt động';
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE toanha SET tinhtrang = ?, trangthaihoatdong = ? WHERE idtoanha = ?");
        $stmt->bind_param("ssi", $tinhtrang, $trangthaihoatdong, $id);
        
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = "Sửa tòa nhà thành công!";
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_message'] = "Lỗi khi sửa tòa nhà: " . $conn->error;
            $_SESSION['toast_type'] = 'error';
        }
        $stmt->close();
        
        // CHUYỂN HƯỚNG NGAY SAU KHI XỬ LÝ
        header("Location: toanha.php");
        exit();
    } else {
        $_SESSION['toast_message'] = "Lỗi: Không tìm thấy ID tòa nhà!";
        $_SESSION['toast_type'] = 'error';
        header("Location: toanha.php");
        exit();
    }
}

// Xử lý xóa tòa nhà
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM toanha WHERE idtoanha = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "Xóa tòa nhà thành công!";
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast_message'] = "Lỗi khi xóa tòa nhà: " . $conn->error;
        $_SESSION['toast_type'] = 'error';
    }
    $stmt->close();
    
    // CHUYỂN HƯỚNG NGAY SAU KHI XỬ LÝ
    header("Location: toanha.php");
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
$filter_tinhtrang = $_GET['tinhtrang'] ?? 'all';
$filter_trangthai = $_GET['trangthai'] ?? 'all';
$search_keyword = $_GET['search'] ?? '';

// Xây dựng query với filter và search
$query = "SELECT * FROM toanha WHERE 1=1";
$params = [];
$types = "";

if ($filter_tinhtrang !== 'all') {
    $query .= " AND tinhtrang = ?";
    $params[] = $filter_tinhtrang;
    $types .= "s";
}

if ($filter_trangthai !== 'all') {
    $query .= " AND trangthaihoatdong = ?";
    $params[] = $filter_trangthai;
    $types .= "s";
}

if (!empty($search_keyword)) {
    $query .= " AND (toanha LIKE ? OR tinhtrang LIKE ? OR trangthaihoatdong LIKE ?)";
    $search_param = "%$search_keyword%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY idtoanha ASC";

// Thực thi query với filter
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách tòa nhà</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="toanha.css">
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
        <h1>Danh sách tòa nhà</h1>
        
        <!-- Filter section với tìm kiếm -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filter_tinhtrang">
                            Tình trạng:
                            <?php if ($filter_tinhtrang !== 'all'): ?>
                                <span class="active-filter-indicator">✓</span>
                            <?php endif; ?>
                        </label>
                        <select id="filter_tinhtrang" name="tinhtrang" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_tinhtrang == 'all' ? 'selected' : ''; ?>>Tất cả tình trạng</option>
                            <option value="Còn trống" <?php echo $filter_tinhtrang == 'Còn trống' ? 'selected' : ''; ?>>Còn trống</option>
                            <option value="Đã kín" <?php echo $filter_tinhtrang == 'Đã kín' ? 'selected' : ''; ?>>Đã kín</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_trangthai">
                            Trạng thái hoạt động:
                            <?php if ($filter_trangthai !== 'all'): ?>
                                <span class="active-filter-indicator">✓</span>
                            <?php endif; ?>
                        </label>
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
                                   placeholder="Tìm kiếm thông tin" 
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
                        <button type="button" class="btn-add" onclick="openBuildingPopup()">
                            <i class="fas fa-plus-circle"></i> Thêm tòa nhà mới
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
                        <th>Tòa nhà</th>
                        <th>Tình trạng</th>
                        <th>Trạng thái hoạt động</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php 
                            $stt = 1;
                            while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $stt++; ?></td>
                                <td><?php echo htmlspecialchars($row['toanha']); ?></td>
                                <td><?php echo htmlspecialchars($row['tinhtrang']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['trangthaihoatdong'] == 'Hoạt động' ? 'active' : 'inactive'; ?>">
                                        <?php echo htmlspecialchars($row['trangthaihoatdong']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="phong.php?toanha_id=<?php echo $row['idtoanha']; ?>">
                                        <button class="action-btn view">Xem</button>
                                    </a>
                                    <button class="action-btn edit" onclick="openBuildingPopup(<?php echo $row['idtoanha']; ?>, '<?php echo htmlspecialchars($row['toanha']); ?>', '<?php echo $row['tinhtrang']; ?>', '<?php echo $row['trangthaihoatdong']; ?>')">Sửa</button>
                                    <button class="action-btn delete" onclick="deleteBuilding(<?php echo $row['idtoanha']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <?php 
                                if ($filter_tinhtrang !== 'all' || $filter_trangthai !== 'all' || !empty($search_keyword)) {
                                    echo "Không có tòa nhà nào phù hợp với bộ lọc đã chọn.";
                                } else {
                                    echo "Không có dữ liệu tòa nhà";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Popup chung cho thêm và sửa tòa nhà -->
    <div class="popup-overlay" id="buildingPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h2 id="popupTitle">Thêm tòa nhà mới</h2>
                <button class="close-popup" onclick="closeBuildingPopup()">&times;</button>
            </div>
            <form method="POST" id="buildingForm">
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="form-group">
                    <label for="ten_toa_nha">Tên tòa nhà:</label>
                    <input type="text" id="ten_toa_nha" name="ten_toa_nha" required placeholder="Nhập tên tòa nhà">
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
                    <button type="button" class="btn-cancel" onclick="closeBuildingPopup()">Hủy</button>
                    <button type="submit" name="add_building" id="submitBtn" class="btn-submit">Thêm tòa nhà</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let isEditMode = false;
        let searchTimeout = null;

        // Hiển thị toast notification
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
            
            // Hiệu ứng hiện
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Tự động ẩn sau 5 giây
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Hiển thị toast tự động từ PHP (nếu có)
        document.addEventListener('DOMContentLoaded', function() {
            const autoToast = document.getElementById('autoToast');
            if (autoToast) {
                setTimeout(() => autoToast.classList.add('show'), 100);
                setTimeout(() => {
                    autoToast.classList.remove('show');
                    setTimeout(() => autoToast.remove(), 300);
                }, 5000);
            }
        });

        // Reset filters
        function resetFilters() {
            window.location.href = 'toanha.php';
        }

        // Xử lý tìm kiếm real-time
        function handleSearchInput() {
            const searchInput = document.getElementById('search');
            const searchLoading = document.getElementById('searchLoading');
            const btnClearSearch = document.getElementById('btnClearSearch');
            const searchValue = searchInput.value.trim();
            
            // Hiển thị/ẩn nút xóa
            if (searchValue.length > 0) {
                btnClearSearch.style.display = 'block';
            } else {
                btnClearSearch.style.display = 'none';
            }
            
            // Xóa timeout cũ nếu có
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Nếu search rỗng, submit ngay lập tức
            if (searchValue === '') {
                submitSearch();
                return;
            }
            
            // Hiển thị loading
            searchLoading.style.display = 'block';
            
            // Đặt timeout để chờ người dùng nhập xong (debounce)
            searchTimeout = setTimeout(() => {
                submitSearch();
                searchLoading.style.display = 'none';
            }, 2000); // Chờ 500ms sau khi người dùng ngừng nhập
        }

        // Submit form tìm kiếm
        function submitSearch() {
            const searchInput = document.getElementById('search');
            const searchValue = searchInput.value.trim();
            
            // Lấy URL hiện tại và thêm/thay đổi tham số search
            const url = new URL(window.location.href);
            
            if (searchValue) {
                url.searchParams.set('search', searchValue);
            } else {
                url.searchParams.delete('search');
            }
            
            // Giữ lại các tham số filter khác
            const filterTinhtrang = document.getElementById('filter_tinhtrang').value;
            const filterTrangthai = document.getElementById('filter_trangthai').value;
            
            if (filterTinhtrang !== 'all') {
                url.searchParams.set('tinhtrang', filterTinhtrang);
            } else {
                url.searchParams.delete('tinhtrang');
            }
            
            if (filterTrangthai !== 'all') {
                url.searchParams.set('trangthai', filterTrangthai);
            } else {
                url.searchParams.delete('trangthai');
            }
            
            // Chuyển hướng đến URL mới
            window.location.href = url.toString();
        }

        // Xóa tìm kiếm
        function clearSearch() {
            const searchInput = document.getElementById('search');
            const btnClearSearch = document.getElementById('btnClearSearch');
            const searchLoading = document.getElementById('searchLoading');
            
            searchInput.value = '';
            btnClearSearch.style.display = 'none';
            searchLoading.style.display = 'none';
            
            // Submit ngay lập tức khi xóa
            submitSearch();
        }

        // Mở popup (thêm hoặc sửa)
        function openBuildingPopup(id = null, tenToaNha = '', tinhTrang = '', trangThaiHoatDong = '') {
            const popup = document.getElementById('buildingPopup');
            const title = document.getElementById('popupTitle');
            const form = document.getElementById('buildingForm');
            const tenToaNhaInput = document.getElementById('ten_toa_nha');
            const submitBtn = document.getElementById('submitBtn');
            const editIdInput = document.getElementById('edit_id');

            if (id) {
                // Chế độ sửa
                isEditMode = true;
                title.textContent = 'Sửa tòa nhà';
                editIdInput.value = id;
                tenToaNhaInput.value = tenToaNha;
                tenToaNhaInput.readOnly = true; // Không cho sửa tên tòa nhà
                document.getElementById('tinhtrang').value = tinhTrang;
                document.getElementById('trangthaihoatdong').value = trangThaiHoatDong;
                
                // Đổi tên nút submit
                submitBtn.name = 'edit_building';
                submitBtn.textContent = 'Cập nhật';
                submitBtn.className = 'btn-edit';
            } else {
                // Chế độ thêm
                isEditMode = false;
                title.textContent = 'Thêm tòa nhà mới';
                editIdInput.value = '';
                tenToaNhaInput.readOnly = false; // Cho phép nhập tên tòa nhà
                
                // Đặt lại tên nút submit
                submitBtn.name = 'add_building';
                submitBtn.textContent = 'Thêm tòa nhà';
                submitBtn.className = 'btn-submit';
            }

            popup.style.display = 'flex';
            tenToaNhaInput.focus();
        }

        // Đóng popup
        function closeBuildingPopup() {
            const popup = document.getElementById('buildingPopup');
            const form = document.getElementById('buildingForm');
            
            popup.style.display = 'none';
            form.reset();
            isEditMode = false;
        }

        // Đóng popup khi click bên ngoài
        document.getElementById('buildingPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBuildingPopup();
            }
        });

        // Đóng popup bằng phím ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBuildingPopup();
            }
        });

        // Xóa tòa nhà
        function deleteBuilding(id) {
            if (confirm('Bạn có chắc chắn muốn xóa tòa nhà này?')) {
                window.location.href = 'toanha.php?delete_id=' + id;
            }
        }
    </script>
</body>
</html>