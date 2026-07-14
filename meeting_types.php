<?php
// meeting_types.php - Meeting Types Management Page (Admin only)
require_once 'db.php';

session_start();

// Authentication Check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

$successMsg = '';
$errorMsg = '';

// Edit Mode detection
$editMode = false;
$editType = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    try {
        $stmtEdit = $pdo->prepare("SELECT * FROM meeting_types WHERE id = ?");
        $stmtEdit->execute([$editId]);
        $editType = $stmtEdit->fetch();
        if ($editType) {
            $editMode = true;
        }
    } catch (\Exception $e) {
        $errorMsg = 'เกิดข้อผิดพลาดในการโหลดข้อมูลแก้ไข: ' . $e->getMessage();
    }
}

// Handle POST: Create or Update Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $typeName = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '#3b82f6';
    
    if (empty($typeName)) {
        $errorMsg = 'กรุณาระบุชื่อประเภทการนัดหมาย';
    } else {
        if ($action === 'create_type') {
            $typeKey = isset($_POST['type_key']) ? strtolower(trim($_POST['type_key'])) : '';
            
            // Validate key
            if (empty($typeKey) || !preg_match('/^[a-z0-9_-]+$/', $typeKey)) {
                $errorMsg = 'รหัสประเภทต้องเป็นภาษาอังกฤษตัวเล็ก ตัวเลข เครื่องหมายขีดกลาง หรือขีดล่างเท่านั้น';
            } else {
                try {
                    // Check duplicate key
                    $stmtCheck = $pdo->prepare("SELECT id FROM meeting_types WHERE type_key = ?");
                    $stmtCheck->execute([$typeKey]);
                    if ($stmtCheck->fetch()) {
                        $errorMsg = "รหัสประเภท '$typeKey' มีในระบบแล้ว กรุณาใช้รหัสอื่น";
                    } else {
                        $stmtInsert = $pdo->prepare("INSERT INTO meeting_types (type_key, type_name, color) VALUES (?, ?, ?)");
                        $stmtInsert->execute([$typeKey, $typeName, $color]);
                        $successMsg = "เพิ่มประเภทการนัดหมาย '$typeName' เรียบร้อยแล้ว";
                    }
                } catch (\PDOException $e) {
                    $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'update_type' && $editMode) {
            try {
                $stmtUpdate = $pdo->prepare("UPDATE meeting_types SET type_name = ?, color = ? WHERE id = ?");
                $stmtUpdate->execute([$typeName, $color, $editType['id']]);
                $successMsg = "ปรับปรุงประเภทการนัดหมายเรียบร้อยแล้ว";
                // Redirect to exit edit mode and refresh list cleanly
                header("Location: meeting_types.php?success=" . urlencode($successMsg));
                exit();
            } catch (\PDOException $e) {
                $errorMsg = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $e->getMessage();
            }
        }
    }
}

// Handle GET: Delete Type
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    try {
        $stmtFetch = $pdo->prepare("SELECT * FROM meeting_types WHERE id = ?");
        $stmtFetch->execute([$deleteId]);
        $typeToDelete = $stmtFetch->fetch();
        
        if (!$typeToDelete) {
            $errorMsg = 'ไม่พบประเภทการนัดหมายที่ต้องการลบ';
        } elseif ($typeToDelete['type_key'] === 'meeting' || $typeToDelete['type_key'] === 'training') {
            $errorMsg = 'ไม่สามารถลบประเภทการนัดหมายหลักของระบบได้';
        } else {
            // Check if there are meetings using this type
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM meetings WHERE meeting_type = ?");
            $stmtCheck->execute([$typeToDelete['type_key']]);
            $count = $stmtCheck->fetchColumn();
            
            if ($count > 0) {
                $errorMsg = "ไม่สามารถลบได้ เนื่องจากมีนัดหมายจำนวน $count รายการ กำลังใช้งานประเภทนี้อยู่";
            } else {
                $stmtDelete = $pdo->prepare("DELETE FROM meeting_types WHERE id = ?");
                $stmtDelete->execute([$deleteId]);
                $successMsg = "ลบประเภทการนัดหมาย '" . $typeToDelete['type_name'] . "' สำเร็จ";
            }
        }
    } catch (\PDOException $e) {
        $errorMsg = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage();
    }
}

// Pull clean success messages from redirects
if (isset($_GET['success'])) {
    $successMsg = $_GET['success'];
}

// Fetch all meeting types
$types = [];
try {
    $stmtTypes = $pdo->query("SELECT * FROM meeting_types ORDER BY id ASC");
    $types = $stmtTypes->fetchAll();
} catch (\PDOException $e) {
    $errorMsg = 'เกิดข้อผิดพลาดในการโหลดข้อมูลประเภทการนัดหมาย: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการประเภทการนัดหมาย - MeetFlow Admin</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .types-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (min-width: 900px) {
            .types-grid {
                grid-template-columns: 1.2fr 1fr;
            }
        }
        .types-card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-md);
        }
        .types-card h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-glass);
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-danger {
            background: rgba(244, 63, 94, 0.15);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fb7185;
        }
        .types-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .types-table th, .types-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-align: left;
        }
        .types-table th {
            font-weight: 700;
            color: var(--text-secondary);
            background: rgba(15, 23, 42, 0.4);
        }
        .color-preview {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .color-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-block;
        }
        .color-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="logo-section">
                <h1><i class="fa-solid fa-tags"></i> จัดการประเภทการนัดหมาย</h1>
                <p>เพิ่ม แก้ไข และดูรหัสสีของประเภทการนัดหมาย เช่น ประชุม, อบรม, สัมมนา</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าปฏิทิน</a>
            </div>
        </header>

        <!-- Alerts -->
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Layout -->
        <main class="types-grid">
            <!-- Left Card: List -->
            <div class="types-card">
                <h2><i class="fa-solid fa-list"></i> รายชื่อประเภทการนัดหมายทั้งหมด</h2>
                <div style="overflow-x: auto;">
                    <table class="types-table">
                        <thead>
                            <tr>
                                <th>รหัสประเภท</th>
                                <th>ชื่อประเภท</th>
                                <th>การแสดงผลสี</th>
                                <th style="width: 140px; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $t): 
                                $isSystem = ($t['type_key'] === 'meeting' || $t['type_key'] === 'training');
                            ?>
                                <tr>
                                    <td>
                                        <code><?= htmlspecialchars($t['type_key']) ?></code>
                                        <?php if ($isSystem): ?>
                                            <span style="font-size: 0.7rem; background: rgba(255,255,255,0.1); color: var(--text-secondary); padding: 1px 4px; border-radius: 4px; margin-left: 4px;">ระบบ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($t['type_name']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="color-preview">
                                            <span class="color-dot" style="background: <?= htmlspecialchars($t['color']) ?>;"></span>
                                            <span class="color-badge" style="background: <?= hexToRgba($t['color'], 0.15) ?>; color: <?= htmlspecialchars($t['color']) ?>; border: 1px solid <?= hexToRgba($t['color'], 0.3) ?>;"><?= htmlspecialchars($t['color']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <a href="?edit_id=<?= $t['id'] ?>" class="btn" style="padding: 6px 10px; font-size: 0.8rem; background: rgba(96, 165, 250, 0.15); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); border-radius: 6px;" title="แก้ไข"><i class="fa-solid fa-edit"></i></a>
                                            <?php if ($isSystem): ?>
                                                <span style="color: var(--text-muted); font-size: 0.8rem; opacity: 0.4; padding: 6px 10px;" title="ไม่สามารถลบประเภทระบบได้"><i class="fa-solid fa-ban"></i></span>
                                            <?php else: ?>
                                                <a href="?delete_id=<?= $t['id'] ?>" class="btn btn-danger" style="padding: 6px 10px; font-size: 0.8rem; box-shadow: none;" title="ลบ" onclick="return confirm('คุณแน่ใจว่าต้องการลบประเภทการนัดหมาย <?= htmlspecialchars($t['type_name']) ?> หรือไม่?')"><i class="fa-solid fa-trash"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Card: Form -->
            <div class="types-card">
                <h2>
                    <?php if ($editMode): ?>
                        <i class="fa-solid fa-pen-to-square"></i> แก้ไขประเภทการนัดหมาย
                    <?php else: ?>
                        <i class="fa-solid fa-folder-plus"></i> เพิ่มประเภทการนัดหมายใหม่
                    <?php endif; ?>
                </h2>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="<?= $editMode ? 'update_type' : 'create_type' ?>">

                    <div class="form-group">
                        <label for="type_key">รหัสประเภท (Type Key) <span style="color: var(--danger)">*</span></label>
                        <input type="text" id="type_key" name="type_key" required 
                               placeholder="เช่น seminar, event (ภาษาอังกฤษตัวเล็กเท่านั้น)" 
                               pattern="[a-z0-9_-]+"
                               title="อังกฤษตัวเล็ก ตัวเลข เครื่องหมายขีดกลาง หรือขีดล่างเท่านั้น"
                               value="<?= $editMode ? htmlspecialchars($editType['type_key']) : '' ?>"
                               <?= $editMode ? 'readonly style="opacity: 0.6; cursor: not-allowed;"' : '' ?>>
                        <?php if ($editMode): ?>
                            <span style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; display: block;"><i class="fa-solid fa-circle-info"></i> ไม่สามารถแก้ไขรหัสประเภทได้เนื่องจากใช้อ้างอิงในฐานข้อมูล</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="type_name">ชื่อประเภทการนัดหมาย (Display Name) <span style="color: var(--danger)">*</span></label>
                        <input type="text" id="type_name" name="type_name" required 
                               placeholder="เช่น โครงการอบรม, สัมมนาภายนอก"
                               value="<?= $editMode ? htmlspecialchars($editType['type_name']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="color">สัญลักษณ์สีประจำประเภท <span style="color: var(--danger)">*</span></label>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <input type="color" id="color" name="color" required style="width: 60px; height: 40px; padding: 0; border: 1px solid var(--border-glass); border-radius: 8px; cursor: pointer; background: none;"
                                   value="<?= $editMode ? htmlspecialchars($editType['color']) : '#3b82f6' ?>">
                            <span style="font-size: 0.9rem; color: var(--text-secondary);">เลือกสีสำหรับการ์ดและ Badge แสดงผล</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <?php if ($editMode): ?>
                            <a href="meeting_types.php" class="btn btn-secondary" style="flex: 1; justify-content: center;"><i class="fa-solid fa-xmark"></i> ยกเลิก</a>
                            <button type="submit" class="btn btn-primary" style="flex: 1.5; justify-content: center;"><i class="fa-solid fa-check"></i> อัปเดตข้อมูล</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-plus"></i> เพิ่มประเภทใหม่</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
