# MeetFlow

ระบบบันทึกตารางนัดประชุม วันอบรม และสัมมนา พร้อมระบบค้นหาผ่าน LINE LIFF, ส่งออกรายงาน และแจ้งเตือนผ่าน Discord Webhook ทำงานบน **Windows Server (IIS + PHP + MySQL)**

---

## 🛠️ Stack และเทคโนโลยีที่ใช้
- **Web Server**: IIS (Internet Information Services) หรือ Apache
- **Backend Language**: PHP (รองรับ PHP 8.0 จนถึง PHP 8.4+)
- **Database**: MySQL / MariaDB (ระบบมี local fallback เป็น SQLite สำหรับทดสอบบนเครื่องพนักงานพัฒนา)
- **CSS Style**: Custom CSS (Dark Glassmorphism Theme)
- **Library**: Font Awesome 6 (Icons), LINE LIFF SDK

---

## 📂 โครงสร้างระบบไฟล์
```text
meetflow/
├── uploads/               # โฟลเดอร์เก็บเอกสารแนบ
│   ├── .htaccess          # ปิดกั้นสิทธิ์รันสคริปต์บน Apache
│   ├── web.config         # ปิดกั้นสิทธิ์รันสคริปต์บน IIS
│   └── .gitignore         # ละเว้นไฟล์อัปโหลดจากการบันทึกเข้าระบบ Git
├── db.php                 # การเชื่อมต่อฐานข้อมูล (MySQL / SQLite Fallback)
├── schema.sql             # ไฟล์สคีมาตารางสำหรับ Import ลงฐานข้อมูล MySQL
├── index.php              # หน้าแสดงผลปฏิทินรายเดือน และหน้าหลัก
├── liff.php               # หน้าจอค้นหาสำหรับ LINE LIFF (Mobile-First)
├── list.php               # หน้าตารางรายการข้อมูลประชุมและอบรม แบ่งหมวดหมู่วันนี้และอนาคต
├── settings.php           # หน้าตั้งค่า Discord Webhook และรหัสผ่าน
├── users.php              # หน้าเพิ่ม/ลบบัญชีผู้ดูแลระบบ (Admin)
├── meeting_types.php      # หน้าควบคุมจัดการประเภทนัดหมายและรหัสสี (Admin)
├── notify_discord.php     # สคริปต์ฟังก์ชันสำหรับส่งแจ้งเตือน Discord Webhook
├── cron_notify.php        # สคริปต์ตรวจสอบแจ้งเตือนสรุปรายวันอัตโนมัติ
├── login.php / logout.php # ระบบจัดการสิทธิ์และความปลอดภัยผู้ใช้งาน
├── CHANGELOG.md           # ไฟล์บันทึกประวัติการพัฒนาและเวอร์ชันอัปเดตระบบ
├── save_meeting.php       # API บันทึกและอัปโหลดไฟล์
├── delete_meeting.php     # API ลบข้อมูล
├── get_meeting.php        # API เรียกดูข้อมูลการนัด
├── docs/                  # โฟลเดอร์เก็บเอกสารคู่มือการพัฒนาและการใช้งาน
│   ├── DEVELOPER.md       # คู่มือการพัฒนาและสถาปัตยกรรมระบบ
│   └── USER_GUIDE.md      # คู่มือการใช้งานระบบสำหรับผู้ใช้ทั่วไปพร้อมภาพตัวอย่าง
├── .gitignore             # ละเว้นไฟล์ที่ไม่ต้องการใน Git (เช่น meetflow.sqlite)
└── README.md              # คู่มือติดตั้งและใช้งานเล่มนี้
```

---

## 🚀 ขั้นตอนการติดตั้งและเปิดใช้งานบน Windows Server IIS + MySQL

### 1. นำฐานข้อมูลเข้าสู่ MySQL
1. สร้างฐานข้อมูลใหม่ใน MySQL Server เช่น ชื่อฐานข้อมูล `meetflow`
2. ตั้งค่าการเข้ารหัสของฐานข้อมูล (Collation) เป็น `utf8mb4_general_ci`
3. ทำการนำเข้า (Import) ไฟล์ SQL จากตัวโปรเจกต์: **[schema.sql](file:///Users/phumiphut/.gemini/antigravity-ide/scratch/meetflow/schema.sql)** เข้าสู่ระบบฐานข้อมูล

### 2. กำหนดค่าการเชื่อมต่อฐานข้อมูล
เปิดไฟล์ **[db.php](file:///Users/phumiphut/.gemini/antigravity-ide/scratch/meetflow/db.php)** และแก้ไขค่าตัวแปรด้านบนให้ตรงกับบัญชีผู้ใช้ MySQL Server บนเซิร์ฟเวอร์ของคุณ:
```php
$host = '127.0.0.1';    // ที่อยู่ของ MySQL Server
$db   = 'meetflow';     // ชื่อฐานข้อมูล
$user = 'root';         // ชื่อผู้ใช้งาน MySQL
$pass = 'your_password'; // รหัสผ่าน MySQL
```

### 3. ตั้งค่าสิทธิ์โฟลเดอร์สำหรับอัปโหลดไฟล์ (IIS เขียนไฟล์)
ในระบบ Windows Server IIS เพื่อให้ PHP สามารถอัปโหลดและลบไฟล์เอกสารแนบในโฟลเดอร์ `uploads/` ได้ ต้องทำการอนุญาตสิทธิ์เขียนไฟล์ให้กับกลุ่มผู้ใช้ว็บไซต์:
1. คลิกขวาที่โฟลเดอร์ `uploads/` เลือก **Properties**
2. ไปที่แท็บ **Security** และคลิกปุ่ม **Edit...**
3. คลิกปุ่ม **Add...** แล้วพิมพ์คำว่า `IIS_IUSRS` หรือ `DefaultAppPool` จากนั้นกดตกลง
4. เลือกกลุ่มผู้ใช้ที่แอดเข้ามา และทำเครื่องหมายถูกที่ช่อง **Modify** และ **Write**
5. กด **Apply** และ **OK**

### 4. การตั้งค่าการแจ้งเตือนรอบวันด้วย Windows Task Scheduler
เพื่อให้ระบบแจ้งเตือนตารางงานประจำวันไปยัง Discord ในเวลาที่แอดมินตั้งค่าไว้ ให้ตั้งค่าสคริปต์ **[cron_notify.php](file:///Users/phumiphut/.gemini/antigravity-ide/scratch/meetflow/cron_notify.php)** ให้รันอัตโนมัติทุกๆ 10 นาที:
1. เปิดโปรแกรม **Task Scheduler** บน Windows Server
2. คลิก **Create Basic Task...** ตั้งชื่อว่า `MeetFlow Daily Reminder`
3. ตั้งค่า Trigger เป็น **Daily** (ทุกวัน)
4. เลือก Action เป็น **Start a program**
   - ช่อง **Program/script**: เลือกตำแหน่งไฟล์ PHP exe บนเครื่อง (เช่น `C:\php\php.exe`)
   - ช่อง **Add arguments**: ใส่พารามิเตอร์ `-f C:\inetpub\wwwroot\meetflow\cron_notify.php`
   - ช่อง **Start in**: ระบุที่ตั้งของโฟลเดอร์โปรเจกต์ `C:\inetpub\wwwroot\meetflow\`
5. หลังสร้างเสร็จ ให้ดับเบิลคลิกเปิด Property ของงานนี้ ไปที่แท็บ **Triggers** กด Edit ตัว Trigger และตั้งค่า **Repeat task every: 10 minutes** เพื่อให้ทำงานตรวจสอบทุกๆ 10 นาที (โดยโค้ดระบบจะบันทึกการส่งลงฐานข้อมูลเพื่อควบคุมให้ยิงเข้า Discord เพียง 1 ครั้งต่อวันเท่านั้น)

### 5. ตั้งค่าเชื่อมต่อ LINE LIFF
สำหรับหน้าสืบค้นข้อมูลปฏิทินที่รวดเร็วบนมือถือผ่าน LINE LIFF:
1. สร้าง LINE LIFF App ในคอนโซล [LINE Developers](https://developers.line.biz/)
2. กำหนด **Endpoint URL** ไปยังหน้าระบบของคุณ เช่น `https://yourdomain.com/meetflow/liff.php`
3. นำ **LIFF ID** ที่ได้ไปแทนที่ลงในโค้ดส่วนท้ายของไฟล์ **[liff.php](file:///Users/phumiphut/.gemini/antigravity-ide/scratch/meetflow/liff.php)**:
   ```javascript
   liff.init({
       liffId: "YOUR-LIFF-ID" // แทนที่ด้วย LIFF ID ของคุณ
   })
   ```

---

## 🔒 ความปลอดภัยของผู้ใช้งานระดับแอดมิน
- ผู้ใช้ระดับทั่วไป สามารถเปิดดูปฏิทินและสืบค้นเอกสารผ่าน LINE LIFF ได้อย่างเดียวโดยไม่มีสิทธิ์บันทึกข้อมูล
- บัญชีผู้ดูแลระบบ (Admin) เริ่มต้นหลังจากดึงข้อมูลสคีมา:
  - **Username**: `admin`
  - **Password**: `admin1234`
- แอดมินสามารถเปลี่ยนรหัสผ่านได้ที่หน้า **ตั้งค่าระบบ** และแอดมินคนอื่นๆ สามารถเพิ่มผู้ใช้งานแอดมินเพิ่มได้ที่หน้า **จัดการผู้ใช้**
