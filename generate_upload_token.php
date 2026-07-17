<?php
// generate_upload_token.php - API to generate a temporary secure upload token for mobile
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db.php';

$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;

try {
    // Generate secure random token
    $token = bin2hex(random_bytes(16));
    
    // Expires in 10 minutes
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Insert token record into database
    $stmt = $pdo->prepare("INSERT INTO temporary_tokens (token, meeting_id, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$token, $meeting_id, $expires_at]);
    
    // Build absolute URL for QR Code
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    $dir = dirname($uri);
    $dir = str_replace('\\', '/', $dir);
    if ($dir === '/') {
        $dir = '';
    }
    
    $uploadUrl = "$protocol://$host$dir/mobile_upload.php?token=$token";
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'url' => $uploadUrl
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating token: ' . $e->getMessage()
    ]);
}
