<?php
// index.php - MeetFlow Calendar Dashboard (with Admin Auth controls)
require_once 'db.php';

session_start();

// Check if user is logged in as Admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Get selected month and year, default to current month/year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate inputs
if ($month < 1 || $month > 12) {
    $month = intval(date('n'));
}
if ($year < 1970 || $year > 2100) {
    $year = intval(date('Y'));
}

// Calculate month details
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$dayOfWeek = date('w', $firstDayOfMonth); // 0 (Sunday) to 6 (Saturday)

// Month names in Thai
$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// Calculate previous/next month/year links
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

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

// Fetch meetings for this month
try {
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
    
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE meeting_date BETWEEN ? AND ? ORDER BY start_time ASC");
    $stmt->execute([$startDate, $endDate]);
    $meetings = $stmt->fetchAll();
    
    // Group meetings by day number
    $meetingsByDay = [];
    foreach ($meetings as $meeting) {
        $dayNum = intval(date('j', strtotime($meeting['meeting_date'])));
        $meetingsByDay[$dayNum][] = $meeting;
    }
} catch (\PDOException $e) {
    $meetingsByDay = [];
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeetFlow - ปฏิทินนัดประชุมและวันอบรม</title>
    <!-- CSS and Fonts -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-calendar-days"></i> MeetFlow</h1>
                <p>ระบบบันทึกตารางนัดประชุม อบรม และสัมมนา</p>
            </div>
            <div class="header-actions">
                <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="openAddMeetingModal(null)"><i class="fa-solid fa-plus"></i> บันทึกข้อมูลใหม่</button>
                    <a href="list.php" class="btn btn-secondary"><i class="fa-solid fa-list-check"></i> รายการ</a>
                    <a href="meeting_types.php" class="btn btn-secondary"><i class="fa-solid fa-tags"></i> จัดการประเภท</a>
                    <a href="users.php" class="btn btn-secondary"><i class="fa-solid fa-users-gear"></i> จัดการผู้ใช้</a>
                    <a href="settings.php" class="btn btn-secondary"><i class="fa-solid fa-gear"></i> ตั้งค่าระบบ</a>
                    <a href="logout.php" class="btn btn-danger" style="background: var(--danger-gradient);"><i class="fa-solid fa-arrow-right-from-bracket"></i> ออกจากระบบ</a>
                <?php else: ?>
                    <a href="list.php" class="btn btn-secondary"><i class="fa-solid fa-list-check"></i> รายการ</a>
                    <a href="liff.php" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> ค้นหา</a>
                    <a href="login.php" class="btn btn-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> เข้าสู่ระบบ (Admin)</a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Calendar Wrapper -->
        <main class="calendar-wrapper">
            <!-- Calendar Navigation and Header -->
            <div class="calendar-controls">
                <div class="calendar-title-section">
                    <span class="calendar-month-year"><?= $thaiMonths[$month] ?> <?= $year + 543 ?></span>
                    <div class="nav-buttons">
                        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn" title="เดือนก่อนหน้า"><i class="fa-solid fa-chevron-left"></i></a>
                        <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="nav-btn" title="เดือนปัจจุบัน"><i class="fa-solid fa-calendar-day"></i></a>
                        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn" title="เดือนถัดไป"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="filter-legend">
                    <?php foreach ($meetingTypes as $t): ?>
                        <span style="margin-right: 15px;"><i class="fa-solid fa-circle" style="color: <?= htmlspecialchars($t['color']) ?>; font-size: 0.8rem; margin-right: 5px;"></i> <?= htmlspecialchars($t['type_name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Calendar Days Header -->
            <div class="calendar-grid">
                <div class="day-label sunday">อา.</div>
                <div class="day-label">จ.</div>
                <div class="day-label">อ.</div>
                <div class="day-label">พ.</div>
                <div class="day-label">พฤ.</div>
                <div class="day-label">ศ.</div>
                <div class="day-label saturday">ส.</div>

                <?php
                // Render empty day spaces before start date
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }

                // Render current month days
                $todayDate = date('Y-m-d');
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDayDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isTodayClass = ($currentDayDate === $todayDate) ? 'today' : '';
                    
                    // Calculate weekday to set Saturday/Sunday styling class names
                    $weekday = date('w', strtotime($currentDayDate));
                    $weekendClass = '';
                    if ($weekday == 0) {
                        $weekendClass = 'sunday';
                    } elseif ($weekday == 6) {
                        $weekendClass = 'saturday';
                    }
                    
                    echo '<div class="calendar-day ' . $isTodayClass . ' ' . $weekendClass . '" onclick="handleDayClick(\'' . $currentDayDate . '\', event)">';
                    echo '<span class="day-number">' . $day . '</span>';
                    echo '<div class="day-events">';
                    
                    if (isset($meetingsByDay[$day])) {
                        foreach ($meetingsByDay[$day] as $meeting) {
                            $isAllDay = (substr($meeting['start_time'], 0, 5) === '08:30' && substr($meeting['end_time'], 0, 5) === '16:30');
                            $timeStr = $isAllDay ? 'ตลอดทั้งวัน' : date('H:i', strtotime($meeting['start_time'])) . ' น.';
                            
                            $typeKey = $meeting['meeting_type'] ?? 'meeting';
                            $typeColor = isset($meetingTypes[$typeKey]) ? $meetingTypes[$typeKey]['color'] : '#3b82f6';
                            $rgbaColor = hexToRgba($typeColor, 0.15);
                            $rgbaColorHover = hexToRgba($typeColor, 0.25);
                            
                            echo '<div class="event-badge" style="--type-color: ' . $typeColor . '; --type-bg: ' . $rgbaColor . '; --type-bg-hover: ' . $rgbaColorHover . ';" onclick="viewMeetingDetails(' . $meeting['id'] . ', event)">';
                            echo '<span class="event-time"><i class="fa-regular fa-clock"></i> ' . $timeStr . '</span>';
                            echo '<span class="event-title">' . htmlspecialchars($meeting['title']) . '</span>';
                            echo '</div>';
                        }
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }

                // Render empty spaces after end date to complete the grid row
                $totalCells = $dayOfWeek + $daysInMonth;
                $remainingCells = 7 - ($totalCells % 7);
                if ($remainingCells < 7) {
                    for ($i = 0; $i < $remainingCells; $i++) {
                        echo '<div class="calendar-day other-month"></div>';
                    }
                }
                ?>
            </div>
        </main>
    </div>

    <!-- DIALOG MODAL: Add/Edit Meeting (Only accessible to Admin) -->
    <?php if ($isAdmin): ?>
    <dialog id="meetingFormDialog">
        <div class="dialog-header">
            <h2 id="modalTitle">บันทึกข้อมูลการประชุม</h2>
            <button class="close-dialog" onclick="closeMeetingFormDialog()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="meetingForm" onsubmit="handleFormSubmit(event)" enctype="multipart/form-data">
            <input type="hidden" id="meeting_id" name="id" value="0">
            
            <div class="form-group">
                <label for="title">หัวข้อการนัดประชุม / อบรม <span style="color: var(--danger)">*</span></label>
                <input type="text" id="title" name="title" required placeholder="เช่น ประชุมทบทวนแผนงาน หรือ อบรมคอมพิวเตอร์">
            </div>
            
            <div class="form-group">
                <label for="description">รายละเอียดเพิ่มเติม</label>
                <textarea id="description" name="description" rows="3" placeholder="ระบุเนื้อหาการประชุม ยินดีต้อนรับผู้เข้าร่วม..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="meeting_type">ประเภทการนัดหมาย <span style="color: var(--danger)">*</span></label>
                <select id="meeting_type" name="meeting_type" required>
                    <?php foreach ($meetingTypes as $t): ?>
                        <option value="<?= htmlspecialchars($t['type_key']) ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="meeting_date">วันที่นัดหมาย <span style="color: var(--danger)">*</span></label>
                    <input type="date" id="meeting_date" name="meeting_date" required>
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-weight: 500; font-size: 0.9rem;">เวลาการประชุม / อบรม <span style="color: var(--danger)">*</span></span>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; cursor: pointer; user-select: none; color: var(--text-secondary);">
                            <input type="checkbox" id="is_all_day" name="is_all_day" onchange="toggleAllDay(this.checked)" style="width: auto; margin: 0;">
                            ตลอดทั้งวัน
                        </label>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div style="flex: 1;">
                            <label for="start_time" style="font-size: 0.8rem; color: var(--text-muted);">เวลาเริ่ม</label>
                            <input type="time" id="start_time" name="start_time" required>
                        </div>
                        <div style="flex: 1;">
                            <label for="end_time" style="font-size: 0.8rem; color: var(--text-muted);">เวลาสิ้นสุด</label>
                            <input type="time" id="end_time" name="end_time" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="doc_no">เลขที่หนังสือ / เลขอ้างอิง</label>
                    <input type="text" id="doc_no" name="doc_no" placeholder="เช่น นร 0505/ว123">
                </div>
                <div class="form-group">
                    <label for="office_no">เลขรับสำนักงาน</label>
                    <input type="text" id="office_no" name="office_no" placeholder="เช่น รับที่ 4567/2569">
                </div>
            </div>

            <div class="form-group">
                <label for="meeting_link">ลิงก์ประชุมออนไลน์ (MS Teams, Zoom, Google Meet)</label>
                <input type="url" id="meeting_link" name="meeting_link" placeholder="https://teams.microsoft.com/...">
            </div>

            <!-- Upload File -->
            <div class="form-group">
                <label>แนบไฟล์หนังสือ (ไฟล์ PDF, Word, Excel, รูปภาพ)</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="doc_file" name="doc_file">
                    <div class="file-upload-label">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span id="file-label-text">คลิกเพื่ออัปโหลดไฟล์ หรือ ลากไฟล์มาวางที่นี่</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">สูงสุด 10MB (PDF, DOCX, XLSX, JPG, PNG)</span>
                    </div>
                </div>
                <div id="current_file_indicator" style="margin-top: 8px; font-size: 0.85rem; display: none;">
                    <i class="fa-solid fa-paperclip"></i> ไฟล์ปัจจุบัน: <span id="current_file_name" style="color: var(--accent);"></span>
                </div>
            </div>

            <!-- Attendee Management -->
            <div class="form-group">
                <label>ผู้เข้าร่วมประชุม / อบรม (ระบุได้หลายคน)</label>
                <div class="attendees-container">
                    <div class="attendee-input-row">
                        <input type="text" id="attendee_input" placeholder="พิมพ์ชื่อผู้เข้าร่วมประชุม">
                        <button type="button" class="btn btn-secondary" onclick="addAttendee()"><i class="fa-solid fa-user-plus"></i></button>
                    </div>
                    <div class="attendee-tags" id="attendee_tags_list">
                        <!-- Dynamic Tags here -->
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; border-top: 1px solid var(--border-glass); padding-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeMeetingFormDialog()">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> บันทึกข้อมูล</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>

    <!-- DIALOG MODAL: View Meeting Details -->
    <dialog id="meetingDetailsDialog">
        <div class="dialog-header">
            <h2>รายละเอียดการนัดประชุม / อบรม</h2>
            <button class="close-dialog" onclick="closeMeetingDetailsDialog()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="meeting-details-panel">
            <h3 id="view_title" style="font-size: 1.5rem; font-weight: 800; color: white;">หัวข้อประชุม</h3>
            
            <div class="detail-row">
                <div class="detail-label">วันเวลา</div>
                <div class="detail-value" id="view_time">1 มกราคม 2569 เวลา 09:00 - 12:00 น.</div>
            </div>
            
            <div class="detail-row">
                <div class="detail-label">รายละเอียด</div>
                <div class="detail-value" id="view_description">-</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">เลขที่หนังสือ</div>
                <div class="detail-value" id="view_doc_no">-</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">เลขรับสำนักงาน</div>
                <div class="detail-value" id="view_office_no">-</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">ประชุมออนไลน์</div>
                <div class="detail-value" id="view_meeting_link_container">-</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">เอกสารแนบ</div>
                <div class="detail-value" id="view_file_container">-</div>
            </div>

            <div class="detail-row" style="border-bottom: none;">
                <div class="detail-label">ผู้เข้าร่วมประชุม</div>
                <div class="detail-value">
                    <div class="attendee-list" id="view_attendees">-</div>
                </div>
            </div>
        </div>
        <div class="dialog-footer-actions">
            <button type="button" class="btn btn-danger" id="deleteMeetingBtn" style="display: none;"><i class="fa-solid fa-trash"></i> ลบข้อมูล</button>
            <button type="button" class="btn btn-info" id="shareMeetingBtn" style="background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); border-radius: 6px;"><i class="fa-solid fa-share-nodes"></i> คัดลอกลิงก์ข้อมูล</button>
            <button type="button" class="btn btn-primary" id="editMeetingBtn" style="display: none;"><i class="fa-solid fa-edit"></i> แก้ไขข้อมูล</button>
            <button type="button" class="btn btn-secondary" onclick="closeMeetingDetailsDialog()">ปิด</button>
        </div>
    </dialog>

    <script>
        // Global variables to control UI logic based on Admin State
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        
        // Track list of attendees in memory during Add/Edit
        let currentAttendeesList = [];

        // File upload UI listener (Only loaded if element exists)
        const docFileInput = document.getElementById('doc_file');
        if (docFileInput) {
            docFileInput.addEventListener('change', function(e) {
                const fileName = e.target.files[0] ? e.target.files[0].name : "คลิกเพื่ออัปโหลดไฟล์ หรือ ลากไฟล์มาวางที่นี่";
                document.getElementById('file-label-text').innerText = fileName;
            });
        }

        // Automatically trigger edit modal on load if edit parameter is present (redirected from list.php)
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');
            const viewId = urlParams.get('view');
            if (editId && isAdmin) {
                fetch(`get_meeting.php?id=${editId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            openEditMeetingModal(data.meeting);
                        }
                    })
                    .catch(err => console.error('Error fetching edit details:', err));
            } else if (viewId) {
                viewMeetingDetails(viewId);
            }
        });

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

        // Toggle All Day times
        function toggleAllDay(checked) {
            const startInput = document.getElementById('start_time');
            const endInput = document.getElementById('end_time');
            if (checked) {
                startInput.value = "08:30";
                endInput.value = "16:30";
                startInput.readOnly = true;
                endInput.readOnly = true;
                startInput.style.opacity = "0.5";
                endInput.style.opacity = "0.5";
            } else {
                startInput.readOnly = false;
                endInput.readOnly = false;
                startInput.style.opacity = "1";
                endInput.style.opacity = "1";
            }
        }

        // Day click opens Add Form (Only if admin)
        function handleDayClick(dateStr, event) {
            // Prevent event bubbling if clicking event badges
            if (event.target.closest('.event-badge')) return;
            if (!isAdmin) return; // Do nothing for normal users
            openAddMeetingModal(dateStr);
        }

        // Open Dialog to Add Meeting
        function openAddMeetingModal(dateStr = null) {
            if (!isAdmin) return;
            document.getElementById('meetingForm').reset();
            document.getElementById('meeting_id').value = "0";
            document.getElementById('modalTitle').innerText = "บันทึกข้อมูลการนัดประชุม / อบรม";
            document.getElementById('file-label-text').innerText = "คลิกเพื่ออัปโหลดไฟล์ หรือ ลากไฟล์มาวางที่นี่";
            document.getElementById('current_file_indicator').style.display = 'none';
            
            // Clear attendees
            currentAttendeesList = [];
            renderAttendeeTags();
            
            if (dateStr) {
                document.getElementById('meeting_date').value = dateStr;
            } else {
                // Default to today
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('meeting_date').value = today;
            }

            // Default times
            document.getElementById('meeting_type').value = "meeting";
            document.getElementById('is_all_day').checked = false;
            toggleAllDay(false);
            document.getElementById('start_time').value = "09:00";
            document.getElementById('end_time').value = "12:00";

            const dialog = document.getElementById('meetingFormDialog');
            dialog.showModal();
        }

        // Close Add/Edit Dialog
        function closeMeetingFormDialog() {
            if (isAdmin) {
                document.getElementById('meetingFormDialog').close();
                // Redirection check for cancel/close operations
                const urlParams = new URLSearchParams(window.location.search);
                const redirectUrl = urlParams.get('redirect');
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else if (urlParams.get('edit')) {
                    window.location.href = 'index.php';
                }
            }
        }

        // Add attendee to memory list
        function addAttendee() {
            if (!isAdmin) return;
            const input = document.getElementById('attendee_input');
            const name = input.value.trim();
            if (name && !currentAttendeesList.includes(name)) {
                currentAttendeesList.push(name);
                renderAttendeeTags();
                input.value = '';
            }
            input.focus();
        }

        // Handle Enter key in attendee input (Only if admin)
        const attInput = document.getElementById('attendee_input');
        if (attInput) {
            attInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addAttendee();
                }
            });
        }

        // Remove attendee from list
        function removeAttendee(name) {
            if (!isAdmin) return;
            currentAttendeesList = currentAttendeesList.filter(item => item !== name);
            renderAttendeeTags();
        }

        // Render tags inside form
        function renderAttendeeTags() {
            if (!isAdmin) return;
            const container = document.getElementById('attendee_tags_list');
            container.innerHTML = '';
            
            currentAttendeesList.forEach(name => {
                const tag = document.createElement('span');
                tag.className = 'attendee-tag';
                tag.innerHTML = `${name} <span class="remove-tag" onclick="removeAttendee('${name}')">&times;</span>`;
                
                // Add hidden input fields for the form submit array
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'attendees[]';
                hiddenInput.value = name;
                tag.appendChild(hiddenInput);
                
                container.appendChild(tag);
            });
        }

        // View details dialog
        function viewMeetingDetails(meetingId, event) {
            if (event) {
                event.stopPropagation();
            }

            fetch(`get_meeting.php?id=${meetingId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const meeting = data.meeting;
                        document.getElementById('view_title').innerText = meeting.title;
                        
                        // Parse date to Thai format
                        const dateParts = meeting.meeting_date.split('-');
                        const thaiYear = parseInt(dateParts[0]) + 543;
                        const months = <?= json_encode($thaiMonths) ?>;
                        const monthName = months[parseInt(dateParts[1])];
                        const dateStr = `${parseInt(dateParts[2])} ${monthName} ${thaiYear}`;
                        const start = meeting.start_time.substring(0, 5);
                        const end = meeting.end_time.substring(0, 5);
                        const isAllDay = (start === '08:30' && end === '16:30');
                        const timeStr = isAllDay ? 'ตลอดทั้งวัน' : `${start} - ${end} น.`;
                        
                        document.getElementById('view_time').innerHTML = `<i class="fa-regular fa-calendar-check"></i> ${dateStr} &nbsp;&nbsp;&nbsp; <i class="fa-regular fa-clock"></i> ${timeStr}`;
                        document.getElementById('view_description').innerText = meeting.description || 'ไม่มีรายละเอียดเพิ่มเติม';
                        document.getElementById('view_doc_no').innerText = meeting.doc_no || '-';
                        document.getElementById('view_office_no').innerText = meeting.office_no || '-';
                        
                        // Link container
                        const linkContainer = document.getElementById('view_meeting_link_container');
                        if (meeting.meeting_link) {
                            linkContainer.innerHTML = `
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; width: 100%;">
                                    <a href="${meeting.meeting_link}" target="_blank" class="detail-value link" style="word-break: break-all; max-width: calc(100% - 100px); text-decoration: underline;"><i class="fa-solid fa-video"></i> ${meeting.meeting_link}</a>
                                    <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem; margin-left: auto;" onclick="copyToClipboard('${meeting.meeting_link.replace(/'/g, "\\'")}', this)"><i class="fa-regular fa-copy"></i> คัดลอก</button>
                                </div>
                            `;
                        } else {
                            linkContainer.innerText = '-';
                        }
                        
                        // Attachment container
                        const fileContainer = document.getElementById('view_file_container');
                        if (meeting.doc_file) {
                            fileContainer.innerHTML = `<a href="uploads/${meeting.doc_file}" target="_blank" class="detail-value link"><i class="fa-solid fa-file-pdf"></i> เปิดเอกสารแนบ</a>`;
                        } else {
                            fileContainer.innerText = '-';
                        }

                        // Attendees list
                        const attendeesList = document.getElementById('view_attendees');
                        attendeesList.innerHTML = '';
                        if (meeting.attendees && meeting.attendees.length > 0) {
                            meeting.attendees.forEach(name => {
                                const badge = document.createElement('span');
                                badge.className = 'attendee-name';
                                badge.innerHTML = `<i class="fa-regular fa-user"></i> ${name}`;
                                attendeesList.appendChild(badge);
                            });
                        } else {
                            attendeesList.innerText = '-';
                        }

                        // Configure Share link button
                        const shareBtn = document.getElementById('shareMeetingBtn');
                        if (shareBtn) {
                            shareBtn.onclick = (e) => {
                                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                                const shareUrl = `${window.location.origin}${basePath}/index.php?view=${meeting.id}`;
                                copyToClipboard(shareUrl, e.currentTarget);
                            };
                        }

                        // Show/Hide Edit & Delete controls depending on Admin role
                        const delBtn = document.getElementById('deleteMeetingBtn');
                        const editBtn = document.getElementById('editMeetingBtn');
                        
                        if (isAdmin) {
                            delBtn.style.display = 'inline-flex';
                            editBtn.style.display = 'inline-flex';
                            delBtn.onclick = () => confirmDeleteMeeting(meeting.id);
                            editBtn.onclick = () => openEditMeetingModal(meeting);
                        } else {
                            delBtn.style.display = 'none';
                            editBtn.style.display = 'none';
                        }

                        const dialog = document.getElementById('meetingDetailsDialog');
                        dialog.showModal();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาดในการเรียกดูข้อมูล');
                });
        }

        // Close details dialog
        function closeMeetingDetailsDialog() {
            document.getElementById('meetingDetailsDialog').close();
            // Clear URL view parameter if present
            const url = new URL(window.location);
            if (url.searchParams.has('view')) {
                url.searchParams.delete('view');
                window.history.replaceState({}, '', url.toString());
            }
        }

        // Open edit dialog using loaded details (Only if Admin)
        function openEditMeetingModal(meeting) {
            if (!isAdmin) return;
            closeMeetingDetailsDialog();
            
            document.getElementById('meetingForm').reset();
            document.getElementById('meeting_id').value = meeting.id;
            document.getElementById('modalTitle').innerText = "แก้ไขข้อมูลการประชุม";
            document.getElementById('title').value = meeting.title;
            document.getElementById('description').value = meeting.description || '';
            document.getElementById('meeting_type').value = meeting.meeting_type || "meeting";
            document.getElementById('meeting_date').value = meeting.meeting_date;
            const startVal = meeting.start_time.substring(0, 5);
            const endVal = meeting.end_time.substring(0, 5);
            const isAllDay = (startVal === '08:30' && endVal === '16:30');
            document.getElementById('is_all_day').checked = isAllDay;
            toggleAllDay(isAllDay);
            document.getElementById('start_time').value = startVal;
            document.getElementById('end_time').value = endVal;
            document.getElementById('doc_no').value = meeting.doc_no || '';
            document.getElementById('office_no').value = meeting.office_no || '';
            document.getElementById('meeting_link').value = meeting.meeting_link || '';
            
            // Set uploaded file display
            document.getElementById('file-label-text').innerText = "คลิกเพื่อเปลี่ยนไฟล์แนบ";
            if (meeting.doc_file) {
                document.getElementById('current_file_name').innerText = meeting.doc_file;
                document.getElementById('current_file_indicator').style.display = 'block';
            } else {
                document.getElementById('current_file_indicator').style.display = 'none';
            }

            // Set attendees
            currentAttendeesList = meeting.attendees || [];
            renderAttendeeTags();

            const dialog = document.getElementById('meetingFormDialog');
            dialog.showModal();
        }

        // Submit form (create/update - Only if Admin)
        function handleFormSubmit(event) {
            if (!isAdmin) return;
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            fetch('save_meeting.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Redirect back to source page or refresh home page cleanly
                    const urlParams = new URLSearchParams(window.location.search);
                    const redirectUrl = urlParams.get('redirect');
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        window.location.href = 'index.php';
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            });
        }

        // Confirm meeting deletion (Only if Admin)
        function confirmDeleteMeeting(meetingId) {
            if (!isAdmin) return;
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
                        closeMeetingDetailsDialog();
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
