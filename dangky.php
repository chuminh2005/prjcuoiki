<?php
require_once 'connect.php';
require_once 'session.php';
// kiểm tra người dùng đã đc đăng nhập chưa, nếu rồi thì chuyển hướng theo vai trò
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: trangadmin.php");
    } else {
        header("Location: khonggianuser.php");
    }
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    
    if (empty($user_name)) {
        $errors['user_name'] = 'Vui lòng nhập tên đăng nhập!';
    } elseif (strlen($user_name) < 3) {
        $errors['user_name'] = 'Tên đăng nhập phải có ít nhất 3 ký tự!';
    } elseif (strlen($user_name) > 50) {
        $errors['user_name'] = 'Tên đăng nhập không được vượt quá 50 ký tự!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $user_name)) {
        $errors['user_name'] = 'Tên đăng nhập chỉ được chứa chữ, số và dấu gạch dưới!';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ!';
    } elseif (!preg_match('/@gmail\.com$/i', $email)) {
        $errors['email'] = 'Email phải kết thúc bằng @gmail.com!';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,}@/', $email)) {
        $errors['email'] = 'Phần trước @ phải có ít nhất 3 ký tự ';
    } elseif (strlen($email) > 100) {
        $errors['email'] = 'Email không được vượt quá 100 ký tự!';
    }
    
    if (empty($full_name)) {
        $errors['full_name'] = 'Vui lòng nhập họ và tên!';
    } elseif (strlen($full_name) < 5) {
        $errors['full_name'] = 'Họ và tên phải có ít nhất 5 ký tự!';
    } elseif (!preg_match('/^[a-zA-ZÀ-ỹ\s]+$/u', $full_name)) {
        $errors['full_name'] = 'Họ và tên chỉ được chứa chữ cái!';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu!';
    } elseif (strlen($password) < 3) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 3 ký tự!';
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Vui lòng nhập xác nhận mật khẩu!';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp!';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_user FROM user WHERE user_name = ?");
            $stmt->execute([$user_name]);
            if ($stmt->fetch()) {
                $errors['user_name'] = 'Tên đăng nhập đã tồn tại!';
            }
            
            $stmt = $pdo->prepare("SELECT id_user FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email đã được sử dụng!';
            }
            
            if (empty($errors)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO user (user_name, email, hash, full_name, id_role) VALUES (?, ?, ?, ?, 2)");
                $stmt->execute([$user_name, $email, $hash, $full_name]);
                
                $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
                $user_name = $email = $full_name = '';
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
    <title>Đăng ký</title>
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
    <div class="register-container">
        <h2>Đăng ký tài khoản</h2>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="user_name">Tên đăng nhập</label>
                <input type="text" id="user_name" name="user_name" 
                       class="<?php echo isset($errors['user_name']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($user_name ?? ''); ?>">
                <?php if (isset($errors['user_name'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['user_name']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" 
                       class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="full_name">Họ và tên</label>
                <input type="text" id="full_name" name="full_name" 
                       class="<?php echo isset($errors['full_name']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['full_name']); ?></span>
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
            
            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn">Đăng ký</button>
        </form>
        
        <div class="login-link">
            Đã có tài khoản? <a href="dangnhap.php">Thì đăng nhập thôi chứ chần chờ gì nữa</a>
        </div>
    </div>
</body>
</html>