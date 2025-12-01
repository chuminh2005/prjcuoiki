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

// Tạo bảng activity_log nếu chưa có
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            id_user INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_detail TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_user) REFERENCES user(id_user) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Bảng đã tồn tại
}

// Lấy tham số lọc
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$limit = intval($_GET['limit'] ?? 50);

// Build query
$sql = "
    SELECT l.*, u.user_name, u.full_name 
    FROM activity_log l
    JOIN user u ON l.id_user = u.id_user
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.user_name LIKE ? OR u.full_name LIKE ? OR l.action_detail LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($action_filter)) {
    $sql .= " AND l.action_type = ?";
    $params[] = $action_filter;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(l.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(l.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY l.created_at DESC LIMIT " . intval($limit);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Thống kê
$stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) = CURDATE()");
$today_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_log WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_count = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_log");
$total_count = $stmt->fetch()['count'];

// Các loại hành động
$action_types = [
    'login' => 'Đăng nhập',
    'logout' => 'Đăng xuất',
    'create_project' => 'Tạo dự án',
    'edit_project' => 'Sửa dự án',
    'delete_project' => 'Xóa dự án',
    'create_content' => 'Tạo nội dung',
    'edit_content' => 'Sửa nội dung',
    'delete_content' => 'Xóa nội dung',
    'join_project' => 'Tham gia dự án',
    'leave_project' => 'Rời dự án',
    'other' => 'Khác'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử hoạt động - Admin</title>
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
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        
        .filter-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
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
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .log-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .log-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .log-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .action-login { background: #d4edda; color: #155724; }
        .action-logout { background: #fff3cd; color: #856404; }
        .action-create { background: #cce5ff; color: #004085; }
        .action-edit { background: #e2e3e5; color: #383d41; }
        .action-delete { background: #f8d7da; color: #721c24; }
        .action-other { background: #e7e7e7; color: #666; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Lịch sử hoạt động</h1>
            <div style="display: flex; gap: 10px;">
                <a href="trangadmin.php" class="btn btn-secondary">Quay lại</a>
                <a href="dangxuat.php" class="btn btn-danger">Đăng xuất</a>
            </div>
        </div>
        
        <!-- Thống kê -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Hôm nay</h3>
                <div class="stat-number"><?php echo $today_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>7 ngày qua</h3>
                <div class="stat-number"><?php echo $week_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng cộng</h3>
                <div class="stat-number"><?php echo $total_count; ?></div>
            </div>
        </div>
        
        <!-- Bộ lọc -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label>Tìm kiếm</label>
                    <input type="text" name="search" placeholder="Tên người dùng..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label>Loại hành động</label>
                    <select name="action">
                        <option value="">Tất cả</option>
                        <?php foreach ($action_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $action_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Từ ngày</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label>Đến ngày</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <label>Số bản ghi</label>
                    <select name="limit">
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="admin_history.php" class="btn btn-secondary">Xóa lọc</a>
                </div>
            </form>
        </div>
        
        <!-- Danh sách log -->
        <div class="section">
            <h2>Lịch sử hoạt động (<?php echo count($logs); ?> bản ghi)</h2>
            
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <p>Chưa có lịch sử hoạt động nào</p>
                    <p style="font-size: 13px; margin-top: 10px;">
                        Hệ thống sẽ tự động ghi lại các hoạt động của người dùng
                    </p>
                </div>
            <?php else: ?>
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Người dùng</th>
                            <th>Hành động</th>
                            <th>Chi tiết</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br>
                                <small style="color: #666;">@<?php echo htmlspecialchars($log['user_name']); ?></small>
                            </td>
                            <td>
                                <?php
                                $action_class = 'action-other';
                                if (strpos($log['action_type'], 'login') !== false) $action_class = 'action-login';
                                elseif (strpos($log['action_type'], 'logout') !== false) $action_class = 'action-logout';
                                elseif (strpos($log['action_type'], 'create') !== false) $action_class = 'action-create';
                                elseif (strpos($log['action_type'], 'edit') !== false) $action_class = 'action-edit';
                                elseif (strpos($log['action_type'], 'delete') !== false) $action_class = 'action-delete';
                                ?>
                                <span class="action-badge <?php echo $action_class; ?>">
                                    <?php echo $action_types[$log['action_type']] ?? $log['action_type']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['action_detail'] ?? '-'); ?></td>
                            <td><small><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
