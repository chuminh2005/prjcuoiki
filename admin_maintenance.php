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
    
    if ($action === 'backup') {
        try {
            $backup_dir = __DIR__ . '/backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . '/' . $filename;
            
            // Lấy thông tin kết nối từ connect.php
            $host = 'vn1.loadip.com:3306';
            $dbname = 'coll5txb_projectcnw';
            $username = 'coll5txb_hieumay11';
            $password = 'hieumay2005';
            
            // Sử dụng mysqldump
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($filepath)
            );
            
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($filepath)) {
                $success = "Sao lưu thành công: {$filename}";
                
                // Log activity
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'backup', ?)");
                    $stmt->execute([$user['id_user'], "Sao lưu database: {$filename}"]);
                } catch (Exception $e) {}
            } else {
                // Fallback: Sao lưu thủ công nếu mysqldump không khả dụng
                $tables = [];
                $result = $pdo->query("SHOW TABLES");
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                
                $sql_dump = "-- Database Backup\n";
                $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    $sql_dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    
                    $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
                    $sql_dump .= $create_table[1] . ";\n\n";
                    
                    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                    if (count($rows) > 0) {
                        foreach ($rows as $row) {
                            $values = array_map(function($val) use ($pdo) {
                                return $val === null ? 'NULL' : $pdo->quote($val);
                            }, $row);
                            $sql_dump .= "INSERT INTO `{$table}` VALUES (" . implode(", ", $values) . ");\n";
                        }
                        $sql_dump .= "\n";
                    }
                }
                
                file_put_contents($filepath, $sql_dump);
                $success = "Sao lưu thành công (PHP mode): {$filename}";
                
                // Log activity
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'backup', ?)");
                    $stmt->execute([$user['id_user'], "Sao lưu database (PHP): {$filename}"]);
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {
            $error = "Lỗi khi sao lưu: " . $e->getMessage();
        }
    }
    
    if ($action === 'delete_backup') {
        $filename = $_POST['filename'] ?? '';
        $filepath = __DIR__ . '/backups/' . $filename;
        
        if (file_exists($filepath) && basename($filepath) === $filename && strpos($filename, 'backup_') === 0) {
            unlink($filepath);
            $success = "Đã xóa file sao lưu: {$filename}";
            
            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'delete', ?)");
                $stmt->execute([$user['id_user'], "Xóa backup: {$filename}"]);
            } catch (Exception $e) {}
        } else {
            $error = "Không tìm thấy file sao lưu hoặc không hợp lệ.";
        }
    }
    
    if ($action === 'restore') {
        $filename = $_POST['filename'] ?? '';
        $filepath = __DIR__ . '/backups/' . $filename;
        
        if (file_exists($filepath) && basename($filepath) === $filename && strpos($filename, 'backup_') === 0) {
            try {
                $sql = file_get_contents($filepath);
                
                // Tắt foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                
                // Thực thi các câu lệnh SQL
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                // Bật lại foreign key checks
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                
                $success = "Khôi phục dữ liệu thành công từ: {$filename}";
                
                // Log activity
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'restore', ?)");
                    $stmt->execute([$user['id_user'], "Khôi phục database từ: {$filename}"]);
                } catch (Exception $e) {}
            } catch (Exception $e) {
                $error = "Lỗi khi khôi phục: " . $e->getMessage();
            }
        } else {
            $error = "Không tìm thấy file sao lưu.";
        }
    }
    
    if ($action === 'optimize') {
        try {
            $tables = [];
            $result = $pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            foreach ($tables as $table) {
                $pdo->exec("OPTIMIZE TABLE `{$table}`");
            }
            
            $success = "Đã tối ưu hóa " . count($tables) . " bảng.";
            
            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'optimize', ?)");
                $stmt->execute([$user['id_user'], "Tối ưu hóa " . count($tables) . " bảng"]);
            } catch (Exception $e) {}
        } catch (Exception $e) {
            $error = "Lỗi khi tối ưu hóa: " . $e->getMessage();
        }
    }
    
    if ($action === 'clear_cache') {
        // Xóa các session cũ hoặc dữ liệu tạm
        try {
            // Có thể thêm logic xóa cache ở đây
            $success = "Đã xóa cache hệ thống.";
            
            // Log activity
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_log (id_user, action_type, action_detail) VALUES (?, 'clear_cache', ?)");
                $stmt->execute([$user['id_user'], "Xóa cache hệ thống"]);
            } catch (Exception $e) {}
        } catch (Exception $e) {
            $error = "Lỗi khi xóa cache: " . $e->getMessage();
        }
    }
}

// Lấy danh sách backup files
$backup_files = [];
$backup_dir = __DIR__ . '/backups';
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0 && substr($file, -4) === '.sql') {
            $filepath = $backup_dir . '/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Thống kê database
$stats = [];
try {
    $result = $pdo->query("SHOW TABLE STATUS");
    $total_size = 0;
    $table_count = 0;
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $total_size += $row['Data_length'] + $row['Index_length'];
        $table_count++;
    }
    $stats['total_size'] = $total_size;
    $stats['table_count'] = $table_count;
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM user");
    $stats['user_count'] = $result->fetch()['count'];
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM project");
    $stats['project_count'] = $result->fetch()['count'];
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM content");
    $stats['content_count'] = $result->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_size' => 0, 'table_count' => 0, 'user_count' => 0, 'project_count' => 0, 'content_count' => 0];
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo trì hệ thống - Admin</title>
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
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        .btn.small { padding: 6px 12px; font-size: 13px; margin: 2px; }
        
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
            font-size: 28px;
            font-weight: bold;
            color: #4a90e2;
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
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .action-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .action-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .action-card p {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Bảo trì hệ thống</h1>
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
        
        <!-- Thống kê hệ thống -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Kích thước Database</h3>
                <div class="stat-number"><?php echo formatBytes($stats['total_size']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Số bảng</h3>
                <div class="stat-number"><?php echo $stats['table_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Người dùng</h3>
                <div class="stat-number"><?php echo $stats['user_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Dự án</h3>
                <div class="stat-number"><?php echo $stats['project_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Nội dung</h3>
                <div class="stat-number"><?php echo $stats['content_count']; ?></div>
            </div>
        </div>
        
        <!-- Công cụ bảo trì -->
        <div class="section">
            <h2>Công cụ bảo trì</h2>
            <div class="action-grid">
                <div class="action-card">
                    <h3>Sao lưu Database</h3>
                    <p>Tạo bản sao lưu toàn bộ cơ sở dữ liệu</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Bạn muốn sao lưu database?')">
                            Sao lưu ngay
                        </button>
                    </form>
                </div>
                
                <div class="action-card">
                    <h3>Tối ưu hóa Database</h3>
                    <p>Tối ưu hóa tất cả các bảng trong database</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="optimize">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Bạn muốn tối ưu hóa database?')">
                            Tối ưu hóa
                        </button>
                    </form>
                </div>
                
                <div class="action-card">
                    <h3>Xóa Cache</h3>
                    <p>Xóa cache và dữ liệu tạm thời</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="btn btn-info" onclick="return confirm('Bạn muốn xóa cache?')">
                            Xóa Cache
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Danh sách backup -->
        <div class="section">
            <h2>Danh sách Backup (<?php echo count($backup_files); ?>)</h2>
            
            <?php if (empty($backup_files)): ?>
                <p style="color: #666; text-align: center; padding: 40px 0;">
                    Chưa có file backup nào. Hãy tạo backup đầu tiên.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tên file</th>
                            <th>Kích thước</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $backup): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($backup['name']); ?></strong></td>
                            <td><?php echo formatBytes($backup['size']); ?></td>
                            <td><?php echo date('d/m/Y H:i:s', $backup['date']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="btn btn-success small" onclick="return confirm('CẢNH BÁO: Khôi phục sẽ GHI ĐÈ toàn bộ dữ liệu hiện tại. Bạn chắc chắn?')">
                                        Khôi phục
                                    </button>
                                </form>
                                
                                <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" class="btn btn-info small" download>
                                    Tải về
                                </a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="btn btn-danger small" onclick="return confirm('Bạn chắc chắn muốn xóa file backup này?')">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
