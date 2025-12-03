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

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id == $user['id_user']) {
            $error = 'Không thể xóa tài khoản của chính mình.';
        } elseif ($user_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM user WHERE id_user = ? AND id_role != 1");
                $stmt->execute([$user_id]);
                $success = 'Đã xóa người dùng thành công.';
            } catch (PDOException $e) {
                $error = 'Không thể xóa người dùng này (có thể đang có dữ liệu liên quan).';
            }
        }
    }
    
    if ($action === 'update_role') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role = intval($_POST['new_role'] ?? 2);
        
        if ($user_id == $user['id_user']) {
            $error = 'Không thể thay đổi quyền của chính mình.';
        } elseif ($user_id > 0 && in_array($new_role, [1, 2])) {
            $stmt = $pdo->prepare("UPDATE user SET id_role = ? WHERE id_user = ?");
            $stmt->execute([$new_role, $user_id]);
            $success = 'Đã cập nhật quyền người dùng.';
        }
    }
}

// Lấy danh sách người dùng với phân trang
$search = $_GET['search'] ?? '';
$search_type = $_GET['search_type'] ?? 'username'; // username hoặc full_name
$page = intval($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Đếm tổng số người dùng (để phân trang)
$count_sql = "SELECT COUNT(*) as total FROM user u WHERE 1=1";
$count_params = [];

if (!empty($search)) {
    if ($search_type === 'username') {
        $count_sql .= " AND u.user_name LIKE ?";
        $count_params[] = "%{$search}%";
    } elseif ($search_type === 'full_name') {
        $count_sql .= " AND u.full_name LIKE ?";
        $count_params[] = "%{$search}%";
    }
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_users = $count_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Lấy danh sách người dùng
$sql = "SELECT u.*, r.role_name FROM user u JOIN role r ON u.id_role = r.id_role WHERE 1=1";
$params = [];

if (!empty($search)) {
    if ($search_type === 'username') {
        $sql .= " AND u.user_name LIKE ?";
        $params[] = "%{$search}%";
    } elseif ($search_type === 'full_name') {
        $sql .= " AND u.full_name LIKE ?";
        $params[] = "%{$search}%";
    }
}

$sql .= " ORDER BY u.`creat-at` DESC LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->query("SELECT COUNT(*) as count FROM user WHERE id_role = 1");
$admin_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM user WHERE id_role = 2");
$user_count = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Admin</title>
    <link rel="stylesheet" href="css/admin_users.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Quản lý người dùng</h1>
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
                <h3>Tổng cộng</h3>
                <div class="stat-number"><?php echo $admin_count + $user_count; ?></div>
            </div>
        </div>
        
        <!-- Bộ lọc -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>Loại tìm kiếm</label>
                    <select name="search_type">
                        <option value="username" <?php echo $search_type == 'username' ? 'selected' : ''; ?>>Username</option>
                        <option value="full_name" <?php echo $search_type == 'full_name' ? 'selected' : ''; ?>>Họ và tên</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Từ khóa</label>
                    <input type="text" name="search" placeholder="Nhập từ khóa tìm kiếm..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                    <a href="admin_users.php" class="btn btn-secondary">Xóa lọc</a>
                </div>
            </form>
        </div>
        
        <!-- Danh sách người dùng -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; padding: 0; border: none;">Danh sách người dùng (Trang <?php echo $page; ?>/<?php echo $total_pages; ?>)</h2>
                <span style="color: #666;">Hiển thị <?php echo count($users); ?> / <?php echo $total_users; ?> người dùng</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                Không tìm thấy người dùng nào
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id_user']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['user_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['id_role'] == 1 ? 'badge-admin' : 'badge-user'; ?>">
                                    <?php echo htmlspecialchars($u['role_name']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($u['creat-at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($u['id_user'] != $user['id_user']): ?>
                                        <!-- Đổi quyền -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id_user']; ?>">
                                            <input type="hidden" name="new_role" value="<?php echo $u['id_role'] == 1 ? 2 : 1; ?>">
                                            <button class="btn btn-success small" type="submit">
                                                <?php echo $u['id_role'] == 1 ? 'Hạ User' : 'Lên Admin'; ?>
                                            </button>
                                        </form>
                                        
                                        <!-- Xóa -->
                                        <?php if ($u['id_role'] != 1): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn chắc chắn muốn xóa người dùng này?')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id_user']; ?>">
                                            <button class="btn btn-danger small" type="submit">Xóa</button>
                                        </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 13px;">(Bạn)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                <?php
                $query_params = [];
                if (!empty($search)) $query_params['search'] = $search;
                if (!empty($search_type)) $query_params['search_type'] = $search_type;
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => 1])); ?>" class="btn btn-secondary small">« Đầu</a>
                    <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page - 1])); ?>" class="btn btn-secondary small">‹ Trước</a>
                <?php endif; ?>
                
                <span style="padding: 0 15px; color: #666; font-weight: 600;">
                    Trang <?php echo $page; ?> / <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page + 1])); ?>" class="btn btn-secondary small">Sau ›</a>
                    <a href="?<?php echo http_build_query(array_merge($query_params, ['page' => $total_pages])); ?>" class="btn btn-secondary small">Cuối »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
