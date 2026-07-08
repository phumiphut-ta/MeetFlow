<?php
// login.php - Admin Authentication Page
require_once 'db.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Success! Set session
                $_SESSION['is_admin'] = true;
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (\PDOException $e) {
            $error = 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - MeetFlow Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--bg-app);
            padding: 20px;
        }
        .login-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .login-header {
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 3rem;
            background: linear-gradient(135deg, #38bdf8 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }
        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
        }
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .input-group input {
            padding-left: 48px;
        }
        .login-btn {
            width: 100%;
            justify-content: center;
            margin-top: 10px;
            padding: 14px;
            font-size: 1rem;
        }
        .error-message {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #f43f5e;
            padding: 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }
        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition-smooth);
        }
        .back-link:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-lock"></i>
            <h2>ผู้ดูแลระบบ MeetFlow</h2>
            <p>กรุณาลงชื่อเข้าใช้เพื่อแก้ไขข้อมูลและออกรายงาน</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="input-group">
                <label for="username">ชื่อผู้ใช้งาน</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="ระบุชื่อผู้ใช้งาน" required autocomplete="username">
                </div>
            </div>

            <div class="input-group">
                <label for="password">รหัสผ่าน</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-key"></i>
                    <input type="password" id="password" name="password" placeholder="ระบุรหัสผ่าน" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary login-btn">
                ลงชื่อเข้าใช้งาน <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>
        </form>

        <a href="index.php" class="back-link"><i class="fa-solid fa-chevron-left"></i> กลับไปหน้าปฏิทิน</a>
    </div>
</body>
</html>
