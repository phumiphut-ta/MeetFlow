<?php
// cleanup_attachments.php - API endpoint for administrator to cleanup files and save disk space
header('Content-Type: application/json; charset=utf-8');
session_start();

// Admin Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: สิทธิ์ผู้ดูแลระบบหมดอายุหรือไม่มีสิทธิ์ดำเนินการ']);
    exit();
}

require_once 'db.php';

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($action === 'delete_old') {
    $months = isset($_GET['months']) ? intval($_GET['months']) : 0;
    $allowedMonths = [3, 6, 12, 24];
    
    if (!in_array($months, $allowedMonths)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาระบุช่วงเวลาการตัดข้อมูลที่ถูกต้อง (3, 6, 12 หรือ 24 เดือน)']);
        exit();
    }
    
    try {
        $cutoffDate = date('Y-m-d', strtotime("-$months months"));
        
        // Find meetings older than cutoff date with attachments
        // If end_date is filled, check end_date < cutoffDate, otherwise check meeting_date < cutoffDate
        $stmt = $pdo->prepare("
            SELECT id, doc_file FROM meetings 
            WHERE (
                (end_date IS NOT NULL AND end_date < ?) OR 
                (end_date IS NULL AND meeting_date < ?)
            ) 
            AND doc_file IS NOT NULL 
            AND doc_file != ''
        ");
        $stmt->execute([$cutoffDate, $cutoffDate]);
        $meetingsToClean = $stmt->fetchAll();
        
        $deletedCount = 0;
        foreach ($meetingsToClean as $meeting) {
            $filename = basename($meeting['doc_file']);
            $filePath = __DIR__ . '/uploads/' . $filename;
            
            // Delete physical file
            if (file_exists($filePath) && is_file($filePath)) {
                if (unlink($filePath)) {
                    $deletedCount++;
                }
            } else {
                // If file doesn't exist on disk, we still count it as cleaned up in the database
                $deletedCount++;
            }
            
            // Update database record to clear the file reference
            $stmtUpdate = $pdo->prepare("UPDATE meetings SET doc_file = NULL WHERE id = ?");
            $stmtUpdate->execute([$meeting['id']]);
        }
        
        echo json_encode([
            'success' => true,
            'count' => $deletedCount,
            'message' => "ลบไฟล์แนบเก่าที่มีอายุเกินกว่า $months เดือนสำเร็จ เรียบร้อยแล้วจำนวน $deletedCount ไฟล์"
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้าถึงฐานข้อมูล: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'delete_orphaned') {
    try {
        // 1. Fetch all referenced files in meetings table
        $stmtMeetings = $pdo->query("SELECT doc_file FROM meetings WHERE doc_file IS NOT NULL AND doc_file != ''");
        $referencedFiles = $stmtMeetings->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Fetch all referenced files in temporary_tokens table
        $stmtTokens = $pdo->query("SELECT uploaded_file FROM temporary_tokens WHERE uploaded_file IS NOT NULL AND uploaded_file != ''");
        $tokenFiles = $stmtTokens->fetchAll(PDO::FETCH_COLUMN);
        
        // Combine all referenced files into a single array
        $allReferenced = array_merge($referencedFiles, $tokenFiles);
        // Normalize names to prevent any paths issues
        $allReferenced = array_map('basename', $allReferenced);
        
        // 3. Scan uploads directory
        $uploadDir = __DIR__ . '/uploads/';
        $deletedCount = 0;
        
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            $ignoredFiles = ['.', '..', '.htaccess', 'web.config', 'index.html', 'index.php'];
            
            foreach ($files as $file) {
                if (in_array($file, $ignoredFiles) || is_dir($uploadDir . $file)) {
                    continue;
                }
                
                // If file is not in database referenced list, delete it!
                if (!in_array($file, $allReferenced)) {
                    $filePath = $uploadDir . $file;
                    if (file_exists($filePath) && is_file($filePath)) {
                        if (unlink($filePath)) {
                            $deletedCount++;
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'count' => $deletedCount,
            'message' => "ล้างไฟล์ขยะตกค้างที่ไม่มีข้อมูลเชื่อมโยงสำเร็จ เรียบร้อยแล้วจำนวน $deletedCount ไฟล์"
        ]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการประมวลผลระบบ: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'การดำเนินการไม่ถูกต้อง']);
}
