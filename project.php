<?php
require_once 'session.php';
require_once 'connect.php';

requireLogin();

$user = getCurrentUser();
$project_id = $_GET['id'] ?? 0;

// Kiểm tra project tồn tại
$stmt = $pdo->prepare("SELECT p.*, u.full_name as owner_name FROM project p JOIN user u ON p.id_user = u.id_user WHERE p.id_project = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: khonggianuser.php");
    exit();
}

// Kiểm tra quyền user
$is_owner = ($project['id_user'] == $user['id_user']);
$stmt = $pdo->prepare("SELECT project_role FROM member WHERE id_project = ? AND id_user = ?");
$stmt->execute([$project_id, $user['id_user']]);
$member = $stmt->fetch();
$user_role = $member ? $member['project_role'] : null;

// Kiểm tra permission truy cập dự án
if ($project['visibility'] == 'private' && !$is_owner && !$user_role) {
    header("Location: khonggianuser.php");
    exit();
}

// Xác định quyền
$can_settings = $is_owner || $user_role == 'manager';
$can_create_content = $is_owner || in_array($user_role, ['manager', 'contributor']);
$can_approve = $is_owner || $user_role == 'manager';

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member WHERE id_project = ?");
$stmt->execute([$project_id]);
$member_count = $stmt->fetch()['count'];

$success = '';
$error = '';

// Xử lý POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Tạo nội dung mới
    if ($action === 'create_content') {
        if (!$can_create_content) {
            $error = 'Bạn không có quyền tạo nội dung.';
        } else {
            $title = trim($_POST['title'] ?? '');
            $detail = trim($_POST['detail'] ?? '');
            
            if (empty($title) && empty($detail)) {
                $error = 'Vui lòng nhập tiêu đề hoặc nội dung.';
            } else {
                $status = $can_approve ? 'accept' : 'pending';
                $stmt = $pdo->prepare("INSERT INTO content (id_project, id_parent, id_user, title, title_detail, status) VALUES (?, NULL, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $user['id_user'], $title, $detail, $status]);
                $success = 'Tạo nội dung thành công' . ($status == 'pending' ? ' (đang chờ duyệt)' : '') . '.';
                header("Location: project.php?id={$project_id}");
                exit();
            }
        }
    }

    // Duyệt nội dung
    if ($action === 'approve_content') {
        if (!$can_approve) {
            $error = 'Bạn không có quyền duyệt nội dung.';
        } else {
            $content_id = intval($_POST['content_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE content SET status = 'accept' WHERE id_content = ? AND id_project = ? AND id_parent IS NULL");
            $stmt->execute([$content_id, $project_id]);
            $success = 'Đã duyệt nội dung.';
            header("Location: project.php?id={$project_id}");
            exit();
        }
    }

    // Từ chối nội dung
    if ($action === 'reject_content') {
        if (!$can_approve) {
            $error = 'Bạn không có quyền từ chối nội dung.';
        } else {
            $content_id = intval($_POST['content_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE content SET status = 'reject' WHERE id_content = ? AND id_project = ?");
            $stmt->execute([$content_id, $project_id]);
            $success = 'Đã từ chối nội dung.';
            header("Location: project.php?id={$project_id}");
            exit();
        }
    }

    // Sửa nội dung
    if ($action === 'edit_content') {
        $content_id = intval($_POST['content_id'] ?? 0);
        $title = trim($_POST['edit_title'] ?? '');
        $detail = trim($_POST['edit_detail'] ?? '');
        
        $stmt = $pdo->prepare("SELECT id_user FROM content WHERE id_content = ? AND id_project = ?");
        $stmt->execute([$content_id, $project_id]);
        $c = $stmt->fetch();
        
        if (!$c) {
            $error = 'Nội dung không tồn tại.';
        } elseif ($c['id_user'] != $user['id_user'] && !$is_owner && $user_role != 'manager') {
            $error = 'Bạn không có quyền sửa nội dung này.';
        } else {
            $stmt = $pdo->prepare("UPDATE content SET title = ?, title_detail = ? WHERE id_content = ?");
            $stmt->execute([$title, $detail, $content_id]);
            $success = 'Cập nhật nội dung thành công.';
            header("Location: project.php?id={$project_id}");
            exit();
        }
    }

    // Xóa nội dung
    if ($action === 'delete_content') {
        $content_id = intval($_POST['content_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT id_user, id_parent FROM content WHERE id_content = ? AND id_project = ?");
        $stmt->execute([$content_id, $project_id]);
        $c = $stmt->fetch();
        
        if (!$c) {
            $error = 'Nội dung không tồn tại.';
        } elseif ($c['id_user'] != $user['id_user'] && !$is_owner && $user_role != 'manager') {
            $error = 'Bạn không có quyền xóa nội dung này.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM content WHERE id_content = ?");
            $stmt->execute([$content_id]);
            $success = 'Đã xóa nội dung.';
            header("Location: project.php?id={$project_id}");
            exit();
        }
    }

}

// Lấy nội dung đã duyệt
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.user_name 
    FROM content c 
    JOIN user u ON c.id_user = u.id_user 
    WHERE c.id_project = ? AND c.id_parent IS NULL AND c.status = 'accept'
    ORDER BY c.created_at DESC
");
$stmt->execute([$project_id]);
$accepted_contents = $stmt->fetchAll();

// Lấy nội dung chờ duyệt (chỉ owner và manager)
$pending_contents = [];
if ($can_approve) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name, u.user_name 
        FROM content c 
        JOIN user u ON c.id_user = u.id_user 
        WHERE c.id_project = ? AND c.id_parent IS NULL AND c.status = 'pending'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$project_id]);
    $pending_contents = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?></title>
    <link rel="stylesheet" href="./css/khonggianuser.css">
    <link rel="stylesheet" href="./css/project.css">

</head>
<body background="https://vietbookstore.com/cdn/shop/articles/13-facts-about-doraemon-doraemon-1694511477.jpg?v=1720975103&width=1100" style="background-size: cover; background-position: center;">
    <div class="top-bar">
        <a href="khonggianuser.php" class="btn btn-secondary">
             Không gian của tôi
        </a>
        
        <?php if ($can_settings): ?>
            <a href="project_settings.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
                 Cài đặt dự án
            </a>
        <?php endif; ?>
    </div>
    
    <div class="project-container">
        <?php if ($success): ?>
            <div class="notice notice-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="project-header">
            <div class="project-title-section">
                <div style="width: 100%;">
                    <h1><?php echo htmlspecialchars($project['title']); ?></h1>
                    <p style="color: #666; margin: 10px 0 0 0;">
                        <?php echo htmlspecialchars($project['description']); ?>
                    </p>
                </div>
            </div>
            
            <div class="project-meta-info">
                <div class="meta-item">
                    <span><strong>Chủ sở hữu:</strong> <?php echo htmlspecialchars($project['owner_name']); ?></span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Thành viên:</strong> <?php echo $member_count + 1; ?></span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Trạng thái:</strong></span>
                    <span class="status-badge status-<?php echo $project['status']; ?>">
                        <?php 
                        $status_text = [
                            'active' => 'Hoạt động',
                            'disactive' => 'Lưu trữ',
                            'freeze' => 'Không hoạt động'
                        ];
                        echo $status_text[$project['status']];
                        ?>
                    </span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Hiển thị:</strong></span>
                    <span><?php echo $project['visibility'] == 'public' ? 'Công khai' : 'Riêng tư'; ?></span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Ngày tạo:</strong> <?php echo date('d/m/Y', strtotime($project['creat_at'])); ?></span>
                </div>
                
                <?php if ($user_role): ?>
                <div class="meta-item">
                    <span><strong>Vai trò của bạn:</strong></span>
                    <span class="status-badge" style="background: #e3f2fd; color: #1976d2;">
                        <?php 
                        $role_names = [
                            'manager' => 'Người điều hành',
                            'contributor' => 'Người đóng góp',
                            'commenter' => 'Người bình luận',
                            'viewer' => 'Người quan sát'
                        ];
                        echo $role_names[$user_role];
                        ?>
                    </span>
                </div>
                <?php elseif ($is_owner): ?>
                <div class="meta-item">
                    <span class="status-badge" style="background: #ffd700; color: #000;">
                        Chủ sở hữu
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($can_create_content): ?>
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" onclick="toggleCreateForm()" style="font-size: 16px; padding: 12px 24px;">Tạo nội dung mới</button>
        </div>
        
        <div class="create-content-form hide" id="create-content-form">
            <h2 style="margin: 0 0 20px 0; color: #333;">Thêm nội dung mới</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_content">
                
                <div class="form-group">
                    <label for="title">Tiêu đề (tùy chọn):</label>
                    <input type="text" id="title" name="title" placeholder="Nhập tiêu đề nội dung...">
                </div>
                
                <div class="form-group">
                    <label for="detail">Nội dung chi tiết: <span style="color: red;">*</span></label>
                    <textarea id="detail" name="detail" placeholder="Nhập nội dung chi tiết..." required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Tạo nội dung</button>
                    <button type="reset" class="btn btn-secondary">Làm mới</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleCreateForm()">Đóng</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Khối toàn bộ nội dung dự án (đã duyệt) -->
        <div style="margin-bottom: 20px;">
            <h2 style="color: #333; font-size: 20px; margin-bottom: 15px; padding-left: 5px;">
                Bảng tin dự án 
                <span style="font-size: 14px; font-weight: normal; color: #666;">
                    (<?php echo count($accepted_contents); ?> bài viết)
                </span>
            </h2>
        </div>
            
        <?php if (empty($accepted_contents)): ?>
            <div class="content-section">
                <div class="empty-state">
                    <p>Chưa có nội dung nào được duyệt trong dự án này.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($accepted_contents as $content): ?>
                <div class="content-item" id="content-<?php echo $content['id_content']; ?>">
                    <!-- Header: Thông tin người đăng -->
                    <div class="content-header">
                        <div class="content-meta">
                            <strong style="color: #333;"><?php echo htmlspecialchars($content['full_name']); ?></strong>
                            <span>•</span>
                            <span style="color: #8b8b8b; font-size: 12px;"><?php echo date('d/m/Y H:i', strtotime($content['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Main: Nội dung bài viết -->
                    <div class="content-main">
                        <?php if (!empty($content['title'])): ?>
                            <div class="content-title"><?php echo htmlspecialchars($content['title']); ?></div>
                        <?php endif; ?>
                        
                        <div class="content-body"><?php echo nl2br(htmlspecialchars($content['title_detail'])); ?></div>
                    </div>
                    
                    <!-- Footer: Các nút hành động -->
                    <div class="content-footer">
                        <div class="content-actions">
                            <?php if ($content['id_user'] == $user['id_user'] || $is_owner || $user_role == 'manager'): ?>
                                <button class="btn btn-secondary small" onclick="toggleEdit(<?php echo $content['id_content']; ?>)">Sửa</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn chắc chắn muốn xóa nội dung này?')">
                                    <input type="hidden" name="action" value="delete_content">
                                    <input type="hidden" name="content_id" value="<?php echo $content['id_content']; ?>">
                                    <button class="btn btn-danger small" type="submit">Xóa</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Form sửa nội dung -->
                        <div class="inline-form hide" id="edit-form-<?php echo $content['id_content']; ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="edit_content">
                                <input type="hidden" name="content_id" value="<?php echo $content['id_content']; ?>">
                                
                                <div class="form-group">
                                    <label>Tiêu đề:</label>
                                    <input type="text" name="edit_title" value="<?php echo htmlspecialchars($content['title']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Nội dung:</label>
                                    <textarea name="edit_detail" required><?php echo htmlspecialchars($content['title_detail']); ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button class="btn btn-success" type="submit">Lưu</button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleEdit(<?php echo $content['id_content']; ?>)">Hủy</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Khối nội dung chờ duyệt (chỉ owner và manager) -->
        <?php if ($can_approve && !empty($pending_contents)): ?>
        <div class="content-section">
            <h2>
                <span>Nội dung chờ duyệt</span>
                <span style="font-size: 14px; font-weight: normal; color: #666;">
                    (<?php echo count($pending_contents); ?> nội dung)
                </span>
            </h2>
            
            <?php foreach ($pending_contents as $content): ?>
                <div class="content-item pending">
                    <div class="content-meta">
                        <span><strong><?php echo htmlspecialchars($content['full_name']); ?></strong> (@<?php echo htmlspecialchars($content['user_name']); ?>)</span>
                        <span><?php echo date('d/m/Y H:i', strtotime($content['created_at'])); ?></span>
                        <span style="background: #fff3cd; padding: 3px 10px; border-radius: 12px; font-weight: 600;">Chờ duyệt</span>
                    </div>
                    
                    <?php if (!empty($content['title'])): ?>
                        <div class="content-title"><?php echo htmlspecialchars($content['title']); ?></div>
                    <?php endif; ?>
                    
                    <div class="content-body"><?php echo nl2br(htmlspecialchars($content['title_detail'])); ?></div>
                    
                    <div class="content-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="approve_content">
                            <input type="hidden" name="content_id" value="<?php echo $content['id_content']; ?>">
                            <button class="btn btn-success small" type="submit">Duyệt</button>
                        </form>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Từ chối nội dung này?')">
                            <input type="hidden" name="action" value="reject_content">
                            <input type="hidden" name="content_id" value="<?php echo $content['id_content']; ?>">
                            <button class="btn btn-danger small" type="submit">Từ chối</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleCreateForm() {
            var el = document.getElementById('create-content-form');
            if (!el) return;
            el.classList.toggle('hide');
            if (!el.classList.contains('hide')) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        function toggleEdit(id) {
            var el = document.getElementById('edit-form-' + id);
            if (!el) return;
            el.classList.toggle('hide');
        }
    </script>
</body>
</html>
