<?php
// report.php - Meeting & Training Report Panel (View, Print, Export CSV)
require_once 'db.php';

session_start();

// Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

// Default Date Filters (default to current month)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-t');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // all, meeting, training

// Fetch Meetings matching filters
$meetings = [];
try {
    $sql = "SELECT * FROM meetings WHERE meeting_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];

    if ($search !== '') {
        $sql .= " AND (title LIKE ? OR description LIKE ? OR doc_no LIKE ? OR office_no LIKE ?)";
        $likeSearch = "%$search%";
        $params[] = $likeSearch;
        $params[] = $likeSearch;
        $params[] = $likeSearch;
        $params[] = $likeSearch;
    }

    if ($type === 'meeting') {
        $sql .= " AND (title NOT LIKE '%อบรม%' AND description NOT LIKE '%อบรม%')";
    } elseif ($type === 'training') {
        $sql .= " AND (title LIKE '%อบรม%' OR description LIKE '%อบรม%')";
    }

    $sql .= " ORDER BY meeting_date ASC, start_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meetings = $stmt->fetchAll();

    // Fetch attendees for all filtered meetings
    foreach ($meetings as $key => $meeting) {
        $stmtAttendees = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
        $stmtAttendees->execute([$meeting['id']]);
        $meetings[$key]['attendees'] = $stmtAttendees->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (\PDOException $e) {
    $dbError = $e->getMessage();
}


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
    <title>รายงานสรุปการนัดหมาย - MeetFlow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Report specific styles */
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
        }
        .badge-meeting {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary-light);
        }
        .badge-training {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
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
            .app-header, .report-filters-card, .btn, footer, .back-home, .header-actions {
                display: none !important;
            }
            .report-table-card {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
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
        <h2 style="font-size: 20pt; margin-bottom: 5px;">รายงานสรุปตารางการนัดหมายและการอบรม (MeetFlow)</h2>
        <p style="font-size: 11pt; color: #555;">ตั้งแต่วันที่ <?= htmlspecialchars($startDate) ?> ถึง <?= htmlspecialchars($endDate) ?></p>
    </div>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-file-invoice"></i> รายงาน MeetFlow</h1>
                <p>ออกรายงาน สรุปสถิติงานนัดประชุมและวันฝึกอบรม</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าปฏิทิน</a>
            </div>
        </header>

        <!-- Filters Box -->
        <div class="report-filters-card">
            <form action="" method="GET">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">ค้นหาหัวข้อ / เลขที่หนังสือ / เลขรับ</label>
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
                            <option value="meeting" <?= $type === 'meeting' ? 'selected' : '' ?>>เฉพาะนัดประชุม</option>
                            <option value="training" <?= $type === 'training' ? 'selected' : '' ?>>เฉพาะการอบรม</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> กรองข้อมูล</button>
                        <a href="report.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i> ล้างตัวกรอง</a>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> พิมพ์รายงาน</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Report -->
        <main class="report-table-card">
            <?php if (count($meetings) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 120px;">วันเวลา</th>
                            <th style="width: 100px;">ประเภท</th>
                            <th>หัวข้อการประชุม / รายละเอียด</th>
                            <th style="width: 140px;">เลขที่หนังสือ / เลขรับ</th>
                            <th style="width: 200px;">ผู้เข้าร่วม</th>
                            <th style="width: 110px;">ลิงก์ / ไฟล์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $idx = 1;
                        foreach ($meetings as $m): 
                            // Format short Date
                            $dateParts = explode('-', $m['meeting_date']);
                            $thaiYear = intval($dateParts[0]) + 543;
                            $mShort = $thaiMonthsShort[intval($dateParts[1])];
                            $dateStr = intval($dateParts[2]) . ' ' . $mShort . ' ' . substr($thaiYear, 2, 2);
                            
                            $start = substr($m['start_time'], 0, 5);
                            $end = substr($m['end_time'], 0, 5);
                            $isAllDay = ($start === '00:00' && $end === '23:59');
                            $timeStr = $isAllDay ? 'ตลอดทั้งวัน' : "$start - $end น.";
                            
                            $isTraining = (strpos(mb_strtolower($m['title']), 'อบรม') !== false || strpos(mb_strtolower($m['description']), 'อบรม') !== false);
                        ?>
                            <tr>
                                <td><?= $idx++ ?></td>
                                <td>
                                    <strong><?= $dateStr ?></strong><br>
                                    <span style="font-size: 0.8rem; color: var(--text-secondary);"><?= $timeStr ?></span>
                                </td>
                                <td>
                                    <?php if ($isTraining): ?>
                                        <span class="badge-type badge-training">อบรม</span>
                                    <?php else: ?>
                                        <span class="badge-type badge-meeting">ประชุม</span>
                                    <?php endif; ?>
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
                                    <div style="display: flex; gap: 8px;">
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
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <i class="fa-regular fa-folder-open" style="font-size: 3rem; margin-bottom: 12px; color: var(--text-muted);"></i>
                    <p>ไม่มีข้อมูลการประชุมในช่วงเวลาดังกล่าว</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
