<?php
// settings.php - Admin Settings Panel (Discord Webhook & Password settings)
require_once 'db.php';

session_start();

// Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$successMsg = '';
$errorMsg = '';

// Fetch current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\PDOException $e) {
    $errorMsg = 'เกิดข้อผิดพลาดในการโหลดข้อมูลการตั้งค่า: ' . $e->getMessage();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_discord') {
        // Save Discord Webhook settings
        $webhook = isset($_POST['discord_webhook_url']) ? trim($_POST['discord_webhook_url']) : '';
        $notifyCreate = isset($_POST['notify_create']) ? '1' : '0';
        $notifyUpdate = isset($_POST['notify_update']) ? '1' : '0';
        $notifyDelete = isset($_POST['notify_delete']) ? '1' : '0';
        $notifyDaily = isset($_POST['notify_daily']) ? '1' : '0';
        $notifyDailyTime = isset($_POST['notify_daily_time']) ? trim($_POST['notify_daily_time']) : '08:00';

        try {
            $pdo->beginTransaction();

            // Since MySQL supports ON DUPLICATE KEY UPDATE and SQLite supports INSERT OR REPLACE,
            // we will use UPDATE or INSERT statements manually.
            
            $upsertSql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            $insertSql = "INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)"; // for SQLite/MySQL ignore
            
            // Standard update/insert pattern
            $keys = [
                'discord_webhook_url' => $webhook,
                'notify_create' => $notifyCreate,
                'notify_update' => $notifyUpdate,
                'notify_delete' => $notifyDelete,
                'notify_daily' => $notifyDaily,
                'notify_daily_time' => $notifyDailyTime
            ];
            
            $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            
            foreach ($keys as $key => $val) {
                // Try update first
                $stmtUpdate->execute([$val, $key]);
                
                // If no row updated, insert
                $stmtCheck = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
                $stmtCheck->execute([$key]);
                if (!$stmtCheck->fetch()) {
                    $stmtInsert->execute([$key, $val]);
                }
            }
            
            $pdo->commit();
            $successMsg = 'บันทึกการตั้งค่าการแจ้งเตือน Discord เรียบร้อยแล้ว';
            
            // Reload settings
            $settings['discord_webhook_url'] = $webhook;
            $settings['notify_create'] = $notifyCreate;
            $settings['notify_update'] = $notifyUpdate;
            $settings['notify_delete'] = $notifyDelete;
            $settings['notify_daily'] = $notifyDaily;
            $settings['notify_daily_time'] = $notifyDailyTime;
            
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Change Admin Password
        $currentPass = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
        $newPass = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirmPass = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errorMsg = 'กรุณากรอกข้อมูลรหัสผ่านให้ครบถ้วน';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
        } elseif (strlen($newPass) < 6) {
            $errorMsg = 'รหัสผ่านใหม่ต้องมีความยาวไม่น้อยกว่า 6 ตัวอักษร';
        } else {
            try {
                // Fetch admin password hash
                $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
                $stmt->execute([$_SESSION['username']]);
                $user = $stmt->fetch();

                if ($user && password_verify($currentPass, $user['password'])) {
                    // Update to new password
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                    $stmtUpdate->execute([$newHash, $_SESSION['username']]);
                    $successMsg = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
                } else {
                    $errorMsg = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            } catch (\PDOException $e) {
                $errorMsg = 'เกิดข้อผิดพลาดเกี่ยวกับฐานข้อมูล: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - MeetFlow Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (min-width: 900px) {
            .settings-grid {
                grid-template-columns: 1.2fr 1fr;
            }
        }
        .settings-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }
        .settings-card h2 {
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
        /* Custom Switch Toggle */
        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.3);
            margin-bottom: 12px;
            border: 1px solid var(--border-glass);
        }
        .toggle-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .toggle-info span {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .toggle-info small {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #475569;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background: var(--primary-gradient);
        }
        input:checked + .slider:before {
            transform: translateX(22px);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-gear"></i> ตั้งค่าระบบ MeetFlow</h1>
                <p>จัดการการแจ้งเตือน Discord และข้อมูลบัญชีผู้ดูแลระบบ</p>
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
        <main class="settings-grid">
            <!-- Discord Notification Box -->
            <div class="settings-card">
                <h2><i class="fa-brands fa-discord" style="color: #5865F2;"></i> ตั้งค่าแจ้งเตือนผ่าน Discord Webhook</h2>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="save_discord">
                    
                    <div class="form-group">
                        <label for="discord_webhook_url">Discord Webhook URL</label>
                        <input type="url" id="discord_webhook_url" name="discord_webhook_url" 
                               value="<?= htmlspecialchars(isset($settings['discord_webhook_url']) ? $settings['discord_webhook_url'] : '') ?>"
                               placeholder="https://discord.com/api/webhooks/...">
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label>เลือกการแจ้งเตือนที่ต้องการเปิดใช้งาน</label>
                        
                        <!-- Toggle 1: Create -->
                        <div class="toggle-row">
                            <div class="toggle-info">
                                <span>แจ้งเตือนเมื่อนัดประชุมใหม่</span>
                                <small>ส่งข้อมูลไปยัง Discord เมื่อมีการเพิ่มนัดประชุมหรือวันอบรมใหม่</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_create" value="1" 
                                       <?= (isset($settings['notify_create']) && $settings['notify_create'] === '1') ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 2: Update -->
                        <div class="toggle-row">
                            <div class="toggle-info">
                                <span>แจ้งเตือนเมื่อแก้ไขการนัดหมาย</span>
                                <small>ส่งข้อมูลไปยัง Discord เมื่อมีการปรับปรุงรายละเอียดการนัดหมายเดิม</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_update" value="1" 
                                       <?= (isset($settings['notify_update']) && $settings['notify_update'] === '1') ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 3: Delete -->
                        <div class="toggle-row">
                            <div class="toggle-info">
                                <span>แจ้งเตือนเมื่อลบการนัดหมาย</span>
                                <small>ส่งข้อมูลไปยัง Discord เมื่อมีการลบข้อมูลนัดประชุมหรือวันอบรม</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_delete" value="1" 
                                       <?= (isset($settings['notify_delete']) && $settings['notify_delete'] === '1') ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Toggle 4: Daily Summary -->
                        <div class="toggle-row">
                            <div class="toggle-info">
                                <span>แจ้งเตือนสรุปนัดหมายประจำวัน</span>
                                <small>ส่งสรุปการนัดหมายและวันอบรมทั้งหมดในแต่ละวันไปยัง Discord ตามเวลาที่กำหนด</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_daily" value="1" 
                                       <?= (isset($settings['notify_daily']) && $settings['notify_daily'] === '1') ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <!-- Daily Notification Time -->
                        <div class="form-group" style="margin-top: 15px; padding: 12px 16px; background: rgba(15, 23, 42, 0.3); border-radius: 12px; border: 1px solid var(--border-glass);">
                            <label for="notify_daily_time" style="margin-bottom: 8px; color: var(--text-secondary);">เวลาที่จะส่งแจ้งเตือนประจำวัน</label>
                            <input type="time" id="notify_daily_time" name="notify_daily_time" 
                                   value="<?= htmlspecialchars(isset($settings['notify_daily_time']) ? $settings['notify_daily_time'] : '08:00') ?>"
                                   style="max-width: 150px;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกการตั้งค่า Discord</button>
                </form>
            </div>

            <!-- Password Box -->
            <div class="settings-card">
                <h2><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่านผู้ดูแลระบบ</h2>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">รหัสผ่านปัจจุบัน</label>
                        <input type="password" id="current_password" name="current_password" required placeholder="กรอกรหัสผ่านปัจจุบัน">
                    </div>

                    <div class="form-group">
                        <label for="new_password">รหัสผ่านใหม่</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="ยืนยันรหัสผ่านใหม่อีกครั้ง">
                    </div>

                    <button type="submit" class="btn btn-primary" style="background: var(--success-gradient);"><i class="fa-solid fa-check"></i> อัปเดตรหัสผ่านใหม่</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
