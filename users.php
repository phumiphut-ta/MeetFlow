<?php
// users.php - Admin User Management Page (Only accessible to logged-in Admin)
require_once 'db.php';

session_start();

// Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$successMsg = '';
$errorMsg = '';

// Handle POST: Create New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $newUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
    $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if (empty($newUsername) || empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (strlen($newPassword) < 6) {
        $errorMsg = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        try {
            // Check if username already exists
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$newUsername]);
            if ($stmtCheck->fetch()) {
                $errorMsg = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น';
            } else {
                // Insert new admin user
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmtInsert->execute([$newUsername, $hashedPassword]);
                $successMsg = "เพิ่มผู้ใช้งาน '$newUsername' เป็นผู้ดูแลระบบเรียบร้อยแล้ว";
            }
        } catch (\PDOException $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทข้อมูล: ' . $e->getMessage();
        }
    }
}

// Handle GET: Delete User
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    
    try {
        // Fetch username to prevent self-deletion or validation
        $stmtFetch = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtFetch->execute([$deleteId]);
        $userToDelete = $stmtFetch->fetch();
        
        if (!$userToDelete) {
            $errorMsg = 'ไม่พบผู้ใช้ที่ต้องการลบ';
        } elseif ($userToDelete['username'] === $_SESSION['username']) {
            $errorMsg = 'คุณไม่สามารถลบบัญชีของตัวเองได้!';
        } else {
            // Delete user
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelete->execute([$deleteId]);
            $successMsg = "ลบผู้ใช้งาน '" . $userToDelete['username'] . "' เรียบร้อยแล้ว";
        }
    } catch (\PDOException $e) {
        $errorMsg = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage();
    }
}

// Fetch all users
$users = [];
try {
    $stmtUsers = $pdo->query("SELECT id, username, created_at FROM users ORDER BY id ASC");
    $users = $stmtUsers->fetchAll();
} catch (\PDOException $e) {
    $errorMsg = 'เกิดข้อผิดพลาดในการโหลดรายชื่อผู้ใช้: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - MeetFlow Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .users-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (min-width: 900px) {
            .users-grid {
                grid-template-columns: 1.2fr 1fr;
            }
        }
        .users-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }
        .users-card h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-glass);
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-danger {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fb7185;
        }
        /* Custom Table for user list */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .user-table th, .user-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-align: left;
        }
        .user-table th {
            font-weight: 700;
            color: var(--text-secondary);
            background: rgba(15, 23, 42, 0.4);
        }
        .current-user-row {
            background: rgba(99, 102, 241, 0.05);
        }
        .current-user-badge {
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-light);
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 6px;
            margin-left: 8px;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-users-gear"></i> จัดการผู้ดูแลระบบ MeetFlow</h1>
                <p>เพิ่ม ลบ และดูรายชื่อบัญชีผู้ใช้งานที่สามารถแก้ไขข้อมูลระบบได้</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าปฏิทิน</a>
            </div>
        </header>

        <!-- Status Alerts -->
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Settings Content Grid -->
        <main class="users-grid">
            <!-- User List Box -->
            <div class="users-card">
                <h2><i class="fa-solid fa-list-ul"></i> รายชื่อผู้ดูแลระบบทั้งหมด</h2>
                <div style="overflow-x: auto;">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้งาน</th>
                                <th>วันที่สร้างบัญชี</th>
                                <th style="width: 100px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): 
                                $isMe = ($u['username'] === $_SESSION['username']);
                            ?>
                                <tr class="<?= $isMe ? 'current-user-row' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                        <?php if ($isMe): ?>
                                            <span class="current-user-badge">ฉัน</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($u['created_at']) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($isMe): ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem; opacity: 0.5;">-</span>
                                        <?php else: ?>
                                            <a href="?delete_id=<?= $u['id'] ?>" class="btn btn-danger" 
                                               style="padding: 6px 12px; font-size: 0.8rem; box-shadow: none;"
                                               onclick="return confirm('คุณแน่ใจว่าต้องการลบผู้ดูแลระบบชื่อ <?= htmlspecialchars($u['username']) ?> ใช่หรือไม่? หากลบไปแล้วจะไม่สามารถเข้าใช้งานระบบได้อีก')">
                                                <i class="fa-solid fa-trash"></i> ลบ
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create User Box -->
            <div class="users-card">
                <h2><i class="fa-solid fa-user-plus"></i> เพิ่มผู้ดูแลระบบคนใหม่</h2>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้งานใหม่ <span style="color: var(--danger)">*</span></label>
                        <input type="text" id="username" name="username" required placeholder="ระบุชื่อผู้ใช้งาน เช่น user.admin" autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">รหัสผ่านใหม่ <span style="color: var(--danger)">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่านใหม่ <span style="color: var(--danger)">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 10px; width: 100%; justify-content: center;">
                        <i class="fa-solid fa-check"></i> สร้างผู้ดูแลระบบใหม่
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
