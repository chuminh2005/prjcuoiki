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

// check access: public or owner or active member
$stmt = $pdo->prepare("SELECT * FROM member WHERE id_project = ? AND id_user = ? AND flag = 'active'");
$stmt->execute([$project_id, $user['id_user']]);
$is_member = (bool)$stmt->fetch();
$is_owner = ($project['id_user'] == $user['id_user']);

if ($project['visibility'] == 'private' && !$is_owner && !$is_member) {
    header("Location: khonggianuser.php");
    exit();
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_content') {
        $title = trim($_POST['title'] ?? '');
        $detail = trim($_POST['detail'] ?? '');
        if (empty($title) && empty($detail)) {
            $error = 'Vui lòng nhập tiêu đề hoặc nội dung.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO content (id_project, id_parent, id_user, title, title_detail, status) VALUES (?, NULL, ?, ?, ?, 'pending')");
            $stmt->execute([$project_id, $user['id_user'], $title, $detail]);
            $success = 'Tạo nội dung thành công.';
            header("Location: project_detail.php?id={$project_id}");
            exit();
        }
    }

    if ($action === 'create_comment') {
        $parent = intval($_POST['parent_id'] ?? 0);
        $detail = trim($_POST['comment_detail'] ?? '');
        if ($parent <= 0 || empty($detail)) {
            $error = 'Nội dung bình luận trống.';
        } else {
            // insert comment as content with id_parent
            $stmt = $pdo->prepare("INSERT INTO content (id_project, id_parent, id_user, title, title_detail, status) VALUES (?, ?, ?, '', ?, 'accept')");
            $stmt->execute([$project_id, $parent, $user['id_user'], $detail]);
            $success = 'Bình luận đã được thêm.';
            header("Location: project_detail.php?id={$project_id}");
            exit();
        }
    }

    if ($action === 'edit_content') {
        $content_id = intval($_POST['content_id'] ?? 0);
        $title = trim($_POST['edit_title'] ?? '');
        $detail = trim($_POST['edit_detail'] ?? '');
        if ($content_id <= 0) { $error = 'Lỗi nội dung.'; }
        else {
            // check permission: owner of content or project owner
            $stmt = $pdo->prepare("SELECT id_user FROM content WHERE id_content = ?");
            $stmt->execute([$content_id]);
            $c = $stmt->fetch();
            if (!$c) { $error = 'Nội dung không tồn tại.'; }
            elseif ($c['id_user'] != $user['id_user'] && !$is_owner) { $error = 'Bạn không có quyền sửa.'; }
            else {
                $stmt = $pdo->prepare("UPDATE content SET title = ?, title_detail = ? WHERE id_content = ?");
                $stmt->execute([$title, $detail, $content_id]);
                $success = 'Cập nhật nội dung thành công.';
                header("Location: project_detail.php?id={$project_id}");
                exit();
            }
        }
    }

    if ($action === 'delete_content') {
        $content_id = intval($_POST['content_id'] ?? 0);
        if ($content_id <= 0) { $error = 'Lỗi nội dung.'; }
        else {
            $stmt = $pdo->prepare("SELECT id_user FROM content WHERE id_content = ?");
            $stmt->execute([$content_id]);
            $c = $stmt->fetch();
            if (!$c) { $error = 'Nội dung không tồn tại.'; }
            elseif ($c['id_user'] != $user['id_user'] && !$is_owner) { $error = 'Bạn không có quyền xóa.'; }
            else {
                // cascading will delete comments due to FK
                $stmt = $pdo->prepare("DELETE FROM content WHERE id_content = ?");
                $stmt->execute([$content_id]);
                $success = 'Đã xóa nội dung.';
                header("Location: project_detail.php?id={$project_id}");
                exit();
            }
        }
    }
}

// fetch main contents (id_parent IS NULL)
$stmt = $pdo->prepare("SELECT c.*, u.full_name, u.user_name FROM content c JOIN user u ON c.id_user = u.id_user WHERE c.id_project = ? AND c.id_parent IS NULL ORDER BY c.created_at DESC");
$stmt->execute([$project_id]);
$contents = $stmt->fetchAll();

// helper to fetch comments for a content
function fetch_comments($pdo, $parent_id) {
    $stmt = $pdo->prepare("SELECT c.*, u.full_name, u.user_name FROM content c JOIN user u ON c.id_user = u.id_user WHERE c.id_parent = ? ORDER BY c.created_at ASC");
    $stmt->execute([$parent_id]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chi tiết dự án - <?php echo htmlspecialchars($project['title']); ?></title>
    <link rel="stylesheet" href="./css/project_detail.css">
    <style> .notice { padding:10px; border-radius:6px; margin-bottom:12px; } .notice-success{background:#e6ffed;color:#05642a} .notice-error{background:#fff0f0;color:#7a1515} </style>
</head>
<body class="project-detail">
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($project['title']); ?></h1>
            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">Quay lại</a>
                <div class="meta">
                    <small>Trạng thái: <?php echo htmlspecialchars($project['status']); ?></small>
                </div>
            </div>
        </div>

        <?php if ($success): ?><div class="notice notice-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="layout">
            <main>
                <div class="card">
                    <h2>Nội dung</h2>

                    <!-- Create content form -->
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="create_content">
                        <div class="form-group">
                            <input type="text" name="title" placeholder="Tiêu đề (tùy chọn)" />
                        </div>
                        <div class="form-group">
                            <textarea name="detail" placeholder="Nội dung..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Thêm nội dung</button>
                        </div>
                    </form>

                    <div class="content-list">
                        <?php if (empty($contents)): ?>
                            <p>Chưa có nội dung nào trong dự án này.</p>
                        <?php else: ?>
                            <?php foreach ($contents as $c): ?>
                                <div class="content-item card" id="content-<?php echo $c['id_content']; ?>">
                                    <div class="content-meta">
                                        <div><strong><?php echo htmlspecialchars($c['full_name']); ?></strong> • <?php echo htmlspecialchars($c['user_name']); ?></div>
                                        <div><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></div>
                                    </div>
                                    <div class="content-title"><?php echo htmlspecialchars($c['title']); ?></div>
                                    <div class="content-body"><?php echo nl2br(htmlspecialchars($c['title_detail'])); ?></div>

                                    <div class="content-actions">
                                        <?php if ($c['id_user'] == $user['id_user'] || $is_owner): ?>
                                            <button class="btn btn-secondary small" onclick="toggleEdit(<?php echo $c['id_content']; ?>)">Sửa</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn chắc chắn muốn xóa?')">
                                                <input type="hidden" name="action" value="delete_content">
                                                <input type="hidden" name="content_id" value="<?php echo $c['id_content']; ?>">
                                                <button class="btn btn-danger small" type="submit">Xóa</button>
                                            </form>
                                        <?php endif; ?>

                                        <button class="btn btn-primary small" onclick="toggleCommentForm(<?php echo $c['id_content']; ?>)">Bình luận</button>
                                    </div>

                                    <div class="inline-form hide" id="edit-form-<?php echo $c['id_content']; ?>">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="edit_content">
                                            <input type="hidden" name="content_id" value="<?php echo $c['id_content']; ?>">
                                            <div class="form-group">
                                                <input type="text" name="edit_title" value="<?php echo htmlspecialchars($c['title']); ?>" />
                                            </div>
                                            <div class="form-group">
                                                <textarea name="edit_detail"><?php echo htmlspecialchars($c['title_detail']); ?></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button class="btn btn-primary" type="submit">Lưu</button>
                                                <button type="button" class="btn btn-secondary" onclick="toggleEdit(<?php echo $c['id_content']; ?>)">Hủy</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="inline-form hide" id="comment-form-<?php echo $c['id_content']; ?>">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_comment">
                                            <input type="hidden" name="parent_id" value="<?php echo $c['id_content']; ?>">
                                            <div class="form-row">
                                                <textarea name="comment_detail" placeholder="Viết bình luận..."></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button class="btn btn-primary" type="submit">Gửi</button>
                                                <button type="button" class="btn btn-secondary" onclick="toggleCommentForm(<?php echo $c['id_content']; ?>)">Hủy</button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php $comments = fetch_comments($pdo, $c['id_content']); ?>
                                    <?php if (!empty($comments)): ?>
                                        <div class="comment-list">
                                            <?php foreach ($comments as $cm): ?>
                                                <div class="comment-item">
                                                    <div class="comment-meta"><strong><?php echo htmlspecialchars($cm['full_name']); ?></strong> • <?php echo date('d/m/Y H:i', strtotime($cm['created_at'])); ?></div>
                                                    <div class="comment-body"><?php echo nl2br(htmlspecialchars($cm['title_detail'])); ?></div>
                                                    <?php if ($cm['id_user'] == $user['id_user'] || $is_owner): ?>
                                                        <div style="margin-top:6px;">
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa bình luận?')">
                                                                <input type="hidden" name="action" value="delete_content">
                                                                <input type="hidden" name="content_id" value="<?php echo $cm['id_content']; ?>">
                                                                <button class="btn btn-danger small" type="submit">Xóa</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

                

            <aside class="sidebar">
                <div class="card">
                    <h3>Thông tin dự án</h3>
                    <p class="meta-label">Mô tả</p>
                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                    <p class="meta-label">Chủ dự án</p>
                    <?php
                        $stmt = $pdo->prepare("SELECT user_name, full_name FROM user WHERE id_user = ?");
                        $stmt->execute([$project['id_user']]);
                        $owner = $stmt->fetch();
                    ?>
                    <p><?php echo htmlspecialchars($owner['full_name']); ?> (<?php echo htmlspecialchars($owner['user_name']); ?>)</p>
                </div>

                <div class="card sidebar">
                    <h3>Thành viên</h3>
                    <div class="member-list">
                        <?php
                        $stmt = $pdo->prepare("SELECT m.*, u.full_name FROM member m JOIN user u ON m.id_user = u.id_user WHERE m.id_project = ? AND m.flag = 'active' ORDER BY m.join_at DESC");
                        $stmt->execute([$project_id]);
                        $members = $stmt->fetchAll();
                        foreach ($members as $mem):
                        ?>
                            <div class="member-item"><div><?php echo htmlspecialchars($mem['full_name']); ?></div><div><?php echo htmlspecialchars($mem['project_role']); ?></div></div>
                        <?php endforeach; ?>
                        <?php if (empty($members)): ?><p>Không có thành viên.</p><?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    </div>

    <script>
        function toggleEdit(id){
            var el = document.getElementById('edit-form-'+id);
            if(!el) return;
            el.classList.toggle('hide');
        }
        function toggleCommentForm(id){
            var el = document.getElementById('comment-form-'+id);
            if(!el) return;
            el.classList.toggle('hide');
        }
    </script>
</body>
</html>