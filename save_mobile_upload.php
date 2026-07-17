<?php
// save_mobile_upload.php - API endpoint for mobile client to upload files and update temporary session
header('Content-Type: application/json');

require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Missing token']);
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
    
    // Process file upload
    if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred']);
        exit();
    }
    
    $file = $_FILES['doc_file'];
    $originalName = $file['name'];
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    
    // File size validation (10MB limit)
    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
        exit();
    }
    
    // File extension validation
    $fileParts = explode('.', $originalName);
    $extension = strtolower(end($fileParts));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($extension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF and image files (JPG, PNG) are allowed']);
        exit();
    }
    
    // Ensure uploads directory exists
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique name
    $newFilename = 'mobile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destPath = $uploadDir . $newFilename;
    
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        // Update database token record
        $stmtUpdate = $pdo->prepare("UPDATE temporary_tokens SET uploaded_file = ? WHERE token = ?");
        $stmtUpdate->execute([$newFilename, $token]);
        
        echo json_encode([
            'success' => true,
            'filename' => $newFilename
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    }
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
