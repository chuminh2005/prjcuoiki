<?php
require_once 'session.php';
require_once 'connect.php';

requireLogin();

if (isAdmin()) {
    header("Location: trangadmin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = getCurrentUser();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'private';
    
    $error = '';
    
    if (empty($title)) {
        $error = 'Vui lòng nhập tên dự án!';
    } elseif (strlen($title) < 3) {
        $error = 'Tên dự án phải có ít nhất 3 ký tự!';
    } elseif (empty($description)) {
        $error = 'Vui lòng nhập mô tả dự án!';
    } elseif (!in_array($visibility, ['public', 'private'])) {
        $error = 'Loại hiển thị không hợp lệ!';
    }
    
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO project (title, description, visibility, status, id_user, creat_at) 
                VALUES (?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([$title, $description, $visibility, $user['id_user']]);
            
            // Redirect về trang không gian user với thông báo thành công
            header("Location: khonggianuser.php?success=create");
            exit();
        } catch (PDOException $e) {
            $error = 'Lỗi khi tạo dự án: ' . $e->getMessage();
        }
    }
    
    // Nếu có lỗi, redirect về với thông báo lỗi
    if (!empty($error)) {
        $_SESSION['project_error'] = $error;
        header("Location: khonggianuser.php?error=create");
        exit();
    }
} else {
    header("Location: khonggianuser.php");
    exit();
}
