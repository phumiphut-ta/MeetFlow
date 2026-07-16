<?php
// notify_discord.php - Discord Webhook Notification Helper

require_once 'db.php';

function notifyDiscord($action, $meeting, $attendees = []) {
    global $pdo;

    try {
        // Fetch discord settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $webhookUrl = isset($settings['discord_webhook_url']) ? trim($settings['discord_webhook_url']) : '';
        if (empty($webhookUrl)) {
            return; // No webhook configured
        }

        // Check toggles
        $shouldNotify = false;
        $color = 3447003; // Default blue
        $actionTitle = '';

        if ($action === 'create' && isset($settings['notify_create']) && $settings['notify_create'] === '1') {
            $shouldNotify = true;
            $color = 3066993; // Green
            $actionTitle = '📅 บันทึกการนัดหมายใหม่';
        } elseif ($action === 'update' && isset($settings['notify_update']) && $settings['notify_update'] === '1') {
            $shouldNotify = true;
            $color = 15105570; // Orange
            $actionTitle = '✏️ อัปเดตข้อมูลการนัดหมาย';
        } elseif ($action === 'delete' && isset($settings['notify_delete']) && $settings['notify_delete'] === '1') {
            $shouldNotify = true;
            $color = 15158332; // Red
            $actionTitle = '🗑️ ยกเลิกการนัดหมาย';
        }

        if (!$shouldNotify) {
            return;
        }

        // Format Date to Thai
        $dateParts = explode('-', $meeting['meeting_date']);
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];
        $thaiYear = intval($dateParts[0]) + 543;
        $monthName = $thaiMonths[intval($dateParts[1])];
        $thaiDate = intval($dateParts[2]) . ' ' . $monthName . ' ' . $thaiYear;

        // If multi-day event
        $hasEndDate = !empty($meeting['end_date']) && $meeting['end_date'] !== $meeting['meeting_date'];
        if ($hasEndDate) {
            $endDateParts = explode('-', $meeting['end_date']);
            $endThaiYear = intval($endDateParts[0]) + 543;
            $endMonthName = $thaiMonths[intval($endDateParts[1])];
            $endThaiDate = intval($endDateParts[2]) . ' ' . $endMonthName . ' ' . $endThaiYear;
            $thaiDate = "$thaiDate ถึง $endThaiDate";
        }

        $startTime = substr($meeting['start_time'], 0, 5);
        $endTime = substr($meeting['end_time'], 0, 5);
        $isAllDay = ($startTime === '08:30' && $endTime === '16:30');
        $timeStr = $isAllDay ? 'ตลอดทั้งวัน' : "$startTime - $endTime น.";

        // Build Attendees list text
        $attendeeText = '-';
        if (!empty($attendees)) {
            $attendeeText = implode("\n• ", $attendees);
            $attendeeText = "• " . $attendeeText;
        }

        // Fetch all meeting types for dynamic naming
        $meetingTypes = [];
        try {
            $stmtTypes = $pdo->query("SELECT * FROM meeting_types");
            while ($row = $stmtTypes->fetch()) {
                $meetingTypes[$row['type_key']] = $row;
            }
        } catch (\Exception $e) {
            // fallback
        }

        $typeKey = $meeting['meeting_type'] ?? 'meeting';
        $typeName = isset($meetingTypes[$typeKey]) ? $meetingTypes[$typeKey]['type_name'] : 'ประชุม';
        if ($typeKey === 'meeting') {
            $typeStr = '👥 ' . $typeName;
        } elseif ($typeKey === 'training') {
            $typeStr = '🏫 ' . $typeName;
        } else {
            $typeStr = '🏷️ ' . $typeName;
        }

        // Build Fields
        $fields = [
            [
                "name" => "หัวข้อ",
                "value" => "**" . $meeting['title'] . "**",
                "inline" => false
            ],
            [
                "name" => "ประเภท",
                "value" => $typeStr,
                "inline" => true
            ],
            [
                "name" => "วันเวลา",
                "value" => "📆 $thaiDate\n⏰ $timeStr",
                "inline" => true
            ]
        ];

        // Description
        if (!empty($meeting['description'])) {
            $fields[] = [
                "name" => "รายละเอียด",
                "value" => $meeting['description'],
                "inline" => false
            ];
        }

        // Book reference codes
        if (!empty($meeting['doc_no']) || !empty($meeting['office_no'])) {
            $refText = "";
            if (!empty($meeting['doc_no'])) {
                $refText .= "📄 **เลขหนังสือ:** " . $meeting['doc_no'] . "\n";
            }
            if (!empty($meeting['office_no'])) {
                $refText .= "📥 **เลขรับสำนักงาน:** " . $meeting['office_no'] . "\n";
            }
            $fields[] = [
                "name" => "เอกสารอ้างอิง",
                "value" => $refText,
                "inline" => true
            ];
        }

        // Attendees
        $fields[] = [
            "name" => "ผู้เข้าร่วมประชุม (" . count($attendees) . " คน)",
            "value" => $attendeeText,
            "inline" => false
        ];

        // Meeting Links
        if (!empty($meeting['meeting_link'])) {
            $fields[] = [
                "name" => "ลิงก์เข้าร่วมประชุมออนไลน์",
                "value" => "[คลิกเพื่อเข้าประชุมที่นี่](" . $meeting['meeting_link'] . ")",
                "inline" => false
            ];
        }

        // Construct Embed Payload
        $payload = [
            "embeds" => [
                [
                    "title" => $actionTitle,
                    "color" => $color,
                    "fields" => $fields,
                    "timestamp" => date('c'),
                    "footer" => [
                        "text" => "MeetFlow Calendar System"
                    ]
                ]
            ]
        ];

        // Send request to Discord (with robust failover if cURL extension is disabled on IIS)
        if (function_exists('curl_init')) {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout to avoid blocking main thread
            
            // Bypass SSL certificate check (essential for Windows Server IIS environments without CA bundle setups)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
            // Fallback to PHP native HTTP stream wrapper if cURL extension is not enabled in php.ini
            $options = [
                'http' => [
                    'header'  => "Content-Type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($payload),
                    'timeout' => 5,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
            $context  = stream_context_create($options);
            @file_get_contents($webhookUrl, false, $context);
        }
        
    } catch (Exception $e) {
        // Fail silently to avoid interrupting the main application save process
        error_log("Discord notify failed: " . $e->getMessage());
    }
}
