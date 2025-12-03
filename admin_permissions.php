<?php
require_once 'session.php';
require_once 'connect.php';

requireLogin();
$user = getCurrentUser();

// Kiểm tra quyền admin
if ($user['id_role'] != 1) {
    header("Location: khonggianuser.php");
    exit();
}

$success = '';
$error = '';

// Xử lý action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_system_role') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role = intval($_POST['new_role'] ?? 2);
        
        if ($user_id == $user['id_user']) {
            $error = 'Không thể thay đổi quyền hệ thống của chính mình.';
        } elseif ($user_id > 0 && in_array($new_role, [1, 2])) {
            $stmt = $pdo->prepare("UPDATE user SET id_role = ? WHERE id_user = ?");
            $stmt->execute([$new_role, $user_id]);
            $success = 'Đã cập nhật quyền hệ thống.';
            
            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'update_permission', ?)");
                $stmt->execute([
                    $user['id_user'],
                    "Thay đổi quyền hệ thống user ID {$user_id} thành " . ($new_role == 1 ? 'Admin' : 'User')
                ]);
            } catch (Exception $e) {}
        }
    }
    
    if ($action === 'toggle_permission') {
        header('Content-Type: application/json');
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        $permission_id = intval($_POST['permission_id'] ?? 0);
        
        if ($user_id > 0 && $project_id > 0 && $permission_id > 0) {
            try {
                // Lấy role của user trong project
                $stmt = $pdo->prepare("SELECT project_role FROM member WHERE id_user = ? AND id_project = ? AND flag = 'active'");
                $stmt->execute([$user_id, $project_id]);
                $member = $stmt->fetch();
                
                if (!$member) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thành viên']);
                    exit;
                }
                
                $role = $member['project_role'];
                
                // Kiểm tra quyền mặc định
                $stmt = $pdo->prepare("SELECT id_permission FROM project_role_permission WHERE project_role = ? AND id_permission = ?");
                $stmt->execute([$role, $permission_id]);
                $is_default_permission = $stmt->fetch() ? true : false;
                
                // Kiểm tra quyền tùy chỉnh (lấy record mới nhất)
                $stmt = $pdo->prepare("
                    SELECT * FROM member_permission 
                    WHERE id_user = ? AND id_project = ? AND id_permission = ?
                    ORDER BY granted_at DESC LIMIT 1
                ");
                $stmt->execute([$user_id, $project_id, $permission_id]);
                $latest_custom = $stmt->fetch();
                
                // Xác định trạng thái thực tế hiện tại
                $currently_has_permission = false;
                if ($latest_custom) {
                    // Nếu có record tùy chỉnh, ưu tiên theo flag của nó
                    $currently_has_permission = ($latest_custom['flag'] === 'active');
                } else {
                    // Nếu không có record tùy chỉnh, dựa vào quyền mặc định
                    $currently_has_permission = $is_default_permission;
                }
                
                if ($currently_has_permission) {
                    // Thu hồi quyền - insert record deactive mới
                    $stmt = $pdo->prepare("
                        INSERT INTO member_permission (id_user, id_project, id_permission, granted_by, flag, revoked_at) 
                        VALUES (?, ?, ?, ?, 'deactive', NOW())
                    ");
                    $stmt->execute([$user_id, $project_id, $permission_id, $user['id_user']]);
                    
                    // Log
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'revoke_permission', ?)");
                        $stmt->execute([
                            $user['id_user'],
                            "Thu hồi quyền permission ID {$permission_id} của user ID {$user_id} trong project ID {$project_id}"
                        ]);
                    } catch (Exception $e) {}
                    
                    echo json_encode(['success' => true, 'message' => 'Đã thu hồi quyền']);
                } else {
                    // Cấp quyền - insert record active mới
                    $stmt = $pdo->prepare("
                        INSERT INTO member_permission (id_user, id_project, id_permission, granted_by, flag) 
                        VALUES (?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([$user_id, $project_id, $permission_id, $user['id_user']]);
                    
                    // Log
                    try {
                        $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'grant_permission', ?)");
                        $stmt->execute([
                            $user['id_user'],
                            "Cấp quyền permission ID {$permission_id} cho user ID {$user_id} trong project ID {$project_id}"
                        ]);
                    } catch (Exception $e) {}
                    
                    echo json_encode(['success' => true, 'message' => 'Đã cấp quyền']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }
}

// Lấy danh sách người dùng với quyền hệ thống
$stmt = $pdo->query("
    SELECT u.*, r.role_name, r.description as role_description
    FROM user u
    JOIN role r ON u.id_role = r.id_role
    ORDER BY u.id_role ASC, u.`creat-at` DESC
");
$users = $stmt->fetchAll();

// Lấy danh sách tất cả thành viên trong các dự án (chỉ active)
$stmt = $pdo->query("
    SELECT m.*, 
           u.user_name, u.full_name, u.email,
           p.title as project_title, p.id_user as project_owner_id,
           owner.user_name as owner_name
    FROM member m
    JOIN user u ON m.id_user = u.id_user
    JOIN project p ON m.id_project = p.id_project
    JOIN user owner ON p.id_user = owner.id_user
    WHERE m.flag = 'active'
    ORDER BY m.id_project ASC, m.join_at DESC
");
$members = $stmt->fetchAll();

// Nhóm members theo project
$projects_members = [];
foreach ($members as $member) {
    $projects_members[$member['id_project']][] = $member;
}

// Lấy tất cả quyền tùy chỉnh (chỉ lấy record mới nhất của mỗi tổ hợp user-project-permission)
$custom_permissions = [];
$stmt = $pdo->query("
    SELECT mp1.* 
    FROM member_permission mp1
    INNER JOIN (
        SELECT id_user, id_project, id_permission, MAX(granted_at) as max_date
        FROM member_permission
        GROUP BY id_user, id_project, id_permission
    ) mp2 ON mp1.id_user = mp2.id_user 
        AND mp1.id_project = mp2.id_project 
        AND mp1.id_permission = mp2.id_permission
        AND mp1.granted_at = mp2.max_date
");
while ($row = $stmt->fetch()) {
    $key = $row['id_user'] . '_' . $row['id_project'] . '_' . $row['id_permission'];
    $custom_permissions[$key] = $row['flag']; // 'active' hoặc 'deactive'
}

// Lấy quyền mặc định theo vai trò từ CSDL
$default_role_permissions = [];
$stmt = $pdo->query("SELECT project_role, id_permission FROM project_role_permission");
while ($row = $stmt->fetch()) {
    $default_role_permissions[$row['project_role']][] = $row['id_permission'];
}

// Hàm kiểm tra xem user có quyền không
function hasPermission($user_id, $project_id, $permission_id, $role, $custom_permissions, $default_role_permissions) {
    $key = $user_id . '_' . $project_id . '_' . $permission_id;
    
    // Kiểm tra có record tùy chỉnh không
    if (isset($custom_permissions[$key])) {
        // Nếu có record tùy chỉnh, ưu tiên theo flag của nó
        return $custom_permissions[$key] === 'active';
    }
    
    // Nếu không có record tùy chỉnh, kiểm tra quyền mặc định
    if (isset($default_role_permissions[$role]) && in_array($permission_id, $default_role_permissions[$role])) {
        return true;
    }
    
    return false;
}

// Lấy danh sách quyền (permissions)
$stmt = $pdo->query("SELECT * FROM permission ORDER BY id_permision ASC");
$permissions = $stmt->fetchAll();

// Lấy danh sách project roles
$stmt = $pdo->query("SELECT * FROM project_role ORDER BY id_project_role ASC");
$project_roles = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->query("SELECT COUNT(*) as count FROM user WHERE id_role = 1");
$admin_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM user WHERE id_role = 2");
$user_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_project) as count FROM member WHERE flag = 'active'");
$active_projects = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM member WHERE flag = 'active'");
$active_members = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phân quyền - Admin</title>
    <link rel="stylesheet" href="css/admin_permissions.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Quản lý phân quyền</h1>
            <div style="display: flex; gap: 10px;">
                <a href="trangadmin.php" class="btn btn-secondary">Quay lại</a>
                <a href="dangxuat.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="notice notice-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Thống kê -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Quản trị viên</h3>
                <div class="stat-number"><?php echo $admin_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Người dùng</h3>
                <div class="stat-number"><?php echo $user_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Dự án có thành viên</h3>
                <div class="stat-number"><?php echo $active_projects; ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng thành viên</h3>
                <div class="stat-number"><?php echo $active_members; ?></div>
            </div>
        </div>
        

        
        <!-- Quản lý quyền chi tiết trong dự án -->
        <div class="section">
            <h2>Quản lý quyền chi tiết thành viên</h2>

            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 16px; height: 16px; background: #d4edda; border: 1px solid #28a745; border-radius: 3px;"></div>
                    <span style="font-size: 13px; color: #666;">Có quyền</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 16px; height: 16px; background: white; border: 1px solid #ddd; border-radius: 3px;"></div>
                    <span style="font-size: 13px; color: #666;">Không có quyền</span>
                </div>
            </div>
            
            <?php if (empty($projects_members)): ?>
                <p style="color: #666; text-align: center; padding: 40px 0;">
                    Chưa có thành viên nào trong các dự án.
                </p>
            <?php else: ?>
                <?php foreach ($projects_members as $project_id => $project_members): ?>
                    <?php $first_member = $project_members[0]; ?>
                    <div class="permission-card" style="margin-bottom: 30px;">
                        <h3 style="margin-bottom: 5px;">
                            <?php echo htmlspecialchars($first_member['project_title']); ?>
                        </h3>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                            Chủ dự án: <strong><?php echo htmlspecialchars($first_member['owner_name']); ?></strong>
                        </p>
                        
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="min-width: 150px;">Thành viên</th>
                                        <th>Vai trò</th>
                                        <?php foreach ($permissions as $perm): ?>
                                        <th style="min-width: 100px; font-size: 12px; text-align: center;" title="<?php echo htmlspecialchars($perm['description']); ?>">
                                            <?php echo htmlspecialchars($perm['description']); ?>
                                        </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($project_members as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['user_name']); ?></strong><br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($member['full_name']); ?></small>
                                        </td>
                                        <td style="min-width: 150px;">
                                            <span class="badge badge-<?php echo $member['project_role']; ?>" style="font-size: 11px;">
                                                <?php 
                                                $role_vn = [
                                                    'manager' => 'Quản lý',
                                                    'contributor' => 'Người đóng góp',
                                                    'commenter' => 'Bình luận',
                                                    'viewer' => 'Xem'
                                                ];
                                                echo htmlspecialchars($role_vn[$member['project_role']] ?? $member['project_role']); 
                                                ?>
                                            </span>
                                        </td>
                                        <?php foreach ($permissions as $perm): ?>
                                        <td style="text-align: center;">
                                            <?php 
                                            $key = $member['id_user'] . '_' . $project_id . '_' . $perm['id_permision'];
                                            $has_permission = hasPermission(
                                                $member['id_user'], 
                                                $project_id, 
                                                $perm['id_permision'], 
                                                $member['project_role'],
                                                $custom_permissions,
                                                $default_role_permissions
                                            );
                                            ?>
                                            <button 
                                                type="button" 
                                                class="permission-toggle <?php echo $has_permission ? 'active' : ''; ?>"
                                                data-user="<?php echo $member['id_user']; ?>"
                                                data-project="<?php echo $project_id; ?>"
                                                data-permission="<?php echo $perm['id_permision']; ?>"
                                                onclick="togglePermission(this)">
                                                <?php echo $has_permission ? '✓' : '—'; ?>
                                            </button>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        
    <div class="notice-container" id="noticeContainer"></div>
    
    <script>
    function togglePermission(btn) {
        const userId = btn.dataset.user;
        const projectId = btn.dataset.project;
        const permissionId = btn.dataset.permission;
        
        // Disable button
        btn.disabled = true;
        
        // Send AJAX request
        fetch('admin_permissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=toggle_permission&user_id=${userId}&project_id=${projectId}&permission_id=${permissionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Toggle button state
                if (btn.classList.contains('active')) {
                    btn.classList.remove('active');
                    btn.textContent = '—';
                } else {
                    btn.classList.add('active');
                    btn.textContent = '✓';
                }
                showNotice(data.message, 'success');
            } else {
                showNotice(data.message, 'error');
            }
            btn.disabled = false;
        })
        .catch(error => {
            showNotice('Lỗi kết nối', 'error');
            btn.disabled = false;
        });
    }
    
    function showNotice(message, type) {
        const container = document.getElementById('noticeContainer');
        const notice = document.createElement('div');
        notice.className = `notice-toast notice-${type}`;
        notice.textContent = message;
        container.appendChild(notice);
        
        setTimeout(() => {
            notice.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => notice.remove(), 300);
        }, 1000);
    }
    </script>
</body>
</html>
