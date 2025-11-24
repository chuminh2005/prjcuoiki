<?php

    session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id_user' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'id_role' => $_SESSION['id_role']
        ];
    }
    return null;
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['id_role'] == 1;
}
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: dangnhap.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die("Bạn không có quyền truy cập trang này!");
    }
}

function createUserSession($user) {
    $_SESSION['user_id'] = $user['id_user'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['id_role'] = $user['id_role'];
}

function destroyUserSession() {
    session_unset();
    session_destroy();
}
?>