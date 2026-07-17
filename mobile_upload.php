<?php
// mobile_upload.php - Mobile-friendly interface for scanning QR code and uploading files
require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$isValid = false;
$meetingTitle = 'สร้างการประชุม / อบรมใหม่';
$currentFile = null;

if (!empty($token)) {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT * FROM temporary_tokens WHERE token = ? AND expires_at > ?");
        $stmt->execute([$token, $now]);
        $tokenRecord = $stmt->fetch();
        
        if ($tokenRecord) {
            $isValid = true;
            $meetingId = intval($tokenRecord['meeting_id']);
            
            if ($meetingId > 0) {
                // Fetch meeting title
                $stmtMeeting = $pdo->prepare("SELECT title, doc_file FROM meetings WHERE id = ?");
                $stmtMeeting->execute([$meetingId]);
                $meeting = $stmtMeeting->fetch();
                if ($meeting) {
                    $meetingTitle = $meeting['title'];
                    $currentFile = $meeting['doc_file'];
                }
            }
        }
    } catch (\Exception $e) {
        $dbError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปโหลดเอกสารผ่านมือถือ - MeetFlow</title>
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --font-primary: 'Outfit', 'Sarabun', sans-serif;
            --bg-app: linear-gradient(135deg, #090d16 0%, #0f172a 50%, #1e1b4b 100%);
            --bg-card: rgba(30, 41, 59, 0.45);
            --border-glass: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --success-gradient: linear-gradient(135deg, #34d399 0%, #059669 100%);
            --danger-gradient: linear-gradient(135deg, #fb7185 0%, #e11d48 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-primary);
            background: var(--bg-app);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .container {
            width: 100%;
            max-width: 450px;
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 24px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .logo-section {
            margin-bottom: 24px;
        }

        .logo-icon {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #38bdf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        h2 {
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 6px;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .meeting-box {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 16px;
            text-align: left;
            margin-bottom: 24px;
        }

        .meeting-box h4 {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .meeting-box p {
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .upload-zone {
            border: 2px dashed rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 32px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(15, 23, 42, 0.2);
            margin-bottom: 24px;
        }

        .upload-zone:active, .upload-zone.dragover {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.05);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 2.2rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }

        .upload-zone:active .upload-icon {
            transform: scale(0.9);
        }

        .upload-text {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .upload-hint {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .selected-file-box {
            display: none;
            background: rgba(99, 102, 241, 0.08);
            border: 1px dashed rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.85rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            text-align: left;
        }

        .selected-file-name {
            font-weight: 500;
            word-break: break-all;
            margin-right: 8px;
            color: #818cf8;
        }

        .btn-upload {
            width: 100%;
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            color: white;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: all 0.3s ease;
        }

        .btn-upload:active {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
        }

        .btn-upload:disabled {
            background: var(--text-muted);
            box-shadow: none;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Error view */
        .error-card {
            border-top: 4px solid #e11d48;
        }

        .error-icon {
            font-size: 3rem;
            color: #f43f5e;
            margin-bottom: 16px;
        }

        .error-msg {
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        /* Success view */
        .success-view {
            display: none;
        }

        .success-icon {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 16px;
            animation: scaleUp 0.4s ease-out;
        }

        @keyframes scaleUp {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .progress-container {
            display: none;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 100px;
            height: 6px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .progress-bar {
            background: var(--primary-gradient);
            height: 100%;
            width: 0%;
            transition: width 0.1s linear;
        }
    </style>
</head>
<body>

<div class="container <?= !$isValid ? 'error-card' : '' ?>">
    <div class="logo-section">
        <i class="fa-solid fa-cloud-arrow-up logo-icon"></i>
        <h2>MeetFlow Mobile Upload</h2>
        <div class="subtitle">ระบบอัปโหลดไฟล์/ถ่ายรูปผ่านโทรศัพท์มือถือ</div>
    </div>

    <?php if (!$isValid): ?>
        <!-- ERROR VIEW -->
        <i class="fa-regular fa-circle-xmark error-icon"></i>
        <h2>ลิงก์เข้าสู่ระบบไม่ถูกต้อง</h2>
        <p class="error-msg">
            โทเค็นการอัปโหลดนี้ไม่มีอยู่หรือหมดอายุแล้ว (โทเค็นมีอายุการใช้งาน 10 นาทีเพื่อความปลอดภัย)<br>
            กรุณาคลิก "สแกน QR Code" บนหน้าจอคอมพิวเตอร์เพื่อลงทะเบียนเซสชันใหม่
        </p>
        <button class="btn-upload" style="background: var(--danger-gradient); box-shadow: none;" onclick="window.close()"><i class="fa-solid fa-xmark"></i> ปิดหน้านี้</button>
    <?php else: ?>
        <!-- UPLOAD VIEW -->
        <div class="upload-form-container">
            <div class="meeting-box">
                <h4>การประชุม / อบรมที่ระบุ</h4>
                <p><?= htmlspecialchars($meetingTitle) ?></p>
                <?php if ($currentFile): ?>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 6px;">
                        <i class="fa-regular fa-file"></i> เอกสารแนบปัจจุบัน: <?= htmlspecialchars($currentFile) ?>
                    </p>
                <?php endif; ?>
            </div>

            <form id="mobileUploadForm" enctype="multipart/form-data">
                <div class="upload-zone" id="uploadZone">
                    <i class="fa-solid fa-camera upload-icon" id="iconCamera"></i>
                    <i class="fa-regular fa-file-image upload-icon" id="iconFile" style="display: none;"></i>
                    <p class="upload-text">ถ่ายภาพ หรือ เลือกเอกสารแนบ</p>
                    <p class="upload-hint">รองรับไฟล์ภาพ หรือ PDF (สูงสุด 10MB)</p>
                    <input type="file" id="doc_file" name="doc_file" accept="image/*,application/pdf" capture="environment" onchange="handleFileSelected(this)">
                </div>

                <div class="selected-file-box" id="fileDetails">
                    <span class="selected-file-name" id="fileNameText">filename.jpg</span>
                    <i class="fa-solid fa-xmark" style="color: var(--text-muted); cursor: pointer;" onclick="clearSelectedFile()"></i>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar" id="progressBar"></div>
                </div>

                <button type="submit" class="btn-upload" id="btnSubmit" disabled>
                    <i class="fa-solid fa-paper-plane"></i> เริ่มอัปโหลดเอกสาร
                </button>
            </form>
        </div>

        <!-- SUCCESS VIEW -->
        <div class="success-view" id="successView">
            <i class="fa-solid fa-circle-check success-icon"></i>
            <h2>อัปโหลดสำเร็จ!</h2>
            <p class="error-msg" style="color: var(--text-primary);">
                ไฟล์เอกสารได้รับการบันทึกเรียบร้อยแล้ว<br>
                หน้าจอคอมพิวเตอร์ของคุณจะปิดรหัส QR Code และอัปเดตไฟล์ให้คุณทันที
            </p>
            <button class="btn-upload" style="background: var(--success-gradient); box-shadow: none;" onclick="resetFormForNewUpload()"><i class="fa-solid fa-rotate-left"></i> อัปโหลดรูปเพิ่ม</button>
        </div>
    <?php endif; ?>
</div>

<script>
    let selectedFile = null;

    function handleFileSelected(input) {
        if (input.files && input.files[0]) {
            selectedFile = input.files[0];
            
            // Limit file size to 10MB
            if (selectedFile.size > 10 * 1024 * 1024) {
                alert('ขนาดไฟล์ต้องไม่เกิน 10MB');
                clearSelectedFile();
                return;
            }

            document.getElementById('fileNameText').innerText = selectedFile.name;
            document.getElementById('fileDetails').style.display = 'flex';
            document.getElementById('uploadZone').style.borderColor = '#10b981';
            document.getElementById('btnSubmit').disabled = false;

            // Change icon to show file selected
            document.getElementById('iconCamera').style.display = 'none';
            document.getElementById('iconFile').style.display = 'inline-block';
        }
    }

    function clearSelectedFile() {
        selectedFile = null;
        document.getElementById('doc_file').value = '';
        document.getElementById('fileDetails').style.display = 'none';
        document.getElementById('uploadZone').style.borderColor = 'rgba(255, 255, 255, 0.15)';
        document.getElementById('btnSubmit').disabled = true;

        document.getElementById('iconCamera').style.display = 'inline-block';
        document.getElementById('iconFile').style.display = 'none';
    }

    function resetFormForNewUpload() {
        clearSelectedFile();
        document.getElementById('successView').style.display = 'none';
        document.querySelector('.upload-form-container').style.display = 'block';
    }

    <?php if ($isValid): ?>
    document.getElementById('mobileUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!selectedFile) return;

        const formData = new FormData();
        formData.append('doc_file', selectedFile);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'save_mobile_upload.php?token=<?= urlencode($token) ?>', true);

        // UI Updates
        document.getElementById('btnSubmit').disabled = true;
        document.getElementById('progressContainer').style.display = 'block';

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                document.getElementById('progressBar').style.width = percent + '%';
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // Show success
                        document.getElementById('progressContainer').style.display = 'none';
                        document.getElementById('progressBar').style.width = '0%';
                        document.querySelector('.upload-form-container').style.display = 'none';
                        document.getElementById('successView').style.display = 'block';
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + res.message);
                        document.getElementById('btnSubmit').disabled = false;
                        document.getElementById('progressContainer').style.display = 'none';
                    }
                } catch(err) {
                    alert('เกิดข้อผิดพลาดในการประมวลผลข้อมูล');
                    document.getElementById('btnSubmit').disabled = false;
                    document.getElementById('progressContainer').style.display = 'none';
                }
            } else {
                alert('การเชื่อมต่อเซิร์ฟเวอร์ขัดข้อง');
                document.getElementById('btnSubmit').disabled = false;
                document.getElementById('progressContainer').style.display = 'none';
            }
        };

        xhr.send(formData);
    });
    <?php endif; ?>
</script>
</body>
</html>
