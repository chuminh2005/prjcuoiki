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

// Lấy danh sách người dùng
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT u.*, r.role_name FROM user u JOIN role r ON u.id_role = r.id_role WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.user_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $sql .= " AND u.id_role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY u.`creat-at` DESC";

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            font-size: 28px;
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary { background: #4a90e2; color: white; }
        .btn-primary:hover { background: #357abd; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn.small { padding: 6px 12px; font-size: 13px; }
        
        .notice {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .notice-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .notice-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #4a90e2;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        
        .badge-user {
            background: #28a745;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
    </style>
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
                    <label>Tìm kiếm</label>
                    <input type="text" name="search" placeholder="Tên, email, username..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>Vai trò</label>
                    <select name="role">
                        <option value="">Tất cả</option>
                        <option value="1" <?php echo $role_filter == '1' ? 'selected' : ''; ?>>Admin</option>
                        <option value="2" <?php echo $role_filter == '2' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="admin_users.php" class="btn btn-secondary">Xóa lọc</a>
                </div>
            </form>
        </div>
        
        <!-- Danh sách người dùng -->
        <div class="section">
            <h2>Danh sách người dùng (<?php echo count($users); ?>)</h2>
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
        </div>
    </div>
</body>
</html>
