<?php
// list.php - Meeting & Training List Panel (View, Search, and Admin Actions)
require_once 'db.php';

session_start();

// Check Admin Status (Allow both public and admin, but restrict actions to admins)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Search & Type Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // all, dynamic types
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Fetch all meeting types
$meetingTypes = [];
try {
    $stmtTypes = $pdo->query("SELECT * FROM meeting_types");
    while ($row = $stmtTypes->fetch()) {
        $meetingTypes[$row['type_key']] = $row;
    }
} catch (\Exception $e) {
    $meetingTypes = [
        'meeting' => ['type_key' => 'meeting', 'type_name' => 'ประชุม', 'color' => '#3b82f6'],
        'training' => ['type_key' => 'training', 'type_name' => 'อบรม', 'color' => '#10b981']
    ];
}

// Fetch Meetings matching filters
$allMeetings = [];
try {
    $sql = "SELECT * FROM meetings WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (title LIKE ? OR description LIKE ? OR doc_no LIKE ? OR office_no LIKE ?)";
        $likeSearch = "%$search%";
        $params[] = $likeSearch;
        $params[] = $likeSearch;
        $params[] = $likeSearch;
        $params[] = $likeSearch;
    }

    if ($startDate !== '') {
        $sql .= " AND meeting_date >= ?";
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= " AND meeting_date <= ?";
        $params[] = $endDate;
    }

    if ($type !== 'all') {
        $sql .= " AND meeting_type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY meeting_date ASC, start_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allMeetings = $stmt->fetchAll();

    // Fetch attendees for all filtered meetings
    foreach ($allMeetings as $key => $meeting) {
        $stmtAttendees = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
        $stmtAttendees->execute([$meeting['id']]);
        $allMeetings[$key]['attendees'] = $stmtAttendees->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (\PDOException $e) {
    $dbError = $e->getMessage();
}

// Partition meetings in PHP
$todayDate = date('Y-m-d');
$todayMeetings = [];
$upcomingMeetings = [];
$pastMeetings = [];

foreach ($allMeetings as $m) {
    if ($m['meeting_date'] === $todayDate) {
        $todayMeetings[] = $m;
    } elseif ($m['meeting_date'] > $todayDate) {
        $upcomingMeetings[] = $m;
    } else {
        $pastMeetings[] = $m;
    }
}

// Sort past meetings descending (most recent first)
usort($pastMeetings, function($a, $b) {
    if ($a['meeting_date'] === $b['meeting_date']) {
        return strcmp($b['start_time'], $a['start_time']);
    }
    return strcmp($b['meeting_date'], $a['meeting_date']);
});

// Thai Month Names for rendering date columns
$thaiMonthsShort = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
    5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
    9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการการนัดหมายและอบรม - MeetFlow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* List page specific styles */
        .report-filters-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }
        .filter-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        @media (min-width: 768px) {
            .filter-row {
                grid-template-columns: 1.5fr 1fr 1fr 1fr;
            }
        }
        .report-table-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow-lg);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            text-align: left;
        }
        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        th {
            font-weight: 700;
            color: var(--text-secondary);
            background: rgba(15, 23, 42, 0.4);
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        .badge-type {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            background: var(--type-bg, rgba(99, 102, 241, 0.15));
            color: var(--type-color, var(--primary-light));
            border: 1px solid var(--type-border, rgba(99, 102, 241, 0.3));
        }

        /* Tabs Styling */
        .tabs-container {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 8px;
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 10px 20px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 10px;
            transition: var(--transition-smooth);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.05);
        }
        .tab-btn.active {
            color: var(--primary-light);
            background: rgba(99, 102, 241, 0.15);
            border-bottom: 2px solid var(--primary-light);
            border-radius: 10px 10px 0 0;
        }
        .tab-count {
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 700;
        }
        .tab-btn.active .tab-count {
            background: var(--primary-light);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Action Buttons on list */
        .list-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt;
                padding: 0;
            }
            .app-container {
                max-width: 100%;
                padding: 0;
            }
            .app-header, .report-filters-card, .btn, footer, .back-home, .header-actions, .tabs-container {
                display: none !important;
            }
            .report-table-card {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
            }
            .tab-content {
                display: block !important;
                margin-bottom: 40px;
            }
            .tab-content h3 {
                display: block !important;
                font-size: 14pt;
                border-bottom: 2px solid #333;
                padding-bottom: 5px;
                margin-top: 30px;
                color: black !important;
            }
            table {
                width: 100%;
                border: 1px solid #ddd;
            }
            th, td {
                border: 1px solid #ddd !important;
                color: black !important;
                padding: 8px !important;
            }
            th {
                background: #f5f5f5 !important;
            }
            .print-title {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            .badge-type {
                background: none !important;
                color: black !important;
                border: 1px solid #ccc;
                padding: 2px 4px;
            }
        }
        .print-title {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Print Only Header -->
    <div class="print-title">
        <h2 style="font-size: 20pt; margin-bottom: 5px;">รายการตารางการนัดหมายและการอบรม (MeetFlow)</h2>
        <p style="font-size: 11pt; color: #555;">แบ่งตามกลุ่มเวลา ณ วันที่ <?= date('d/m/Y H:i') ?></p>
    </div>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-list-check"></i> รายการ MeetFlow</h1>
                <p>ตารางสรุปนัดประชุมและอบรม แบ่งหมวดหมู่วันนี้และอนาคต</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-calendar-days"></i> กลับไปหน้าปฏิทิน</a>
                <?php if ($isAdmin): ?>
                    <a href="meeting_types.php" class="btn btn-secondary"><i class="fa-solid fa-tags"></i> จัดการประเภท</a>
                    <a href="users.php" class="btn btn-secondary"><i class="fa-solid fa-users-gear"></i> จัดการผู้ใช้</a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Filters Box -->
        <div class="report-filters-card">
            <form action="" method="GET">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">ค้นหาหัวข้อ / รายละเอียด / หนังสือ</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="เช่น นร 0505/...">
                    </div>
                    <div class="form-group">
                        <label for="start_date">ตั้งแต่วันที่</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">ถึงวันที่</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="form-group">
                        <label for="type">ประเภทการนัดหมาย</label>
                        <select id="type" name="type">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                            <?php foreach ($meetingTypes as $t): ?>
                                <option value="<?= htmlspecialchars($t['type_key']) ?>" <?= $type === $t['type_key'] ? 'selected' : '' ?>>เฉพาะ<?= htmlspecialchars($t['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> กรองข้อมูล</button>
                        <a href="list.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i> ล้างตัวกรอง</a>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> พิมพ์รายการ</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchTab(event, 'today')">
                <i class="fa-solid fa-calendar-day"></i> วันนี้
                <span class="tab-count"><?= count($todayMeetings) ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'upcoming')">
                <i class="fa-solid fa-angles-right"></i> เร็วๆ นี้
                <span class="tab-count"><?= count($upcomingMeetings) ?></span>
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'past')">
                <i class="fa-solid fa-clock-rotate-left"></i> ผ่านไปแล้ว
                <span class="tab-count"><?= count($pastMeetings) ?></span>
            </button>
        </div>

        <!-- Table Report -->
        <main class="report-table-card">
            
            <!-- 1. TODAY MEETINGS -->
            <div id="tab-today" class="tab-content active">
                <h3 class="print-only-heading" style="display: none; color: black; margin-bottom: 10px;">📅 รายการนัดหมายวันนี้</h3>
                <?php renderMeetingsTable($todayMeetings, $thaiMonthsShort, $isAdmin); ?>
            </div>

            <!-- 2. UPCOMING MEETINGS -->
            <div id="tab-upcoming" class="tab-content">
                <h3 class="print-only-heading" style="display: none; color: black; margin-bottom: 10px;">🚀 รายการนัดหมายเร็วๆ นี้</h3>
                <?php renderMeetingsTable($upcomingMeetings, $thaiMonthsShort, $isAdmin); ?>
            </div>

            <!-- 3. PAST MEETINGS -->
            <div id="tab-past" class="tab-content">
                <h3 class="print-only-heading" style="display: none; color: black; margin-bottom: 10px;">📂 รายการนัดหมายที่ผ่านไปแล้ว</h3>
                <?php renderMeetingsTable($pastMeetings, $thaiMonthsShort, $isAdmin); ?>
            </div>

        </main>
    </div>

    <script>
        // Tab switching logic
        function switchTab(event, tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
        }

        // Copy shareable meeting details link with micro-feedback
        function copyShareLink(meetingId, btnElement, meetingTitle) {
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            const shareUrl = `${window.location.origin}${basePath}/index.php?view=${meetingId}`;
            const copyText = meetingTitle ? `${meetingTitle}\n${shareUrl}` : shareUrl;
            
            navigator.clipboard.writeText(copyText).then(() => {
                const originalHTML = btnElement.innerHTML;
                btnElement.innerHTML = `<i class="fa-solid fa-check" style="color: var(--success);"></i>`;
                btnElement.disabled = true;
                setTimeout(() => {
                    btnElement.innerHTML = originalHTML;
                    btnElement.disabled = false;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy share link: ', err);
            });
        }

        // Admin Confirm meeting deletion (AJAX)
        function confirmDeleteMeeting(meetingId) {
            if (confirm('คุณต้องการลบข้อมูลการนัดประชุมนี้หรือไม่? (ข้อมูลผู้เข้าร่วมและไฟล์แนบจะถูกลบไปด้วย)')) {
                fetch('delete_meeting.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: meetingId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาดในการลบข้อมูล');
                });
            }
        }
    </script>
</body>
</html>

<?php
// Helper function to render table for each partition
function renderMeetingsTable($meetingsList, $thaiMonthsShort, $isAdmin) {
    global $meetingTypes;
    if (count($meetingsList) > 0): ?>
        <table style="min-width: 900px;">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 120px;">วันเวลา</th>
                    <th style="width: 100px;">ประเภท</th>
                    <th>หัวข้อการประชุม / รายละเอียด</th>
                    <th style="width: 140px;">เลขที่หนังสือ / เลขรับ</th>
                    <th style="width: 200px;">ผู้เข้าร่วม</th>
                    <th style="width: 110px;">ลิงก์ / ไฟล์</th>
                    <?php if ($isAdmin): ?>
                        <th style="width: 150px; text-align: center;">จัดการ</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $idx = 1;
                foreach ($meetingsList as $m): 
                    // Format short Date
                    $dateParts = explode('-', $m['meeting_date']);
                    $thaiYear = intval($dateParts[0]) + 543;
                    $mShort = $thaiMonthsShort[intval($dateParts[1])];
                    $dateStr = intval($dateParts[2]) . ' ' . $mShort . ' ' . substr($thaiYear, 2, 2);
                    
                    $start = substr($m['start_time'], 0, 5);
                    $end = substr($m['end_time'], 0, 5);
                    $isAllDay = ($start === '08:30' && $end === '16:30');
                    $timeStr = $isAllDay ? 'ตลอดทั้งวัน' : "$start - $end น.";
                    
                    $isTraining = (isset($m['meeting_type']) && $m['meeting_type'] === 'training');
                ?>
                    <tr>
                        <td><?= $idx++ ?></td>
                        <td>
                            <strong><?= $dateStr ?></strong><br>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);"><?= $timeStr ?></span>
                        </td>
                        <td>
                            <?php 
                            $typeKey = $m['meeting_type'] ?? 'meeting';
                            $typeName = isset($meetingTypes[$typeKey]) ? $meetingTypes[$typeKey]['type_name'] : 'ประชุม';
                            $typeColor = isset($meetingTypes[$typeKey]) ? $meetingTypes[$typeKey]['color'] : '#3b82f6';
                            $rgbaColor = hexToRgba($typeColor, 0.15);
                            $rgbaColorBorder = hexToRgba($typeColor, 0.3);
                            ?>
                            <span class="badge-type" style="--type-bg: <?= $rgbaColor ?>; --type-color: <?= $typeColor ?>; --type-border: <?= $rgbaColorBorder ?>;"><?= htmlspecialchars($typeName) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($m['title']) ?></strong>
                            <?php if (!empty($m['description'])): ?>
                                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;"><?= htmlspecialchars($m['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['doc_no']): ?>
                                <div style="font-size: 0.85rem;"><i class="fa-solid fa-file-invoice" style="font-size: 0.75rem; color: var(--primary-light);"></i> <?= htmlspecialchars($m['doc_no']) ?></div>
                            <?php endif; ?>
                            <?php if ($m['office_no']): ?>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 2px;"><i class="fa-solid fa-arrow-down-long" style="font-size: 0.75rem; color: var(--success);"></i> <?= htmlspecialchars($m['office_no']) ?></div>
                            <?php endif; ?>
                            <?php if (!$m['doc_no'] && !$m['office_no']): ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (count($m['attendees']) > 0): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach ($m['attendees'] as $att): ?>
                                        <span style="background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; display: inline-block;"><i class="fa-regular fa-user" style="font-size: 0.7rem; color: var(--primary-light);"></i> <?= htmlspecialchars($att) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">ไม่ได้ระบุ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if ($m['meeting_link']): ?>
                                    <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" title="เข้าประชุมออนไลน์" style="color: var(--accent);"><i class="fa-solid fa-video"></i></a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); opacity: 0.4;"><i class="fa-solid fa-video-slash"></i></span>
                                <?php endif; ?>

                                <?php if ($m['doc_file']): ?>
                                    <a href="uploads/<?= htmlspecialchars($m['doc_file']) ?>" target="_blank" title="เปิดเอกสารแนบ" style="color: var(--success);"><i class="fa-solid fa-file-arrow-down"></i></a>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); opacity: 0.4;"><i class="fa-solid fa-file-excel"></i></span>
                                <?php endif; ?>

                                <button type="button" class="btn-copy-share" onclick="copyShareLink(<?= $m['id'] ?>, this, '<?= htmlspecialchars(str_replace("'", "\\'", $m['title']), ENT_QUOTES, 'UTF-8') ?>')" title="คัดลอกลิงก์ข้อมูลนัดหมายเพื่อส่งต่อ" style="background: none; border: none; padding: 0; color: #38bdf8; cursor: pointer; font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center;"><i class="fa-solid fa-share-nodes"></i></button>
                            </div>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td>
                                <div class="list-actions">
                                    <a href="index.php?edit=<?= $m['id'] ?>&redirect=list.php" class="btn" style="padding: 6px 10px; font-size: 0.8rem; background: rgba(96, 165, 250, 0.15); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); border-radius: 6px;" title="แก้ไข"><i class="fa-solid fa-pen-to-square"></i> แก้ไข</a>
                                    <button type="button" class="btn" style="padding: 6px 10px; font-size: 0.8rem; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px;" onclick="confirmDeleteMeeting(<?= $m['id'] ?>)" title="ลบ"><i class="fa-solid fa-trash"></i> ลบ</button>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
            <i class="fa-regular fa-folder-open" style="font-size: 3rem; margin-bottom: 12px; color: var(--text-muted);"></i>
            <p>ไม่มีข้อมูลการประชุมในช่วงเวลานี้</p>
        </div>
    <?php endif;
}
?>
