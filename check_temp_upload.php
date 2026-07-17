<?php
// check_temp_upload.php - API for PC browser to poll the mobile upload status
header('Content-Type: application/json');

require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Missing token']);
    exit();
}

try {
    $now = date('Y-m-d H:i:s');
    
    // Check if token exists and is not expired
    $stmt = $pdo->prepare("SELECT * FROM temporary_tokens WHERE token = ? AND expires_at > ?");
    $stmt->execute([$token, $now]);
    $tokenRecord = $stmt->fetch();
    
    if (!$tokenRecord) {
        echo json_encode(['success' => false, 'message' => 'Token has expired or is invalid']);
        exit();
    }
    
    if (!empty($tokenRecord['uploaded_file'])) {
        echo json_encode([
            'success' => true,
            'uploaded' => true,
            'filename' => $tokenRecord['uploaded_file']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'uploaded' => false
        ]);
    }
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
