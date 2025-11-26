<?php

require_once 'connect.php'; 
require_once 'session.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = $_GET['q'] ?? '';

if (strlen($keyword) < 1) {
    echo json_encode([]);
    exit;
}

try {
    // Tìm user_name, email, trừ user hiện tại (nếu cần)
    $stmt = $pdo->prepare("SELECT user_name, email, full_name FROM user WHERE (user_name LIKE :kw OR email LIKE :kw) LIMIT 5");
    $stmt->execute(['kw' => "%$keyword%"]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);
} catch (Exception $e) {

    echo json_encode(['error' => 'Database error']); 
}
?>