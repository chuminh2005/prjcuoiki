<?php
require_once 'session.php';
require_once 'connect.php';

requireLogin();

$user = getCurrentUser();
$project_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT p.*, u.full_name as owner_name FROM project p JOIN user u ON p.id_user = u.id_user WHERE p.id_project = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: khonggianuser.php");
    exit();
}

$is_owner = ($project['id_user'] == $user['id_user']);
$stmt = $pdo->prepare("SELECT project_role FROM member WHERE id_project = ? AND id_user = ? AND flag = 'active'");
$stmt->execute([$project_id, $user['id_user']]);
$member = $stmt->fetch();
$user_role = $member ? $member['project_role'] : null;

if ($project['visibility'] == 'private' && !$is_owner && !$user_role) {
    header("Location: khonggianuser.php");
    exit();
}

$can_settings = $is_owner || $user_role == 'manager';

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member WHERE id_project = ? AND flag = 'active'");
$stmt->execute([$project_id]);
$member_count = $stmt->fetch()['count'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?></title>
    <link rel="stylesheet" href="./css/khonggianuser.css">
    <style>
        .project-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .project-header {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            backdrop-filter: blur(5px);
        }
        
        .project-title-section {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .project-title-section h1 {
            margin: 0;
            color: #333;
            font-size: 32px;
        }
        
        .project-actions {
            display: flex;
            gap: 10px;
        }
        
        .project-meta-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-disactive { background: #fff3cd; color: #856404; }
        .status-freeze { background: #d1ecf1; color: #0c5460; }
        
        .breadcrumb {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .breadcrumb a {
            color: #4a90e2;
            text-decoration: none;
        }
        
        .content-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            backdrop-filter: blur(5px);
            opacity: 0.95;
        }
        
        .content-section h2 {
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .top-bar {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 20px 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
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
        <div class="project-header">
            
            <div class="project-title-section">
                <div style="width: 100%;">
                    <h1> <?php echo htmlspecialchars($project['title']); ?></h1>
                    <p style="color: #666; margin: 10px 0 0 0;">
                        <?php echo htmlspecialchars($project['description']); ?>
                    </p>
                </div>
            </div>
            
            <div class="project-meta-info">
                <div class="meta-item">
                    <span> <strong>Chủ sở hữu:</strong> <?php echo htmlspecialchars($project['owner_name']); ?></span>
                </div>
                
                <div class="meta-item">
                    <span> <strong>Thành viên:</strong> <?php echo $member_count + 1; ?></span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Trạng thái:</strong></span>
                    <span class="status-badge status-<?php echo $project['status']; ?>">
                        <?php 
                        $status_text = [
                            'active' => ' Hoạt động',
                            'disactive' => ' Lưu trữ',
                            'freeze' => ' Không hoạt động'
                        ];
                        echo $status_text[$project['status']];
                        ?>
                    </span>
                </div>
                
                <div class="meta-item">
                    <span><strong>Hiển thị:</strong></span>
                    <span><?php echo $project['visibility'] == 'public' ? ' Công khai' : ' Riêng tư'; ?></span>
                </div>
                
                <div class="meta-item">
                    <span> <strong>Ngày tạo:</strong> <?php echo date('d/m/Y', strtotime($project['creat_at'])); ?></span>
                </div>
                
                <?php if ($user_role): ?>
                <div class="meta-item">
                    <span><strong>Vai trò của bạn:</strong></span>
                    <span class="status-badge" style="background: #e3f2fd; color: #1976d2;">
                        <?php 
                        $role_names = [
                            'manager' => ' Người điều hành',
                            'contributor' => ' Người đóng góp',
                            'commenter' => ' Người bình luận',
                            'viewer' => ' Người quan sát'
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
        
        <div class="content-section">
            <h2> Cấu trúc nội dung dự án</h2>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
            </div>
        </div>
        
        <div class="content-section">
            <h2> Nội dung chờ duyệt</h2>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
            </div>
        </div>
        
         <a href="project_detail.php?id=<?php echo $project_id; ?>">
            <div class="content-section">
                <h2> Nội dung chi tiết</h2>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                </div>
            </div>
         </a>   
    </div>
</body>
</html>
