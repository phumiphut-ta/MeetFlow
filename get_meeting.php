<?php
// get_meeting.php - Fetch a single meeting details as JSON
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Meeting ID is required.']);
    exit();
}

$id = intval($_GET['id']);

try {
    // Fetch meeting details
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found.']);
        exit();
    }

    // Fetch meeting attendees
    $stmtAttendees = $pdo->prepare("SELECT name FROM meeting_attendees WHERE meeting_id = ? ORDER BY id ASC");
    $stmtAttendees->execute([$id]);
    $attendees = $stmtAttendees->fetchAll(PDO::FETCH_COLUMN);

    $meeting['attendees'] = $attendees;

    echo json_encode([
        'success' => true,
        'meeting' => $meeting
    ]);

} catch (\PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
