<?php
// save_scanned_link.php - API endpoint for mobile client to save scanned link to temporary session
header('Content-Type: application/json');

require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token) && isset($_POST['token'])) {
    $token = trim($_POST['token']);
}

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Missing token']);
    exit();
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
if (empty($action) && isset($_POST['action'])) {
    $action = trim($_POST['action']);
}

// Support receiving link via POST param or raw JSON payload
$rawInput = file_get_contents('php://input');
$link = '';

if (!empty($_POST['link'])) {
    $link = trim($_POST['link']);
} else if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        if (!empty($jsonData['action'])) {
            $action = trim($jsonData['action']);
        }
        if (!empty($jsonData['link'])) {
            $link = trim($jsonData['link']);
        }
    }
}

if ($action !== 'reset' && empty($link)) {
    echo json_encode(['success' => false, 'message' => 'Missing scanned link data']);
    exit();
}

try {
    $now = date('Y-m-d H:i:s');
    
    // Validate token
    $stmt = $pdo->prepare("SELECT * FROM temporary_tokens WHERE token = ? AND expires_at > ?");
    $stmt->execute([$token, $now]);
    $tokenRecord = $stmt->fetch();
    
    if (!$tokenRecord) {
        echo json_encode(['success' => false, 'message' => 'Token has expired or is invalid']);
        exit();
    }
    
    // If resetting link for re-scanning
    if ($action === 'reset') {
        $stmtReset = $pdo->prepare("UPDATE temporary_tokens SET scanned_link = NULL WHERE token = ?");
        $stmtReset->execute([$token]);
        
        echo json_encode([
            'success' => true,
            'reset' => true,
            'message' => 'Scanned link reset successfully'
        ]);
        exit();
    }
    
    // Limit link length
    if (mb_strlen($link) > 2000) {
        $link = mb_substr($link, 0, 2000);
    }
    
    // Update temporary_tokens
    $stmtUpdate = $pdo->prepare("UPDATE temporary_tokens SET scanned_link = ? WHERE token = ?");
    $stmtUpdate->execute([$link, $token]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Scanned link saved successfully',
        'link' => $link
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
