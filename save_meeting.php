<?php
// save_meeting.php - Handle creating and editing meetings (Admin Only with Discord Notification)
require_once 'db.php';
require_once 'notify_discord.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

// Admin Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: สิทธิ์ผู้ดูแลระบบหมดอายุหรือไม่มีสิทธิ์ดำเนินการ']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$meeting_date = isset($_POST['meeting_date']) ? trim($_POST['meeting_date']) : '';
$start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
$end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
$doc_no = isset($_POST['doc_no']) ? trim($_POST['doc_no']) : '';
$office_no = isset($_POST['office_no']) ? trim($_POST['office_no']) : '';
$meeting_link = isset($_POST['meeting_link']) ? trim($_POST['meeting_link']) : '';
$meeting_type = isset($_POST['meeting_type']) ? trim($_POST['meeting_type']) : 'meeting';
$admin_note = isset($_POST['admin_note']) && trim($_POST['admin_note']) !== '' ? trim($_POST['admin_note']) : null;

// Validate required fields
if (empty($title) || empty($meeting_date) || empty($start_time) || empty($end_time)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (หัวข้อประชุม, วันที่, เวลาเริ่มต้น, เวลาสิ้นสุด)']);
    exit();
}

// Parse attendees
$attendees = [];
if (isset($_POST['attendees'])) {
    if (is_array($_POST['attendees'])) {
         $attendees = array_filter(array_map('trim', $_POST['attendees']));
    } else {
         $attendees = array_filter(array_map('trim', explode(',', $_POST['attendees'])));
    }
}

// Handle file upload
$uploaded_filename = null;

if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['doc_file']['tmp_name'];
    $fileName = $_FILES['doc_file']['name'];
    $fileSize = $_FILES['doc_file']['size'];
    $fileType = $_FILES['doc_file']['type'];
    
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    // Whitelist extensions
    $allowedfileExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx'];
    
    if (in_array($fileExtension, $allowedfileExtensions)) {
        // Sanitize file name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        // Ensure directory exists
        $uploadFileDir = 'uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        
        $dest_path = $uploadFileDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $uploaded_filename = $newFileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกไฟล์แนบ']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'อนุญาตให้แนบไฟล์นามสกุล PDF, PNG, JPG, JPEG, Word และ Excel เท่านั้น']);
        exit();
    }
}

try {
    $pdo->beginTransaction();

    $action = 'create';
    $final_file = $uploaded_filename;

    if ($id > 0) {
        $action = 'update';
        // --- UPDATE MEETING ---
        // Fetch current meeting to handle file replacement
        $stmtFetch = $pdo->prepare("SELECT doc_file FROM meetings WHERE id = ?");
        $stmtFetch->execute([$id]);
        $currentMeeting = $stmtFetch->fetch();

        if (!$currentMeeting) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการประชุมที่ต้องการแก้ไข']);
            $pdo->rollBack();
            exit();
        }

        $final_file = $currentMeeting['doc_file'];
        if ($uploaded_filename !== null) {
            // Delete old file
            if (!empty($currentMeeting['doc_file'])) {
                $oldFilePath = 'uploads/' . basename($currentMeeting['doc_file']);
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
            $final_file = $uploaded_filename;
        }

        // Update fields
        $sql = "UPDATE meetings SET 
                title = ?, 
                description = ?, 
                meeting_type = ?, 
                meeting_date = ?, 
                start_time = ?, 
                end_time = ?, 
                doc_no = ?, 
                office_no = ?, 
                meeting_link = ?, 
                doc_file = ?,
                admin_note = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $description, $meeting_type, $meeting_date, $start_time, $end_time,
            $doc_no, $office_no, $meeting_link, $final_file, $admin_note, $id
        ]);

        $meeting_id = $id;

        // Clear existing attendees for this meeting
        $stmtDelAttendees = $pdo->prepare("DELETE FROM meeting_attendees WHERE meeting_id = ?");
        $stmtDelAttendees->execute([$meeting_id]);

    } else {
        // --- INSERT NEW MEETING ---
        $sql = "INSERT INTO meetings (title, description, meeting_type, meeting_date, start_time, end_time, doc_no, office_no, meeting_link, doc_file, admin_note) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $description, $meeting_type, $meeting_date, $start_time, $end_time,
            $doc_no, $office_no, $meeting_link, $uploaded_filename, $admin_note
        ]);

        $meeting_id = $pdo->lastInsertId();
    }

    // Insert Attendees
    if (!empty($attendees)) {
        $sqlAttendee = "INSERT INTO meeting_attendees (meeting_id, name) VALUES (?, ?)";
        $stmtAttendee = $pdo->prepare($sqlAttendee);
        foreach ($attendees as $name) {
            $stmtAttendee->execute([$meeting_id, $name]);
        }
    }

    $pdo->commit();

    // Trigger Discord Notification after transaction commit
    $meetingDetails = [
        'title' => $title,
        'description' => $description,
        'meeting_type' => $meeting_type,
        'meeting_date' => $meeting_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'doc_no' => $doc_no,
        'office_no' => $office_no,
        'meeting_link' => $meeting_link,
        'doc_file' => $final_file
    ];
    notifyDiscord($action, $meetingDetails, $attendees);

    echo json_encode([
        'success' => true,
        'message' => $id > 0 ? 'แก้ไขข้อมูลการประชุมเรียบร้อยแล้ว' : 'บันทึกการประชุมเรียบร้อยแล้ว',
        'meeting_id' => $meeting_id
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
