<?php
require_once 'connect.php';
require_once 'session.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: trangadmin.php");
    } else {
        header("Location: khonggianuser.php");
    }
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($user_name)) {
        $errors['user_name'] = 'Vui lòng nhập tên đăng nhập!';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu!';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user WHERE user_name = ?");
            $stmt->execute([$user_name]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['hash'])) {
                createUserSession($user);
                if ($user['id_role'] == 1) {
                    header("Location: trangadmin.php");
                } else {
                    header("Location: khonggianuser.php");
                }
                exit();
            } else {
                $errors['general'] = 'Tên đăng nhập hoặc mật khẩu không đúng!';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="css/dangkynhap.css">
    <style>
        .error-message {
            color: #ff3333;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }
        .input-error {
            border-color: #ff3333 !important;
        }
    </style>
</head>
<body background="https://kenh14cdn.com/203336854389633024/2025/10/5/a8ec8a13632762d04906b2292ff6b4f4-1759662934261-17596629346172029202275.jpg" style="background-size: cover; background-position: center;">
    <div class="login-container">
        <h2>Đăng nhập</h2>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_name">Tên đăng nhập</label>
                <input type="text" id="user_name" name="user_name" 
                       class="<?php echo isset($errors['user_name']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($user_name ?? ''); ?>" 
                       autofocus>
                <?php if (isset($errors['user_name'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['user_name']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password"
                       class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['password'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn">Đăng nhập</button>
        </form>
        
        <div class="register-link">
            Chưa có tài khoản? <a href="dangky.php">Thì nhanh tay đăng ký ngay luôn thôi</a>
        </div>
    </div>
</body>
</html>