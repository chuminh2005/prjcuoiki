<?php
require_once 'session.php';
require_once 'connect.php';

requireLogin();

$user = getCurrentUser();
$project_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM project WHERE id_project = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: khonggianuser.php");
    exit();
}

$is_owner = ($project['id_user'] == $user['id_user']);
$stmt = $pdo->prepare("SELECT project_role FROM member WHERE id_project = ? AND id_user = ?");
$stmt->execute([$project_id, $user['id_user']]);
$member = $stmt->fetch();
$is_manager = $member && $member['project_role'] == 'manager';

if (!$is_owner && !$is_manager) {
    header("Location: khonggianuser.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    // update thông tin dự án
    if ($action == 'update_info' && $is_owner) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visibility = $_POST['visibility'] ?? 'private';
        
        if (empty($title)) {
            $error = 'Tên dự án không được để trống!';
        } elseif (strlen($title) < 3) {
            $error = 'Tên dự án phải có ít nhất 3 ký tự!';
        } elseif (empty($description)) {
            $error = 'Mô tả dự án không được để trống!';
        } elseif (!in_array($visibility, ['public', 'private'])) {
            $error = 'Hiển thị không hợp lệ!';
        } else {
            $stmt = $pdo->prepare("UPDATE project SET title = ?, description = ?, visibility = ? WHERE id_project = ?");
            $stmt->execute([$title, $description, $visibility, $project_id]);
            $success = 'Cập nhật thông tin dự án thành công!';
            
            $stmt = $pdo->prepare("SELECT * FROM project WHERE id_project = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch();
        }
    }

    // thêm thành viên mới
    if ($action == 'add_member' && ($is_owner || $is_manager)) {
        $user_identifier = trim($_POST['user_identifier'] ?? '');
        $project_role = $_POST['project_role'] ?? 'viewer';
    
        if (empty($user_identifier)) {
            $error = 'Vui lòng nhập tên đăng nhập hoặc email!';
        } elseif (!in_array($project_role, ['viewer', 'commenter', 'contributor', 'manager'])) {
            $error = 'Vai trò không hợp lệ!';
        } else {
            $stmt = $pdo->prepare("SELECT id_user, user_name, email FROM user WHERE user_name = ? OR email = ?");
            $stmt->execute([$user_identifier, $user_identifier]);
            $target_user = $stmt->fetch();
            
            if (!$target_user) {
                $error = 'Không tìm thấy người dùng!';
            } elseif ($target_user['id_user'] == $project['id_user']) {
                $error = 'Không thể thêm chủ dự án vào danh sách thành viên!';
            } else {
                $stmt = $pdo->prepare("SELECT id_member FROM member WHERE id_project = ? AND id_user = ? AND flag = 'active'"); // Lấy bản ghi đang hoạt động
                $stmt->execute([$project_id, $target_user['id_user']]);
                if ($stmt->fetch()) {
                    $error = 'Người dùng đã là thành viên của dự án!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO member (id_user, id_project, project_role) VALUES (?, ?, ?)");
                    $stmt->execute([$target_user['id_user'], $project_id, $project_role]);
                    $success = 'Thêm thành viên thành công!';
                }
            }
        }
    }
    // xử lý theo hướng cập nhật thêm 1 bản ghi mới chứ không sửa bản ghi cũ, không update
    if ($action == 'update_role' && ($is_owner || $is_manager)) {
        $member_id = $_POST['member_id'] ?? 0;
        $new_role = $_POST['new_role'] ?? 'viewer';

        if (!in_array($new_role, ['viewer', 'commenter', 'contributor', 'manager'])) {
            $error = 'Vai trò không hợp lệ!';
        } else {
            try {
                $pdo->beginTransaction();

                // Lấy thông tin bản ghi member cũ (chỉ khi đang active)
                $stmt = $pdo->prepare("SELECT id_user FROM member WHERE id_member = ? AND id_project = ? AND flag = 'active' FOR UPDATE");
                $stmt->execute([$member_id, $project_id]);
                $old = $stmt->fetch();

                if (!$old) {
                    $pdo->rollBack();
                    $error = 'Thành viên không tồn tại hoặc đã bị xóa!';
                } else {
                    // Deactive bản ghi cũ
                    $stmt = $pdo->prepare("UPDATE member SET flag = 'deactive', end_at = current_timestamp() WHERE id_member = ? AND id_project = ?");
                    $stmt->execute([$member_id, $project_id]);

                    // Thêm bản ghi mới với vai trò mới (join_at mặc định là current_timestamp)
                    $stmt = $pdo->prepare("INSERT INTO member (id_user, id_project, project_role) VALUES (?, ?, ?)");
                    $stmt->execute([$old['id_user'], $project_id, $new_role]);

                    $pdo->commit();
                    $success = 'Cập nhật vai trò thành công!';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Lỗi khi cập nhật vai trò: ' . $e->getMessage();
            }
        }
    }
    
    
    if ($action == 'remove_member' && ($is_owner || $is_manager)) {
        $member_id = $_POST['member_id'] ?? 0;
        
        $stmt = $pdo->prepare("UPDATE member SET flag = 'deactive', end_at = current_timestamp() WHERE id_member = ? AND id_project = ?"); // Cập nhật trạng thái thành 'deactive' chứ không delete
        $stmt->execute([$member_id, $project_id]);
        $success = 'Xóa thành viên thành công!';
    }
    
    if ($action == 'update_status' && $is_owner) {
        $new_status = $_POST['status'] ?? 'active';
        
        $stmt = $pdo->prepare("UPDATE project SET status = ? WHERE id_project = ?");
        $stmt->execute([$new_status, $project_id]);
        $success = 'Cập nhật trạng thái dự án thành công!';
        
        $stmt = $pdo->prepare("SELECT * FROM project WHERE id_project = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
    }
    
    if ($action == 'delete_project' && $is_owner) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("DELETE FROM member WHERE id_project = ?");
            $stmt->execute([$project_id]);
            
            $stmt = $pdo->prepare("DELETE FROM content WHERE id_project = ?");
            $stmt->execute([$project_id]);
            
            $stmt = $pdo->prepare("DELETE FROM project WHERE id_project = ?");
            $stmt->execute([$project_id]);
            
            $pdo->commit();
            
            header("Location: khonggianuser.php?success=delete");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Lỗi khi xóa dự án: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("
    SELECT m.*, u.user_name, u.email, u.full_name 
    FROM member m 
    JOIN user u ON m.id_user = u.id_user 
    WHERE m.id_project = ? AND m.flag = 'active'
    ORDER BY m.join_at DESC
");
$stmt->execute([$project_id]);
$members = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt dự án</title>
    <link rel="stylesheet" href="./css/khonggianuser.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .settings-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #4a90e2;
            text-decoration: none;
        }
        
        .settings-grid {
            display: grid;
            gap: 20px;
        }
        
        .settings-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-card h2 {
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .member-list {
            margin-top: 20px;
        }
        
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-info strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }
        
        .member-info small {
            color: #666;
            font-size: 13px;
        }
        
        .member-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-manager { background: #e3f2fd; color: #1976d2; }
        .role-contributor { background: #f3e5f5; color: #7b1fa2; }
        .role-commenter { background: #fff3e0; color: #f57c00; }
        .role-viewer { background: #e0e0e0; color: #616161; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4a90e2;
            color: white;
        }
        
        .btn-primary:hover {
            background: #357abd;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .danger-zone {
            border: 2px solid #dc3545;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .danger-zone h3 {
            color: #dc3545;
            margin-top: 0;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
        }
        
        .input-group input,
        .input-group select {
            flex: 1;
        }
    </style>
</head>
<body background="https://vietbookstore.com/cdn/shop/articles/13-facts-about-doraemon-doraemon-1694511477.jpg?v=1720975103&width=1100" style="background-size: cover; background-position: center;">
    <div class="settings-container">
        <div class="settings-header">
            <h1> Cài đặt dự án</h1>
            <div class="breadcrumb">
                <a href="khonggianuser.php">Không gian của tôi</a> / 
                <a href="project.php?id=<?php echo $project_id; ?>"><?php echo htmlspecialchars($project['title']); ?></a> / 
                Cài đặt
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" id="errorAlert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <?php if ($is_owner): ?>
            <div class="settings-card">
                <h2> Thông tin dự án</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="form-group">
                        <label for="title">Tên dự án *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Mô tả *</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($project['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="visibility">Hiển thị *</label>
                        <select id="visibility" name="visibility">
                            <option value="public" <?php echo $project['visibility'] == 'public' ? 'selected' : ''; ?>> Công khai</option>
                            <option value="private" <?php echo $project['visibility'] == 'private' ? 'selected' : ''; ?>> Riêng tư</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"> Lưu thay đổi</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="settings-card">
                <h2>Thành viên dự án</h2>
                
                <?php if ($is_owner || $is_manager): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_member">
                    
                    <div class="form-group">
                        <label>Thêm thành viên mới</label>
                        <div class="input-group">
                            <input type="text" name="user_identifier" placeholder="Nhập tên đăng nhập hoặc email">
                            <select name="project_role">
                                <option value="viewer"> Người quan sát</option>
                                <option value="commenter"> Người bình luận</option>
                                <option value="contributor"> Người đóng góp</option>
                                <option value="manager">Người điều hành</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Thêm</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <div class="member-list">
                    <div class="member-item">
                        <div class="member-info">
                            <strong> <?php 
                                $stmt = $pdo->prepare("SELECT full_name, user_name, email FROM user WHERE id_user = ?");
                                $stmt->execute([$project['id_user']]);
                                $owner = $stmt->fetch();
                                echo htmlspecialchars($owner['full_name']); 
                            ?></strong>
                            <small><?php echo htmlspecialchars($owner['user_name']); ?> • <?php echo htmlspecialchars($owner['email']); ?></small>
                        </div>
                        <span class="role-badge" style="background: #ffd700; color: #000;">Chủ dự án</span>
                    </div>
                    
                    <?php if (empty($members)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">Chưa có thành viên nào</p>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <div class="member-item">
                                <div class="member-info">
                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                    <small><?php echo htmlspecialchars($member['user_name']); ?> • <?php echo htmlspecialchars($member['email']); ?></small>
                                    <small style="display: block; margin-top: 5px;">Tham gia: <?php echo date('d/m/Y', strtotime($member['join_at'])); ?></small>
                                </div>
                                <div class="member-actions">
                                    <?php
                                    $role_names = [
                                        'manager' => ' Người điều hành',
                                        'contributor' => ' Người đóng góp',
                                        'commenter' => ' Người bình luận',
                                        'viewer' => ' Người quan sát'
                                    ];
                                    $role_class = 'role-' . $member['project_role'];
                                    ?>
                                    
                                    <?php if ($is_owner || $is_manager): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="member_id" value="<?php echo $member['id_member']; ?>">
                                            <select name="new_role" onchange="this.form.submit()" class="btn btn-sm btn-secondary">
                                                <option value="viewer" <?php echo $member['project_role'] == 'viewer' ? 'selected' : ''; ?>>Quan sát</option>
                                                <option value="commenter" <?php echo $member['project_role'] == 'commenter' ? 'selected' : ''; ?>>Bình luận</option>
                                                <option value="contributor" <?php echo $member['project_role'] == 'contributor' ? 'selected' : ''; ?>>Đóng góp</option>
                                                <option value="manager" <?php echo $member['project_role'] == 'manager' ? 'selected' : ''; ?>>Điều hành</option>
                                            </select>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa thành viên này?')">
                                            <input type="hidden" name="action" value="remove_member">
                                            <input type="hidden" name="member_id" value="<?php echo $member['id_member']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"> Xóa</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="role-badge <?php echo $role_class; ?>">
                                            <?php echo $role_names[$member['project_role']]; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="settings-card">
                <h2>Vai trò trong dự án</h2>
                <div style="line-height: 1.8;">

                </div>
            </div>
            
            <?php if ($is_owner): ?>
            <div class="settings-card">
                <h2> Trạng thái dự án</h2>
                <p style="margin-bottom: 15px;">Trạng thái hiện tại: <strong>
                    <?php 
                    $status_names = [
                        'active' => ' Hoạt động',
                        'disactive' => ' Lưu trữ',
                        'freeze' => ' Không hoạt động'
                    ];
                    echo $status_names[$project['status']];
                    ?>
                </strong></p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="status-buttons">
                        <button type="submit" name="status" value="active" class="btn btn-success"> Hoạt động</button>
                        <button type="submit" name="status" value="disactive" class="btn btn-warning"> Lưu trữ</button>
                        <button type="submit" name="status" value="freeze" class="btn btn-secondary"> Không hoạt động</button>
                    </div>
                </form>
                
                <div class="danger-zone">
                    <form method="POST" onsubmit="return confirm(' BẠN CÓ CHẮC CHẮN MUỐN XÓA DỰ ÁN NÀY?')">
                        <input type="hidden" name="action" value="delete_project">
                        <button type="submit" class="btn btn-danger"> Xóa dự án vĩnh viễn</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        setTimeout(function() {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 2000);
    </script>
</body>
</html>
