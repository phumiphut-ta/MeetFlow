<?php
// cron_notify.php - Daily summary reminder scheduler for Discord Webhook
// Can be run every 5-10 minutes via Task Scheduler or Crontab

require_once __DIR__ . '/db.php';

// Set default timezone for exact matching
date_default_timezone_set('Asia/Bangkok');

try {
    // 1. Fetch settings from DB
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $webhookUrl = isset($settings['discord_webhook_url']) ? trim($settings['discord_webhook_url']) : '';
    $notifyDaily = isset($settings['notify_daily']) ? $settings['notify_daily'] : '0';
    $notifyDailyTime = isset($settings['notify_daily_time']) ? trim($settings['notify_daily_time']) : '08:00';
    $lastCronRunDate = isset($settings['last_cron_run_date']) ? $settings['last_cron_run_date'] : '';

    // Check if daily notifications are enabled and webhook is set
    if ($notifyDaily !== '1' || empty($webhookUrl)) {
        echo "Daily notifications disabled or Webhook URL is empty.\n";
        exit();
    }

    $todayDate = date('Y-m-d');
    $currentTime = date('H:i');

    // 2. Prevent duplicate notifications today
    if ($lastCronRunDate === $todayDate) {
        echo "Notification already sent for today ($todayDate).\n";
        exit();
    }

    // 3. Check if current time is past or equal to scheduled notification time
    if ($currentTime < $notifyDailyTime) {
        echo "Current time ($currentTime) is before scheduled time ($notifyDailyTime). Skipping.\n";
        exit();
    }

    // 4. Retrieve meetings/trainings scheduled for today
    $stmtMeetings = $pdo->prepare("SELECT * FROM meetings WHERE meeting_date = ? ORDER BY start_time ASC");
    $stmtMeetings->execute([$todayDate]);
    $meetings = $stmtMeetings->fetchAll();

    // If no meetings today, we just mark today as run and exit (avoid spamming empty notifications)
    if (empty($meetings)) {
        echo "No meetings scheduled for today ($todayDate). Marking as run.\n";
        updateLastRunDate($pdo, $todayDate);
        exit();
    }

    // 5. Format and dispatch the Discord summary embed
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $dateParts = explode('-', $todayDate);
    $thaiYear = intval($dateParts[0]) + 543;
    $monthName = $thaiMonths[intval($dateParts[1])];
    $thaiDateStr = intval($dateParts[2]) . ' ' . $monthName . ' ' . $thaiYear;

    $fields = [];
    $meetingCount = count($meetings);

    foreach ($meetings as $idx => $m) {
        $start = substr($m['start_time'], 0, 5);
        $end = substr($m['end_time'], 0, 5);
        
        // Fetch attendees
        $stmtAtt = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
        $stmtAtt->execute([$m['id']]);
        $attendees = $stmtAtt->fetchAll(PDO::FETCH_COLUMN);
        
        $attendeeStr = !empty($attendees) ? implode(', ', $attendees) : '-';
        
        $meetingInfo = "⏰ **เวลา:** $start - $end น.\n";
        
        if (!empty($m['description'])) {
            $meetingInfo .= "📝 **รายละเอียด:** " . $m['description'] . "\n";
        }
        if (!empty($m['doc_no'])) {
            $meetingInfo .= "📄 **เลขที่หนังสือ:** " . $m['doc_no'] . "\n";
        }
        if (!empty($m['meeting_link'])) {
            $meetingInfo .= "🔗 **ลิงก์ประชุม:** [คลิกเข้าประชุมที่นี่](" . $m['meeting_link'] . ")\n";
        }
        $meetingInfo .= "👥 **ผู้เข้าร่วม:** $attendeeStr";
        
        $fields[] = [
            "name" => ($idx + 1) . ". 📌 " . $m['title'],
            "value" => $meetingInfo,
            "inline" => false
        ];
    }

    $payload = [
        "embeds" => [
            [
                "title" => "📢 แจ้งเตือนตารางการประชุมประจำวันนี้",
                "description" => "📅 **ประจำวันที่:** $thaiDateStr\n💬 **มีตารางการประชุมทั้งหมด:** $meetingCount รายการ",
                "color" => 9807270, // Lavender/Purple
                "fields" => $fields,
                "timestamp" => date('c'),
                "footer" => [
                    "text" => "MeetFlow Daily Reminder"
                ]
            ]
        ]
    ];

    // Dispatch webhook curl
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo "Daily notification sent successfully to Discord.\n";
        updateLastRunDate($pdo, $todayDate);
    } else {
        echo "Failed to send notification. HTTP Status: $httpCode. Response: $res\n";
    }

} catch (Exception $e) {
    echo "Cron error: " . $e->getMessage() . "\n";
}

// Helper to update last run date
function updateLastRunDate($pdo, $date) {
    $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_cron_run_date'");
    $stmtUpdate->execute([$date]);
    
    // Fallback if not exists
    $stmtCheck = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = 'last_cron_run_date'");
    $stmtCheck->execute();
    if (!$stmtCheck->fetch()) {
        $stmtInsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('last_cron_run_date', ?)");
        $stmtInsert->execute([$date]);
    }
}
?>
