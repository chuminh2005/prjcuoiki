<?php
$host = 'vn1.loadip.com';
$dbname = 'coll5txb_projectcnw';
$username = 'coll5txb_minh';
$password = 'Minh04102005';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}
?>