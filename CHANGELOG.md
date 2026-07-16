# Changelog

All notable changes to the **MeetFlow** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2026-07-16

### Added
- **Admin Note Feature**: Added an internal `admin_note` field in the meetings table with admin-only textareas, selective rendering, and API security filtering to avoid data leakage for guests.
- **Multi-day Events Feature**: Spans calendar cell events across multiple days using overlapping range checks. Upgraded lists dashboard and LINE LIFF card groups to recognize and group active date ranges.
- **Related Link Rebranding**: Rebranded online meeting links to "Related Link" (ลิงก์ที่เกี่ยวข้อง) with generic chain link icons (`fa-link`) to accommodate registration forms and document sharing links.

---

## [1.4.0] - 2026-07-16

### Added
- **Copy Formatting Improvement**: Prepend the **Meeting Title** followed by a newline before the shareable URL (e.g., `[ชื่อเรื่อง]\n[ลิงก์]`) when copying links to the clipboard, applied consistently across:
  - Calendar Details Modal (`index.php`)
  - Lists Dashboard Table Row (`list.php`)
  - LINE LIFF search cards view (`liff.php`)

---

## [1.3.0] - 2026-07-14

### Added
- **Dynamic Meeting Types (CRUD)**: Added a dedicated management page (`meeting_types.php`) for administrators to insert, edit, and delete meeting types with custom labels and HTML color pickers.
- **Auto-migrations & Seeding**: Updated database initializers in `db.php` and `schema.sql` to automatically provision the `meeting_types` table and seed defaults (`meeting` and `training`).
- **Dynamic Badges Theme**: Render calendar event badges and lists dynamically using custom CSS variables (`--type-color`, `--type-bg`) pulled from database colors.
- **Details Dialog Footer Grid**: Restructured buttons (Delete, Copy, Edit, Close) using a responsive CSS grid that wraps gracefully on small mobile devices while maintaining separate left/right layouts on desktop.
- **LINE LIFF Copy Details**: Added a full-width "คัดลอกลิงก์ข้อมูลสำหรับส่งต่อ" button inside expanded search cards in `liff.php`.

### Fixed
- **Share Icon Rendering**: Fixed the share button icon on the lists dashboard by changing the Font Awesome class from regular (`fa-regular`) to solid (`fa-solid fa-share-nodes`).

---

## [1.2.0] - 2026-07-09

### Added
- **Direct View Parameter (`?view=ID`)**: Added support for direct meeting details linking. Visiting the URL immediately opens the details modal and dynamically cleans the URL parameter from the address bar on modal exit.
- **Database Migration Script (`migrate.php`)**: Added a self-healing utility script to check database integrity and inject missing columns automatically.
- **LINE LIFF Search Grouping**: Grouped search and upcoming results chronologically into Today, This Week, This Month, Next Months, and Past groups.
- **Grayscale Past Events**: Applied visual indicators (opacity reduction and grayscale filters) to past events in the mobile LINE LIFF search view.

### Fixed
- **IIS 500 Attachment Error**: Replaced locking script handler blocks with native web.config `requestFiltering` file extensions bans to prevent IIS errors when accessing uploaded attachments.
- **Dropdown Styling**: Fixed CSS dropdown select background colors in dark mode.

---

## [1.1.0] - 2026-07-08

### Added
- **Explicit Meeting Type**: Added a specific meeting type (ประชุม / อบรม) field to the creation form and database schema.
- **Tabbed Lists Dashboard (`list.php`)**: Converted the static reports page into a list panel containing tabbed partitions (Today, Upcoming, Past) with inline Edit and Delete options for administrators.
- **Referrer-based Redirection**: Implemented redirects back to the originating dashboard page (calendar or list) after saving or cancelling meeting edits.
- **Mobile Header Grid**: Optimized the calendar header navigation buttons for mobile layouts (2-column actions, full-width logout).
- **IIS Discord Fallback**: Added robust stream-based HTTP fallback methods ignoring SSL verification when POSTing webhooks in environments with missing root certificates.
- **Database Fallback & Auto-seeding**: Enabled automatic local SQLite fallback (`meetflow.sqlite`) on database connection errors and auto-seeds the default admin credentials (`admin` / `admin1234`).

### Fixed
- **All-day Event Time Bounds**: Standardized "ตลอดทั้งวัน" (All-day) boundaries to `08:30` - `16:30`.

---

## [1.0.0] - 2026-07-08

### Added
- **Initial Release**: Launched MeetFlow monthly calendar dashboard with glassmorphism UI.
- **Discord Notification System**: Real-time webhook notifications for event creations, edits, and deletions.
- **Daily Summaries (`cron_notify.php`)**: Scheduled daily reminder summary alerts.
- **LINE LIFF Mobile Search (`liff.php`)**: Mobile-first search interface optimized for LINE app webviews querying by document and office number.
