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

// Xử lý AJAX lấy thông tin nội quy theo ID
if (isset($_GET['action']) && $_GET['action'] == 'get_noiquy' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    
    try {
        $sql = "SELECT * FROM noiquy WHERE idnoiquy = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $noiquy = $result->fetch_assoc();
            echo json_encode(['success' => true, 'noiquy' => $noiquy]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy nội quy']);
        }
        $stmt->close();
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

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
    <style>
        /* Additional styles for modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.3s ease;
            background: none;
            border: none;
            z-index: 1001;
        }

        .close:hover {
            color: #e74c3c;
        }

        .modal-content h2 {
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            text-align: left;
            padding: 20px 30px 0;
        }

        #noiquyForm {
            padding: 0 30px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Inter', sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.5;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .form-actions button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .form-actions button[type="submit"] {
            background: linear-gradient(135deg, #27ae60, #219a52);
            color: white;
        }

        .form-actions button[type="submit"]:hover {
            background: linear-gradient(135deg, #219a52, #1e8449);
            transform: translateY(-2px);
        }

        .form-actions button[type="button"] {
            background: #95a5a6;
            color: white;
        }

        .form-actions button[type="button"]:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px;
            }
            
            #noiquyForm {
                padding: 0 20px 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions button {
                width: 100%;
            }
        }
    </style>
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
                                value="<?= htmlspecialchars($search_keyword) ?>">
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
                    <textarea name="noidungnoiquy" id="noidungnoiquyInput" rows="4" required 
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

    async function editNoiquy(id) {
        try {
            // Hiển thị loading state
            document.getElementById('modalTitle').textContent = 'Đang tải...';
            
            const response = await fetch(`?action=get_noiquy&id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('modalTitle').textContent = 'Sửa nội quy';
                document.getElementById('addBtn').style.display = 'none';
                document.getElementById('updateBtn').style.display = 'block';
                document.getElementById('noiquyModal').style.display = 'block';
                
                // Điền dữ liệu vào form
                document.getElementById('noiquyId').value = data.noiquy.idnoiquy;
                document.getElementById('noidungnoiquyInput').value = data.noiquy.noidungnoiquy || '';
                document.getElementById('xulyviphamInput').value = data.noiquy.xulyvipham || '';
                document.getElementById('ghichuInput').value = data.noiquy.ghichu || '';
            } else {
                alert('Không thể tải dữ liệu nội quy: ' + data.message);
            }
        } catch (error) {
            console.error('Lỗi:', error);
            alert('Lỗi khi tải dữ liệu nội quy');
        }
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

    // Xử lý sự kiện submit form để validate
    document.getElementById('noiquyForm').addEventListener('submit', function(e) {
        const noidung = document.getElementById('noidungnoiquyInput').value.trim();
        if (!noidung) {
            e.preventDefault();
            alert('Vui lòng nhập nội dung nội quy');
            document.getElementById('noidungnoiquyInput').focus();
        }
    });
    </script>
</body>
</html>