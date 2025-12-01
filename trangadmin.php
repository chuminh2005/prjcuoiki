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

// Lấy thống kê
$stmt = $pdo->query("SELECT COUNT(*) as count FROM user WHERE id_role = 2");
$total_users = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM project");
$total_projects = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM content WHERE id_parent IS NULL");
$total_contents = $stmt->fetch()['count'];

// Lấy danh sách user gần đây
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name 
    FROM user u 
    JOIN role r ON u.id_role = r.id_role 
    ORDER BY u.`creat-at` DESC 
    LIMIT 10
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống</title>
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
        
        .admin-nav {
            display: flex;
            gap: 10px;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #4a90e2;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border-left: 4px solid #4a90e2;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .menu-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .menu-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Quản trị hệ thống</h1>
            <div class="admin-nav">
                <a href="dangxuat.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
        
        <!-- Thống kê -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Tổng người dùng</h3>
                <div class="stat-number"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng dự án</h3>
                <div class="stat-number"><?php echo $total_projects; ?></div>
            </div>

        </div>
        
        <!-- Menu chức năng -->
        <div class="menu-grid">
            <div class="menu-card">
                <h3>Quản lý tài khoản</h3>
                <p>Xem, thêm, sửa, xóa tài khoản người dùng trong hệ thống</p>
                <a href="admin_users.php" class="btn btn-primary">Quản lý người dùng</a>
            </div>
            
            <div class="menu-card">
                <h3>Quản lý phân quyền</h3>
                <p>Phân quyền hệ thống cho người dùng (Admin/User)</p>
                <a href="admin_permissions.php" class="btn btn-success">Phân quyền</a>
            </div>
            
            <div class="menu-card">
                <h3>Lịch sử hoạt động</h3>
                <p>Tra cứu lịch sử đăng nhập và hoạt động của người dùng</p>
                <a href="admin_history.php" class="btn btn-warning">Xem lịch sử</a>
            </div>
            
            <div class="menu-card">
                <h3>Bảo trì hệ thống</h3>
                <p>Sao lưu dữ liệu, khôi phục database, bảo trì hệ thống</p>
                <a href="admin_maintenance.php" class="btn btn-danger">Bảo trì</a>
            </div>
        </div>
        
        <!-- Người dùng gần đây -->
        <div class="section">
            <h2>Người dùng đăng ký gần đây</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $u): ?>
                    <tr>
                        <td><?php echo $u['id_user']; ?></td>
                        <td><?php echo htmlspecialchars($u['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $u['id_role'] == 1 ? 'badge-admin' : 'badge-user'; ?>">
                                <?php echo htmlspecialchars($u['role_name']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($u['creat-at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>