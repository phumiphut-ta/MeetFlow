<?php
// db.php - Smart Database Connection (MySQL with automatic SQLite Fallback for Local Testing)

$host = '127.0.0.1';
$db   = 'meetflow';
$user = 'root';
$pass = ''; // Default password, change as needed for Windows Server IIS/MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$is_sqlite = false;

try {
    // Attempt MySQL connection
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // FALLBACK TO SQLITE FOR LOCAL DEVELOPMENT/PREVIEW
    // If MySQL connection fails, we assume we are running in a local test environment
    $sqlite_path = __DIR__ . '/meetflow.sqlite';
    $is_sqlite = true;
    
    try {
        $pdo = new PDO("sqlite:" . $sqlite_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Enable Foreign Key support in SQLite
        $pdo->exec("PRAGMA foreign_keys = ON;");
        
        // Check if database is already initialized
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='meetings'")->fetch();
        if (!$check) {
            // Initialize SQLite Database schema
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS meetings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    meeting_date DATE NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    doc_no VARCHAR(100) DEFAULT NULL,
                    office_no VARCHAR(100) DEFAULT NULL,
                    meeting_link VARCHAR(1000) DEFAULT NULL,
                    doc_file VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS meeting_attendees (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    meeting_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE
                );
                
                CREATE INDEX idx_meetings_date ON meetings(meeting_date);
                CREATE INDEX idx_meetings_doc_no ON meetings(doc_no);
                CREATE INDEX idx_meetings_office_no ON meetings(office_no);
                CREATE INDEX idx_attendees_meeting_id ON meeting_attendees(meeting_id);
            ");
            
            // Insert sample data
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            $stmt = $pdo->prepare("INSERT INTO meetings (title, description, meeting_date, start_time, end_time, doc_no, office_no, meeting_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                'ประชุมวางแผนงบประมาณประจำปี 2570',
                'ประชุมหารือแนวทางการจัดทำงบประมาณรายจ่ายประจำปีงบประมาณ พ.ศ. 2570 ของส่วนงานพัฒนาเทคโนโลยีสารสนเทศ',
                $today,
                '09:30:00',
                '12:00:00',
                'นร 0505/ว123',
                'รับที่ 4567/2569',
                'https://meet.google.com/abc-defg-hij'
            ]);
            $id1 = $pdo->lastInsertId();
            
            $stmt->execute([
                'อบรมการใช้งานระบบจัดการความรู้ (KM)',
                'การฝึกอบรมการใช้งานโปรแกรมแบ่งปันความรู้ภายในองค์กรสำหรับเจ้าหน้าที่เข้าใหม่',
                $tomorrow,
                '13:30:00',
                '16:30:00',
                'นร 0505/ว124',
                'รับที่ 4568/2569',
                'https://teams.microsoft.com/l/meetup-join/example'
            ]);
            $id2 = $pdo->lastInsertId();
            
            // Add attendees
            $stmtAtt = $pdo->prepare("INSERT INTO meeting_attendees (meeting_id, name) VALUES (?, ?)");
            $stmtAtt->execute([$id1, 'นายสมชาย ดีเด่น']);
            $stmtAtt->execute([$id1, 'นางสาวสมศรี เรียนดี']);
            $stmtAtt->execute([$id1, 'นายวิชัย ว่องไว']);
            $stmtAtt->execute([$id2, 'นางสาวจารุวรรณ นามสมมติ']);
            $stmtAtt->execute([$id2, 'นายสมศักดิ์ รักเรียน']);
        }

        // Initialize Users & Settings for SQLite
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT DEFAULT NULL
            );
        ");

        // Seed admin if not exists
        $userCheck = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
        if (!$userCheck) {
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmtUser->execute(['admin', '$2y$12$bTIzCR6FqelD/SkWc9f7MOcrFTjxTzmZb2qf3jBE6GtIVk3fkzmzi']); // admin1234
        }

        // Seed settings if not exists
        $settCheck = $pdo->query("SELECT setting_key FROM settings WHERE setting_key = 'discord_webhook_url'")->fetch();
        if (!$settCheck) {
            $pdo->exec("
                INSERT INTO settings (setting_key, setting_value) VALUES ('discord_webhook_url', '');
                INSERT INTO settings (setting_key, setting_value) VALUES ('notify_create', '0');
                INSERT INTO settings (setting_key, setting_value) VALUES ('notify_update', '0');
                INSERT INTO settings (setting_key, setting_value) VALUES ('notify_delete', '0');
                INSERT INTO settings (setting_key, setting_value) VALUES ('notify_daily', '0');
                INSERT INTO settings (setting_key, setting_value) VALUES ('notify_daily_time', '08:00');
                INSERT INTO settings (setting_key, setting_value) VALUES ('last_cron_run_date', '');
            ");
        }

    } catch (\PDOException $sqlite_error) {
        // If both failed, display error
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <title>MeetFlow - Database Connection Error</title>
            <style>
                body { font-family: sans-serif; background: #0f172a; color: #f1f5f9; padding: 40px; display: flex; justify-content: center; }
                .box { background: #1e293b; border: 1px solid rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; max-width: 600px; }
                h1 { color: #ef4444; }
                pre { background: #0f172a; padding: 15px; border-radius: 6px; overflow-x: auto; color: #38bdf8; }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>การเชื่อมต่อฐานข้อมูลล้มเหลว</h1>
                <p>ระบบพยายามเชื่อมต่อกับ MySQL และ SQLite แต่ไม่สำเร็จ:</p>
                <p><strong>ข้อผิดพลาด MySQL:</strong></p>
                <pre><?= htmlspecialchars($e->getMessage()) ?></pre>
                <p><strong>ข้อผิดพลาด SQLite:</strong></p>
                <pre><?= htmlspecialchars($sqlite_error->getMessage()) ?></pre>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Auto-migrate schema: Add meeting_type column if it doesn't exist
try {
    $pdo->query("SELECT meeting_type FROM meetings LIMIT 1");
} catch (\Exception $ex) {
    try {
        if ($is_sqlite) {
            $pdo->exec("ALTER TABLE meetings ADD COLUMN meeting_type VARCHAR(50) DEFAULT 'meeting'");
        } else {
            $pdo->exec("ALTER TABLE meetings ADD COLUMN `meeting_type` VARCHAR(50) DEFAULT 'meeting' AFTER `description`");
        }
    } catch (\Exception $migration_error) {
        // Ignore if failed
    }
}

// Create meeting_types table if it doesn't exist
try {
    $pdo->query("SELECT 1 FROM meeting_types LIMIT 1");
} catch (\Exception $ex) {
    try {
        if ($is_sqlite) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS meeting_types (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type_key VARCHAR(50) NOT NULL UNIQUE,
                    type_name VARCHAR(100) NOT NULL,
                    color VARCHAR(20) DEFAULT '#3b82f6',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS meeting_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type_key VARCHAR(50) NOT NULL UNIQUE,
                    type_name VARCHAR(100) NOT NULL,
                    color VARCHAR(20) DEFAULT '#3b82f6',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
        
        // Seed default types
        $pdo->exec("
            INSERT INTO meeting_types (type_key, type_name, color) VALUES ('meeting', 'ประชุม', '#3b82f6');
            INSERT INTO meeting_types (type_key, type_name, color) VALUES ('training', 'อบรม', '#10b981');
        ");
    } catch (\Exception $migration_error) {
        // Ignore if failed
    }
}

// Utility function to convert Hex to RGBA for glass backgrounds
if (!function_exists('hexToRgba')) {
    function hexToRgba($hex, $alpha = 0.2) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return "rgba($r, $g, $b, $alpha)";
    }
}


