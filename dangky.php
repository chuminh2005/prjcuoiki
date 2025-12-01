<?php
require_once 'connect.php';
require_once 'session.php';


// I. Hàm chung
function isRequired($value) {
    return !empty(trim($value));
}

function minLength($value, $min) {
    return strlen($value) >= $min;
}

function maxLength($value, $max) {
    return strlen($value) <= $max;
}

function noSpaces($value) {
    return strpos($value, ' ') === false;
}

// II. Hàm kiểm tra email
function isEmailFormat($value) {
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function isEmailAvailable($value, $listExistingEmails) {
    return !in_array($value, $listExistingEmails);
}

// III. Hàm kiểm tra mật khẩu
function hasNumber($value) {
    return preg_match('/[0-9]/', $value) === 1;
}

function hasLowerCase($value) {
    return preg_match('/[a-z]/', $value) === 1;
}

function hasUpperCase($value) {
    return preg_match('/[A-Z]/', $value) === 1;
}

function hasSpecialChar($value) {
    return preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value) === 1;
}

// IV. Hàm kiểm tra không toàn là...
function khongtoanso($value) {
    // Trả về true nếu KHÔNG phải toàn số
    return !preg_match('/^[0-9]+$/', $value);
}

function khongtoanchu($value) {
    // Trả về true nếu KHÔNG phải toàn chữ
    return !preg_match('/^[a-zA-Z]+$/', $value);
}

function khongtoankytudacbiet($value) {
    // Trả về true nếu KHÔNG phải toàn ký tự đặc biệt
    return !preg_match('/^[!@#$%^&*(),.?":{}|<>]+$/', $value);
}

// V. Hàm kiểm tra trùng khớp
function isMatch($value, $targetValue) {
    return $value === $targetValue;
}



// Kiểm tra đã đăng nhập chưa
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
    
    // Lấy danh sách email đã tồn tại
    $existingEmails = [];
    try {
        $stmt = $pdo->prepare("SELECT email FROM user");
        $stmt->execute();
        $existingEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $errors['general'] = 'Lỗi kết nối database!';
    }
    
    $validationConfig = [
        "email" => [
            ["func" => "isRequired", "args" => [$email], "msg" => "Vui lòng nhập email!"],
            ["func" => "noSpaces", "args" => [$email], "msg" => "Email không được chứa khoảng trắng!"],
            ["func" => "minLength", "args" => [$email, 3], "msg" => "Email phải có ít nhất 3 ký tự!"],
            ["func" => "maxLength", "args" => [$email, 255], "msg" => "Email không được vượt quá 255 ký tự!"],
            ["func" => "isEmailFormat", "args" => [$email], "msg" => "Email không đúng định dạng!"],
            ["func" => "isEmailAvailable", "args" => [$email, $existingEmails], "msg" => "Email đã được sử dụng!"]
        ],
        "password" => [
            ["func" => "isRequired", "args" => [$password], "msg" => "Vui lòng nhập mật khẩu!"],
            ["func" => "minLength", "args" => [$password, 6], "msg" => "Mật khẩu phải có ít nhất 6 ký tự!"],
            ["func" => "maxLength", "args" => [$password, 255], "msg" => "Mật khẩu không được vượt quá 255 ký tự!"],
            ["func" => "noSpaces", "args" => [$password], "msg" => "Mật khẩu không được chứa khoảng trắng!"],
            ["func" => "khongtoanso", "args" => [$password], "msg" => "Mật khẩu không được toàn số!"],
            ["func" => "khongtoanchu", "args" => [$password], "msg" => "Mật khẩu không được toàn chữ!"],
            ["func" => "khongtoankytudacbiet", "args" => [$password], "msg" => "Mật khẩu không được toàn ký tự đặc biệt!"],
            ["func" => "hasUpperCase", "args" => [$password], "msg" => "Mật khẩu phải có ít nhất 1 chữ hoa!"],
            ["func" => "hasNumber", "args" => [$password], "msg" => "Mật khẩu phải có ít nhất 1 chữ số!"],
            ["func" => "hasSpecialChar", "args" => [$password], "msg" => "Mật khẩu phải có ít nhất 1 ký tự đặc biệt!"]
        ],
        "confirm_password" => [
            ["func" => "isRequired", "args" => [$confirm_password], "msg" => "Vui lòng nhập lại mật khẩu!"],
            ["func" => "isMatch", "args" => [$confirm_password, $password], "msg" => "Mật khẩu xác nhận không khớp!"]
        ],
        "user_name" => [
            ["func" => "isRequired", "args" => [$user_name], "msg" => "Vui lòng nhập tên đăng nhập!"],
            ["func" => "minLength", "args" => [$user_name, 3], "msg" => "Tên đăng nhập phải có ít nhất 3 ký tự!"],
            ["func" => "maxLength", "args" => [$user_name, 50], "msg" => "Tên đăng nhập không được vượt quá 50 ký tự!"],
            ["func" => function($val) { return preg_match('/^[a-zA-Z0-9_]+$/', $val); }, "args" => [$user_name], "msg" => "Tên đăng nhập chỉ được chứa chữ, số và dấu gạch dưới!"]
        ],
        "full_name" => [
            ["func" => "isRequired", "args" => [$full_name], "msg" => "Vui lòng nhập họ và tên!"],
            ["func" => "minLength", "args" => [$full_name, 5], "msg" => "Họ và tên phải có ít nhất 5 ký tự!"],
            ["func" => function($val) { return preg_match('/^[a-zA-ZÀ-ỹ\s]+$/u', $val); }, "args" => [$full_name], "msg" => "Họ và tên chỉ được chứa chữ cái!"]
        ]
    ];
    
    // Vòng lặp validation: for (i=0; i++; i<n)
    foreach ($validationConfig as $element => $conditions) {
        $errorMessages = []; // Mảng chứa tất cả lỗi của element này
        
        // for (j=0; j++; j<m)
        foreach ($conditions as $condition) {
            // Gọi function ĐKj
            $func = $condition['func'];
            $args = $condition['args'];
            
            // Xử lý func là string (tên hàm) hoặc closure
            if (is_string($func)) {
                $result = call_user_func_array($func, $args);
            } else {
                $result = call_user_func_array($func, $args);
            }
            
            if (!$result) {
                $errorMessages[] = $condition['msg'];
            }
        }
        
        // Nếu có lỗi, format thành danh sách đẹp
        if (!empty($errorMessages)) {
            if (count($errorMessages) == 1) {
                // Nếu chỉ có 1 lỗi, hiển thị trực tiếp
                $errors[$element] = $errorMessages[0];
            } else {
                // Nếu có nhiều lỗi, hiển thị dạng danh sách
                $errors[$element] = '<ul class="error-list">' . 
                    implode('', array_map(function($msg) { 
                        return '<li>' . $msg . '</li>'; 
                    }, $errorMessages)) . 
                    '</ul>';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            // Kiểm tra username đã tồn tại chưa
            $stmt = $pdo->prepare("SELECT id_user FROM user WHERE user_name = ?");
            $stmt->execute([$user_name]);
            if ($stmt->fetch()) {
                $errors['user_name'] = 'Tên đăng nhập đã tồn tại!';
            } else {
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
            line-height: 1.5;
        }
        .error-list {
            margin: 5px 0 0 0;
            padding-left: 20px;
            list-style-type: none;
        }
        .error-list li {
            position: relative;
            padding-left: 15px;
            margin-bottom: 3px;
        }
        .error-list li:before {
            content: "• ";
            position: absolute;
            left: 0;
            color: #ff3333;
            font-weight: bold;
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
                    <span class="error-message"><?php echo $errors['user_name']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" 
                       class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?php echo $errors['email']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="full_name">Họ và tên</label>
                <input type="text" id="full_name" name="full_name" 
                       class="<?php echo isset($errors['full_name']) ? 'input-error' : ''; ?>"
                       value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <span class="error-message"><?php echo $errors['full_name']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password"
                       class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['password'])): ?>
                    <span class="error-message"><?php echo $errors['password']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="error-message"><?php echo $errors['confirm_password']; ?></span>
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