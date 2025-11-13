<?php
session_start();
require_once('../config.php');

header('Content-Type: application/json');

// Tắt hiển thị lỗi để tránh làm hỏng JSON
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

$contract_id = intval($_GET['id'] ?? 0);

if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid contract ID']);
    exit();
}

try {
    // Query để lấy chi tiết hợp đồng với thông tin sinh viên
    $query = "SELECT 
                hd.*,
                sv.masinhvien,
                sv.tensinhvien,
                sv.ngaysinh,
                sv.lop,
                sv.khoa
              FROM hopdong hd
              JOIN sinhvien sv ON hd.id_sinhvien = sv.idsinhvien
              WHERE hd.id_hopdong = ?";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $contract_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $contract = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $contract]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contract not found']);
    }

    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>