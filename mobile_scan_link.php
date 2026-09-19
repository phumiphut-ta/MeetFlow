<?php
// mobile_scan_link.php - Mobile web camera interface to scan QR codes on paper/materials and auto-sync link to desktop
require_once 'db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$isValid = false;
$meetingTitle = 'สร้างการประชุม / อบรมใหม่';

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
                $stmtMeeting = $pdo->prepare("SELECT title FROM meetings WHERE id = ?");
                $stmtMeeting->execute([$meetingId]);
                $meeting = $stmtMeeting->fetch();
                if ($meeting) {
                    $meetingTitle = $meeting['title'];
                }
            }
        }
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>สแกน QR Code ลิงก์ผ่านมือถือ - MeetFlow</title>
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Local fallback QR decoder -->
    <script src="assets/jsqr.min.js"></script>
    
    <style>
        :root {
            --font-primary: 'Outfit', 'Sarabun', sans-serif;
            --bg-app: linear-gradient(135deg, #090d16 0%, #0f172a 50%, #1e1b4b 100%);
            --bg-card: rgba(30, 41, 59, 0.55);
            --border-glass: rgba(255, 255, 255, 0.1);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            border-radius: 20px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            text-align: center;
        }

        .logo-section {
            margin-bottom: 20px;
        }

        .logo-icon {
            font-size: 2.2rem;
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        h2 {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            font-size: 0.82rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .meeting-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
            margin-bottom: 20px;
        }

        .meeting-box h4 {
            font-size: 0.72rem;
            color: #818cf8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meeting-box p {
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.35;
            color: #f1f5f9;
        }

        /* Camera Scanner Viewport */
        .scanner-container {
            position: relative;
            width: 100%;
            height: 280px;
            background: #020617;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-glass);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #scanner_video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Viewfinder Overlay */
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scan-box {
            position: relative;
            width: 200px;
            height: 200px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 0 0 9999px rgba(2, 6, 23, 0.5);
        }

        /* Reticle Corners */
        .corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #38bdf8;
            border-style: solid;
        }
        .corner-tl { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-top-left-radius: 12px; }
        .corner-tr { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-top-right-radius: 12px; }
        .corner-bl { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-bottom-left-radius: 12px; }
        .corner-br { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-bottom-right-radius: 12px; }

        /* Laser Animation */
        .scan-laser {
            position: absolute;
            left: 5%;
            width: 90%;
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, #38bdf8 50%, transparent 100%);
            box-shadow: 0 0 10px #38bdf8, 0 0 20px #818cf8;
            top: 0;
            animation: scanLaser 2s infinite ease-in-out alternate;
        }

        @keyframes scanLaser {
            0% { top: 5%; opacity: 0.8; }
            100% { top: 95%; opacity: 0.8; }
        }

        .scanner-prompt {
            position: absolute;
            bottom: 12px;
            left: 0;
            right: 0;
            font-size: 0.8rem;
            color: #f8fafc;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9);
            background: rgba(15, 23, 42, 0.65);
            padding: 4px 12px;
            border-radius: 20px;
            margin: 0 auto;
            width: fit-content;
            backdrop-filter: blur(8px);
        }

        /* Controls */
        .scanner-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 18px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.6);
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 8px 20px -6px rgba(16, 185, 129, 0.6);
        }

        /* Result View */
        #result_container {
            display: none;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 16px;
            text-align: left;
            animation: fadeIn 0.3s ease;
        }

        .result-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .result-link-text {
            font-family: monospace;
            font-size: 0.85rem;
            color: #38bdf8;
            word-break: break-all;
            background: rgba(2, 6, 23, 0.6);
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 12px;
            max-height: 100px;
            overflow-y: auto;
        }

        .sync-status {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Error Banner */
        .error-banner {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.2);
            color: #fda4af;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.88rem;
            margin-bottom: 16px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #file_input_fallback {
            display: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="logo-section">
        <div class="logo-icon"><i class="fa-solid fa-camera"></i></div>
        <h2>สแกนลิงก์จากกล้องมือถือ</h2>
        <p class="subtitle">สแกน QR Code จากเอกสารเพื่อส่งลิงก์ไปยังฟอร์ม MeetFlow</p>
    </div>

    <?php if (!$isValid): ?>
        <div class="error-banner">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.4rem;"></i>
            <div>
                <strong>ลิงก์สแกนไม่ถูกต้องหรือหมดอายุ</strong>
                <div style="font-size: 0.8rem; color: #fecdd3; margin-top: 2px;">
                    เซสชันหมดอายุ (10 นาที) หรือข้อมูลไม่ถูกต้อง กรุณากดสแกน QR Code ใหม่จากหน้าจอคอมพิวเตอร์
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="meeting-box">
            <h4><i class="fa-solid fa-calendar-check"></i> ข้อมูลการประชุม</h4>
            <p><?= htmlspecialchars($meetingTitle) ?></p>
        </div>

        <div id="camera_error_banner" class="error-banner" style="display: none;"></div>

        <!-- Live Scanner Viewport -->
        <div class="scanner-container" id="scanner_viewport">
            <video id="scanner_video" playsinline muted autoplay></video>
            
            <div class="scanner-overlay">
                <div class="scan-box">
                    <div class="corner corner-tl"></div>
                    <div class="corner corner-tr"></div>
                    <div class="corner corner-bl"></div>
                    <div class="corner corner-br"></div>
                    <div class="scan-laser"></div>
                </div>
            </div>

            <div class="scanner-prompt"><i class="fa-solid fa-qrcode"></i> หันกล้องไปที่ QR Code บนเอกสาร</div>
        </div>

        <!-- Result / Sync Card -->
        <div id="result_container">
            <div class="result-badge" id="sync_badge">
                <i class="fa-solid fa-circle-check"></i> ตรวจพบ QR Code
            </div>
            
            <div class="result-link-text" id="result_link_display"></div>

            <div class="sync-status" id="sync_status_text">
                <i class="fa-solid fa-spinner fa-spin"></i> กำลังส่งลิงก์ไปยังคอมพิวเตอร์...
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary" id="btn_copy" onclick="copyScannedLink()">
                    <i class="fa-regular fa-copy"></i> คัดลอกลิงก์
                </button>
                <button type="button" class="btn btn-primary" onclick="restartScanner()">
                    <i class="fa-solid fa-rotate-right"></i> สแกนใหม่
                </button>
            </div>
        </div>

        <!-- Actions & Fallback -->
        <div class="scanner-actions" id="action_buttons">
            <button type="button" class="btn btn-secondary" onclick="switchCamera()">
                <i class="fa-solid fa-camera-rotate"></i> สลับกล้อง
            </button>
            
            <label for="file_input_fallback" class="btn btn-secondary" style="margin: 0;">
                <i class="fa-solid fa-image"></i> เลือกรูปภาพ
            </label>
            <input type="file" id="file_input_fallback" accept="image/*" onchange="handleImageFile(this)">
        </div>

        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 10px;">
            <i class="fa-solid fa-shield-halved"></i> เชื่อมต่อแบบเข้ารหัส Token ปลอดภัยชั่วคราว
        </div>
    <?php endif; ?>
</div>

<canvas id="scanner_canvas" style="display: none;"></canvas>

<script>
    const token = <?= json_encode($token) ?>;
    const isValid = <?= json_encode($isValid) ?>;

    let video = document.getElementById('scanner_video');
    let canvas = document.getElementById('scanner_canvas');
    let ctx = canvas ? canvas.getContext('2d', { willReadFrequently: true }) : null;
    
    let currentStream = null;
    let scanning = false;
    let facingMode = 'environment';
    let barcodeDetector = null;
    let scannedLink = '';

    // Initialize native BarcodeDetector if available
    if ('BarcodeDetector' in window) {
        try {
            barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch (e) {
            console.warn('BarcodeDetector format qr_code not supported, falling back to jsQR:', e);
            barcodeDetector = null;
        }
    }

    if (isValid) {
        startCamera();
    }

    async function startCamera() {
        stopCamera();
        const errorBanner = document.getElementById('camera_error_banner');
        if (errorBanner) errorBanner.style.display = 'none';

        try {
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };

            currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = currentStream;
            video.setAttribute('playsinline', true);
            await video.play();

            scanning = true;
            document.getElementById('scanner_viewport').style.display = 'flex';
            requestAnimationFrame(tickScan);
        } catch (err) {
            console.error('Camera access error:', err);
            if (errorBanner) {
                errorBanner.style.display = 'flex';
                errorBanner.innerHTML = `
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.3rem;"></i>
                    <div>
                        <strong>ไม่สามารถเปิดกล้องได้</strong>
                        <div style="font-size: 0.8rem; margin-top: 2px;">
                            กรุณาอนุญาตการเข้าถึงกล้องบนเบราว์เซอร์ หรือกดปุ่ม "เลือกรูปภาพ" เพื่ออัปโหลดรูป QR Code แทน
                        </div>
                    </div>
                `;
            }
        }
    }

    function stopCamera() {
        scanning = false;
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
    }

    async function switchCamera() {
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        await startCamera();
    }

    async function tickScan() {
        if (!scanning || !video || video.readyState !== video.HAVE_ENOUGH_DATA) {
            if (scanning) requestAnimationFrame(tickScan);
            return;
        }

        let detected = false;
        let qrData = '';

        // 1. Try native BarcodeDetector first (fastest)
        if (barcodeDetector) {
            try {
                const barcodes = await barcodeDetector.detect(video);
                if (barcodes && barcodes.length > 0) {
                    qrData = barcodes[0].rawValue;
                    detected = true;
                }
            } catch (err) {
                // fall through to jsQR
            }
        }

        // 2. Fallback to jsQR
        if (!detected && typeof jsQR === 'function') {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });

            if (code && code.data) {
                qrData = code.data;
                detected = true;
            }
        }

        if (detected && qrData.trim() !== '') {
            onQrCodeDetected(qrData.trim());
        } else {
            requestAnimationFrame(tickScan);
        }
    }

    function onQrCodeDetected(rawUrl) {
        scanning = false;
        scannedLink = rawUrl;

        // Vibrate if supported
        if (navigator.vibrate) {
            try { navigator.vibrate([100, 50, 100]); } catch(e){}
        }

        // Show result box
        document.getElementById('result_container').style.display = 'block';
        document.getElementById('result_link_display').innerText = rawUrl;
        
        const syncBadge = document.getElementById('sync_badge');
        const syncText = document.getElementById('sync_status_text');
        
        syncBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังส่งข้อมูล...';
        syncBadge.style.background = 'rgba(56, 189, 248, 0.15)';
        syncBadge.style.color = '#38bdf8';
        syncBadge.style.borderColor = 'rgba(56, 189, 248, 0.3)';

        syncText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังซิงค์ไปยังหน้าต่างคอมพิวเตอร์...';

        // Send to backend via AJAX
        fetch(`save_scanned_link.php?token=${encodeURIComponent(token)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ link: rawUrl })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                syncBadge.innerHTML = '<i class="fa-solid fa-circle-check"></i> ซิงค์สำเร็จแล้ว!';
                syncBadge.style.background = 'rgba(16, 185, 129, 0.15)';
                syncBadge.style.color = '#34d399';
                syncBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';

                syncText.innerHTML = '<i class="fa-solid fa-check" style="color: #34d399;"></i> ลิงก์ถูกกรอกลงในช่องคอมพิวเตอร์ของคุณแล้ว';
            } else {
                syncBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> บันทึกล้มเหลว';
                syncBadge.style.background = 'rgba(244, 63, 94, 0.15)';
                syncBadge.style.color = '#fda4af';
                syncText.innerText = 'เกิดข้อผิดพลาด: ' + (data.message || 'ไม่สามารถส่งข้อมูลได้');
            }
        })
        .catch(err => {
            console.error('Save scanned link error:', err);
            syncBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> การเชื่อมต่อล้มเหลว';
            syncBadge.style.background = 'rgba(244, 63, 94, 0.15)';
            syncBadge.style.color = '#fda4af';
            syncText.innerText = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
        });
    }

    function copyScannedLink() {
        if (!scannedLink) return;
        const btn = document.getElementById('btn_copy');
        const oldHtml = btn.innerHTML;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(scannedLink).then(() => {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> คัดลอกแล้ว!';
                setTimeout(() => { btn.innerHTML = oldHtml; }, 2000);
            }).catch(() => {
                fallbackCopy(scannedLink, btn, oldHtml);
            });
        } else {
            fallbackCopy(scannedLink, btn, oldHtml);
        }
    }

    function fallbackCopy(text, btn, oldHtml) {
        const tempInput = document.createElement('input');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        try {
            document.execCommand('copy');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> คัดลอกแล้ว!';
            setTimeout(() => { btn.innerHTML = oldHtml; }, 2000);
        } catch (e) {
            alert('ไม่สามารถคัดลอกได้โดยอัตโนมัติ กรุณากดคัดลอกด้วยตนเอง');
        }
        document.body.removeChild(tempInput);
    }

    function restartScanner() {
        scannedLink = '';
        document.getElementById('result_container').style.display = 'none';

        // Notify server to reset scanned link in temporary session so PC clears the field
        fetch(`save_scanned_link.php?token=${encodeURIComponent(token)}&action=reset`, {
            method: 'POST'
        }).catch(err => {
            console.error('Reset scanned link error:', err);
        });

        startCamera();
    }

    // Static image file decoding fallback
    function handleImageFile(input) {
        if (!input.files || input.files.length === 0) return;
        const file = input.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            const img = new Image();
            img.onload = async function() {
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);

                let detected = false;
                let qrData = '';

                // Try BarcodeDetector
                if (barcodeDetector) {
                    try {
                        const barcodes = await barcodeDetector.detect(canvas);
                        if (barcodes && barcodes.length > 0) {
                            qrData = barcodes[0].rawValue;
                            detected = true;
                        }
                    } catch(err){}
                }

                // Fallback to jsQR
                if (!detected && typeof jsQR === 'function') {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imgData.data, imgData.width, imgData.height);
                    if (code && code.data) {
                        qrData = code.data;
                        detected = true;
                    }
                }

                if (detected && qrData.trim() !== '') {
                    onQrCodeDetected(qrData.trim());
                } else {
                    alert('ไม่พบ QR Code ในรูปภาพที่เลือก กรุณาเลือกรูปภาพที่ชัดเจนอีกครั้ง');
                }
            };
            img.src = e.target.result;
        };

        reader.readAsDataURL(file);
    }

    window.addEventListener('beforeunload', () => {
        stopCamera();
    });
</script>
</body>
</html>
