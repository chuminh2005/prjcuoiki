<?php 
require_once 'session.php';
require_once 'connect.php';

requireLogin();
if (isAdmin()) {
    header("Location: trangadmin.php");
    exit();
}

$user = getCurrentUser();

$stmt = $pdo->prepare("
    SELECT p.*, COUNT(DISTINCT m.id_member) as member_count 
    FROM project p 
    LEFT JOIN member m ON p.id_project = m.id_project 
    WHERE p.id_user = ? 
    GROUP BY p.id_project 
    ORDER BY p.creat_at DESC
");
$stmt->execute([$user['id_user']]);
$myProjects = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, m.project_role, u.full_name as owner_name,
           COUNT(DISTINCT m2.id_member) as member_count, m.join_at
    FROM member m
    JOIN project p ON m.id_project = p.id_project
    JOIN user u ON p.id_user = u.id_user
    LEFT JOIN member m2 ON p.id_project = m2.id_project
    WHERE m.id_user = ? AND p.id_user != ? AND m.flag = 'active'
    GROUP BY p.id_project, p.id_user, p.title, p.description, p.status, p.visibility, p.creat_at, m.project_role, u.full_name, m.join_at
    ORDER BY m.join_at DESC
");
$stmt->execute([$user['id_user'], $user['id_user']]);
$joinedProjects = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT 'content' as type, c.title, c.created_at, p.title as project_title, 
           u.full_name as user_name, p.id_project
    FROM content c
    JOIN project p ON c.id_project = p.id_project
    JOIN user u ON c.id_user = u.id_user
    WHERE (p.id_user = ? OR c.id_project IN (
        SELECT id_project FROM member WHERE id_user = ?
    ))
    AND c.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute([$user['id_user'], $user['id_user']]);
$notifications = $stmt->fetchAll();

// Xử lý thông báo
$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] == 'create') {
    $success_message = 'Tạo dự án thành công!';
}

if (isset($_GET['error']) && $_GET['error'] == 'create' && isset($_SESSION['project_error'])) {
    $error_message = $_SESSION['project_error'];
    unset($_SESSION['project_error']);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không gian của tôi</title>
    <link rel="stylesheet" href="./css/khonggianuser.css">
    <style>
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body background="https://vietbookstore.com/cdn/shop/articles/13-facts-about-doraemon-doraemon-1694511477.jpg?v=1720975103&width=1100" style="background-size: cover; background-position: center;">
    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error" id="errorAlert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="header">
            <h1> Không gian của tôi</h1>
            <div class="header-right">
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                </div>
                <button class="btn btn-secondary" onclick="openModal('profileModal')">
                     Cài đặt 
                </button>
                <a href="dangxuat.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
        
        <div class="grid">
            <div>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; border: none; padding: 0;"> Dự án của tôi (<?php echo count($myProjects); ?>)</h2>
                        <button class="btn btn-primary" onclick="openModal('createProjectModal')">
                             Tạo dự án
                        </button>
                    </div>
                    
                    <div class="project-list">
                        <?php if (empty($myProjects)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px;"></div>
                                <p>Bạn chưa có dự án nào</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($myProjects as $project): ?>
                                <a href="project.php?id=<?php echo $project['id_project']; ?>" style="text-decoration: none; color: inherit; display: block;">
                                    <div class="project-item" style="cursor: pointer; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)';" onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                        <div class="project-header">
                                            <div class="project-title">
                                                <?php echo htmlspecialchars($project['title']); ?>
                                            </div>
                                        <span class="project-status status-<?php echo $project['status']; ?>">
                                            <?php 
                                            echo $project['status'] == 'active' ? 'Hoạt động' : 
                                                ($project['status'] == 'freeze' ? 'Lưu trữ' : ' Không hoạt động'); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="project-desc">
                                        <?php echo htmlspecialchars($project['description']); ?>
                                    </div>
                                    <div class="project-meta">
                                        <span>
                                            <?php echo $project['visibility'] == 'public' ? ' Công khai' : ' Riêng tư'; ?>
                                        </span>
                                        <span> <?php echo date('d/m/Y', strtotime($project['creat_at'])); ?></span>
                                    </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 30px;">
                    <h2> Dự án tham gia (<?php echo count($joinedProjects); ?>)</h2>
                    
                    <div class="project-list">
                        <?php if (empty($joinedProjects)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px;"></div>
                                <p>Bạn chưa tham gia dự án nào</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($joinedProjects as $project): ?>
                                <a href="project.php?id=<?php echo $project['id_project']; ?>" style="text-decoration: none; color: inherit; display: block;">
                                    <div class="project-item" style="cursor: pointer; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)';" onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                        <div class="project-header">
                                        <div class="project-title">
                                            <?php echo htmlspecialchars($project['title']); ?>
                                            <span class="project-role">
                                                <?php 
                                                $roleNames = [
                                                    'manager' => ' Quản lý',
                                                    'contributor' => ' Đóng góp',
                                                    'commenter' => ' Bình luận',
                                                    'viewer' => ' Xem'
                                                ];
                                                echo $roleNames[$project['project_role']] ?? $project['project_role'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-desc">
                                        <?php echo htmlspecialchars($project['description']); ?>
                                    </div>
                                    <div class="project-meta">
                                        <span> Chủ sở hữu: <?php echo htmlspecialchars($project['owner_name']); ?></span>

                                    </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h2>Thông báo</h2>
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <div style="font-size: 48px;"></div>
                            <p>Không có thông báo mới</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($notif['user_name']); ?></strong>
                                    đã thêm nội dung 
                                    <strong>"<?php echo htmlspecialchars($notif['title']); ?>"</strong>
                                    vào dự án 
                                    <strong><?php echo htmlspecialchars($notif['project_title']); ?></strong>
                                </div>
                                <div class="notification-time">
                                    <?php 
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 3600) {
                                        echo floor($diff / 60) . ' phút trước';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . ' giờ trước';
                                    } else {
                                        echo floor($diff / 86400) . ' ngày trước';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div id="createProjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tạo dự án mới</h3>
                <button class="close-modal" onclick="closeModal('createProjectModal')">&times;</button>
            </div>
            
            <form method="POST" action="create_project.php">
                <div class="form-group">
                    <label for="title">Tên dự án *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả *</label>
                    <textarea id="description" name="description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="visibility">Hiển thị *</label>
                    <select id="visibility" name="visibility" required>
                        <option value="public"> Công khai</option>
                        <option value="private"> Riêng tư</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Tạo dự án</button>
            </form>
        </div>
    </div>
    
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3> Thông tin cá nhân</h3>
                <button class="close-modal" onclick="closeModal('profileModal')">&times;</button>
            </div>
            
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" value="<?php echo htmlspecialchars($user['user_name']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
            </div>
            
            <div class="form-group">
                <label>Họ và tên</label>
                <input type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
            </div>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">
            
            <h4 style="margin-bottom: 20px; color: #333;"> Đổi mật khẩu</h4>
            
            <form method="POST" action="change_password.php" id="changePasswordForm">
                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới *</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_new_password">Xác nhận mật khẩu mới *</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                
                <button type="submit" class="btn btn-secondary" style="width: 100%;">Đổi mật khẩu</button>
            </form>
        </div>
    </div>  
<script src="./js/khonggianuser.js"></script>
</body>
</html>