-- Create MeetFlow Database Schema
CREATE DATABASE IF NOT EXISTS `meetflow` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `meetflow`;

-- Meetings Table
CREATE TABLE IF NOT EXISTS `meetings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `meeting_type` VARCHAR(50) DEFAULT 'meeting',
    `meeting_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `doc_no` VARCHAR(100) DEFAULT NULL,
    `office_no` VARCHAR(100) DEFAULT NULL,
    `meeting_link` VARCHAR(1000) DEFAULT NULL,
    `doc_file` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meeting Attendees Table (Supports multiple attendees per meeting)
CREATE TABLE IF NOT EXISTS `meeting_attendees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meeting_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table (Admin Users)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings Table (Discord configuration, etc.)
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meeting Types Table (Dynamic meeting classifications)
CREATE TABLE IF NOT EXISTS `meeting_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type_key` VARCHAR(50) NOT NULL UNIQUE,
    `type_name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(20) DEFAULT '#3b82f6',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default meeting types
INSERT INTO `meeting_types` (`type_key`, `type_name`, `color`) VALUES
('meeting', 'ประชุม', '#3b82f6'),
('training', 'อบรม', '#10b981')
ON DUPLICATE KEY UPDATE `type_key`=`type_key`;

-- Indexes for search optimization
CREATE INDEX idx_meetings_date ON meetings(meeting_date);
CREATE INDEX idx_meetings_doc_no ON meetings(doc_no);
CREATE INDEX idx_meetings_office_no ON meetings(office_no);
CREATE INDEX idx_attendees_meeting_id ON meeting_attendees(meeting_id);

-- Insert Sample Data for testing
INSERT INTO `meetings` (`id`, `title`, `description`, `meeting_date`, `start_time`, `end_time`, `doc_no`, `office_no`, `meeting_link`, `doc_file`) VALUES
(1, 'ประชุมวางแผนงบประมาณประจำปี 2570', 'ประชุมหารือแนวทางการจัดทำงบประมาณรายจ่ายประจำปีงบประมาณ พ.ศ. 2570 ของส่วนงานพัฒนาเทคโนโลยีสารสนเทศ', CURDATE(), '09:30:00', '12:00:00', 'นร 0505/ว123', 'รับที่ 4567/2569', 'https://meet.google.com/abc-defg-hij', NULL),
(2, 'อบรมการใช้งานระบบจัดการความรู้ (KM)', 'การฝึกอบรมการใช้งานโปรแกรมแบ่งปันความรู้ภายในองค์กรสำหรับเจ้าหน้าที่เข้าใหม่', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '13:30:00', '16:30:00', 'นร 0505/ว124', 'รับที่ 4568/2569', 'https://teams.microsoft.com/l/meetup-join/example', NULL);

INSERT INTO `meeting_attendees` (`meeting_id`, `name`) VALUES
(1, 'นายสมชาย ดีเด่น'),
(1, 'นางสาวสมศรี เรียนดี'),
(1, 'นายวิชัย ว่องไว'),
(2, 'นางสาวจารุวรรณ นามสมมติ'),
(2, 'นายสมศักดิ์ รักเรียน');

-- Seed Admin User (username: admin, password: admin1234 -> hashed via PASSWORD_BCRYPT)
-- Default bcrypt hash for 'admin1234': $2y$12$bTIzCR6FqelD/SkWc9f7MOcrFTjxTzmZb2qf3jBE6GtIVk3fkzmzi
INSERT INTO `users` (`username`, `password`) VALUES
('admin', '$2y$12$bTIzCR6FqelD/SkWc9f7MOcrFTjxTzmZb2qf3jBE6GtIVk3fkzmzi')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed default Discord Webhook Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('discord_webhook_url', ''),
('notify_create', '0'),
('notify_update', '0'),
('notify_delete', '0'),
('notify_daily', '0'),
('notify_daily_time', '08:00'),
('last_cron_run_date', '')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;
