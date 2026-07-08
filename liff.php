<?php
// liff.php - LINE LIFF Mobile-First Search Interface
require_once 'db.php';

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchResults = [];
$searched = false;

// Month names in Thai for rendering dates
$thaiMonths = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
    5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
    9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];

if ($searchQuery !== '') {
    $searched = true;
    try {
        // Query to search by doc_no or office_no
        $sql = "SELECT * FROM meetings 
                WHERE doc_no LIKE ? OR office_no LIKE ? 
                ORDER BY meeting_date DESC, start_time ASC";
        $stmt = $pdo->prepare($sql);
        $likeQuery = "%$searchQuery%";
        $stmt->execute([$likeQuery, $likeQuery]);
        $searchResults = $stmt->fetchAll();
        
        // Fetch attendees for all matching meetings
        foreach ($searchResults as $key => $meeting) {
            $stmtAttendees = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
            $stmtAttendees->execute([$meeting['id']]);
            $searchResults[$key]['attendees'] = $stmtAttendees->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (\PDOException $e) {
        $dbError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetFlow Search - LINE LIFF</title>
    <!-- CSS and Fonts -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Extra mobile overrides for LIFF */
        body {
            background: linear-gradient(135deg, #090d16 0%, #0f172a 100%);
        }
        .liff-container {
            padding: 16px;
            padding-bottom: 80px; /* Safe space for bottom navigation/bar */
        }
        .meeting-card {
            border-left: 4px solid var(--primary);
        }
        .meeting-card.expanded {
            border-left-color: var(--accent);
        }
        .expanded-details {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            animation: fadeIn 0.3s ease;
        }
        .meeting-card.expanded .expanded-details {
            display: block;
        }
        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 15px;
        }
        .card-actions .btn {
            font-size: 0.85rem;
            padding: 8px 12px;
            justify-content: center;
        }
        .back-home {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .back-home:hover {
            color: white;
        }
        .search-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            margin-top: 5px;
            display: inline-block;
        }
    </style>
</head>
<body class="liff-body">
    <div class="liff-container">
        <!-- Back link to Main Calendar Dashboard -->
        <a href="index.php" class="back-home"><i class="fa-solid fa-arrow-left"></i> กลับหน้าหลักปฏิทิน</a>

        <!-- LIFF Header -->
        <header class="liff-header">
            <h1><i class="fa-solid fa-magnifying-glass"></i> MeetFlow Search</h1>
            <p>ค้นหาข้อมูลงนัดประชุมหรือโครงการฝึกอบรมสำหรับผู้บริหาร</p>
        </header>

        <!-- Search Box -->
        <div class="search-box-card">
            <form action="" method="GET">
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="เลขหนังสือ หรือ เลขรับสำนักงาน..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> ค้นหา</button>
                </div>
            </form>
        </div>

        <!-- Search Results Section -->
        <main class="results-section">
            <?php if ($searched): ?>
                <h3>ผลการค้นหาสำหรับ "<?= htmlspecialchars($searchQuery) ?>" (พบ <?= count($searchResults) ?> รายการ)</h3>
                
                <?php if (count($searchResults) > 0): ?>
                    <?php foreach ($searchResults as $meeting): ?>
                        <?php
                        // Parse date to Thai format
                        $dateParts = explode('-', $meeting['meeting_date']);
                        $thaiYear = intval($dateParts[0]) + 543;
                        $monthName = $thaiMonths[intval($dateParts[1])];
                        $dateStr = intval($dateParts[2]) . ' ' . $monthName . ' ' . $thaiYear;
                        
                        $start = date('H:i', strtotime($meeting['start_time']));
                        $end = date('H:i', strtotime($meeting['end_time']));
                        
                        // Check if it's training
                        $isTraining = (strpos(mb_strtolower($meeting['title']), 'อบรม') !== false || strpos(mb_strtolower($meeting['description']), 'อบรม') !== false);
                        $typeBadge = $isTraining ? '<span class="card-time-badge" style="background: rgba(16, 185, 129, 0.15); color: var(--success);"><i class="fa-solid fa-graduation-cap"></i> วันฝึกอบรม</span>' : '<span class="card-time-badge" style="background: rgba(99, 102, 241, 0.15); color: var(--primary-light);"><i class="fa-solid fa-circle"></i> นัดประชุม</span>';
                        ?>
                        <div class="meeting-card" onclick="toggleCard(this)">
                            <div class="card-header">
                                <span class="card-date-badge"><i class="fa-regular fa-calendar"></i> <?= $dateStr ?></span>
                                <?= $typeBadge ?>
                            </div>
                            
                            <h4><?= htmlspecialchars($meeting['title']) ?></h4>
                            
                            <div class="card-meta-info" style="border: none; padding-top: 0; margin-top: 0;">
                                <div class="meta-item"><i class="fa-regular fa-clock"></i> <?= $start ?> - <?= $end ?> น.</div>
                                <?php if ($meeting['doc_no']): ?>
                                    <div class="meta-item"><i class="fa-solid fa-file-invoice"></i> <?= htmlspecialchars($meeting['doc_no']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Expanded Meeting Details -->
                            <div class="expanded-details">
                                <p class="description" style="display: block; -webkit-line-clamp: none; overflow: visible; margin-bottom: 12px;">
                                    <strong>รายละเอียด:</strong><br>
                                    <?= nl2br(htmlspecialchars($meeting['description'] ?: 'ไม่มีรายละเอียดเพิ่มเติม')) ?>
                                </p>
                                
                                <div style="margin-bottom: 12px;">
                                    <strong>เลขรับสำนักงาน:</strong> <?= htmlspecialchars($meeting['office_no'] ?: '-') ?>
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <strong>ผู้เข้าร่วมประชุม (<?= count($meeting['attendees']) ?> คน):</strong>
                                    <div class="attendee-list" style="margin-top: 6px;">
                                        <?php if (count($meeting['attendees']) > 0): ?>
                                            <?php foreach ($meeting['attendees'] as $name): ?>
                                                <span class="attendee-name" style="padding: 4px 10px; font-size: 0.8rem;"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($name) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">ไม่ได้ระบุผู้เข้าร่วมประชุม</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($meeting['meeting_link']): ?>
                                <div style="margin-bottom: 12px; display: flex; flex-direction: column; gap: 4px;">
                                    <strong>ลิงก์ประชุมออนไลน์:</strong>
                                    <div style="display: flex; align-items: center; gap: 8px; background: rgba(15, 23, 42, 0.4); padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border-glass);">
                                        <a href="<?= htmlspecialchars($meeting['meeting_link']) ?>" target="_blank" class="link" style="font-size: 0.85rem; word-break: break-all; text-decoration: underline; flex-grow: 1; color: var(--accent);"><i class="fa-solid fa-video"></i> <?= htmlspecialchars($meeting['meeting_link']) ?></a>
                                        <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem; white-space: nowrap; flex-shrink: 0;" onclick="event.stopPropagation(); copyToClipboard('<?= htmlspecialchars(str_replace("'", "\\'", $meeting['meeting_link'])) ?>', this)"><i class="fa-regular fa-copy"></i> คัดลอก</button>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <?php if ($meeting['meeting_link']): ?>
                                        <a href="<?= htmlspecialchars($meeting['meeting_link']) ?>" target="_blank" class="btn btn-primary" onclick="event.stopPropagation();"><i class="fa-solid fa-video"></i> เข้าประชุมออนไลน์</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;"><i class="fa-solid fa-video-slash"></i> ไม่มีลิงก์</button>
                                    <?php endif; ?>

                                    <?php if ($meeting['doc_file']): ?>
                                        <a href="uploads/<?= htmlspecialchars($meeting['doc_file']) ?>" target="_blank" class="btn btn-secondary" onclick="event.stopPropagation();"><i class="fa-solid fa-file-arrow-down"></i> ดาวน์โหลดไฟล์</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed;"><i class="fa-solid fa-file-excel"></i> ไม่มีไฟล์แนบ</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fa-regular fa-folder-open"></i>
                        <p>ไม่พบข้อมูลการประชุมหรือวันฝึกอบรมที่ตรงกับเงื่อนไข</p>
                        <span class="search-badge">กรุณาตรวจสอบเลขหนังสือ หรือเลขรับสำนักงานอีกครั้ง</span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Quick Tips before searching -->
                <div class="no-results" style="padding-top: 20px;">
                    <i class="fa-solid fa-keyboard" style="color: var(--primary-light);"></i>
                    <p>ป้อนเลขที่หนังสือหรือเลขรับสำนักงานเพื่อเริ่มต้นค้นหา</p>
                    <span class="search-badge">ตัวอย่าง: นร 0505/ว123 หรือ 4567/2569</span>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- LINE LIFF SDK Initialization (Optional but ready to use) -->
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        // Copy to clipboard helper with visual micro-feedback
        function copyToClipboard(text, btnElement) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHTML = btnElement.innerHTML;
                btnElement.innerHTML = `<i class="fa-solid fa-check" style="color: var(--success);"></i> คัดลอกแล้ว`;
                btnElement.disabled = true;
                setTimeout(() => {
                    btnElement.innerHTML = originalHTML;
                    btnElement.disabled = false;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        // Toggle Expanded Card Details
        function toggleCard(cardElement) {
            cardElement.classList.toggle('expanded');
        }

        // Initialize LIFF
        document.addEventListener("DOMContentLoaded", function() {
            liff.init({
                liffId: "YOUR-LIFF-ID" // Replace with actual LIFF ID when deploying to LINE Developers console
            }).then(() => {
                console.log("LINE LIFF Initialized successfully.");
                if (liff.isLoggedIn()) {
                    // We can access user profile if needed
                }
            }).catch((err) => {
                console.warn("LINE LIFF initialization failed: User might be running in a standard mobile browser.", err.message);
            });
        });
    </script>
</body>
</html>
