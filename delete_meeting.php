<?php
// delete_meeting.php - Delete an existing meeting (Admin Only with Discord Notification)
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

// Support JSON input or Form POST
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid meeting ID.']);
    exit();
}

try {
    // Fetch meeting first to check file attachment & retrieve details for Discord notification
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found.']);
        exit();
    }

    // Fetch attendees for Discord notification before deleting
    $stmtAttendees = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ?");
    $stmtAttendees->execute([$id]);
    $attendees = $stmtAttendees->fetchAll(PDO::FETCH_COLUMN);

    // Delete meeting from database (attendees are deleted via ON DELETE CASCADE)
    $stmtDelete = $pdo->prepare("DELETE FROM meetings WHERE id = ?");
    $stmtDelete->execute([$id]);

    // Delete file if exists
    if (!empty($meeting['doc_file'])) {
        $filePath = 'uploads/' . basename($meeting['doc_file']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Trigger Discord Notification
    notifyDiscord('delete', $meeting, $attendees);

    echo json_encode([
        'success' => true,
        'message' => 'ลบข้อมูลการประชุมเรียบร้อยแล้ว'
    ]);

} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
