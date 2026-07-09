<?php
// migrate.php - Manual Database Migration Helper
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MeetFlow Database Migration Helper ===\n\n";

try {
    echo "Checking if column 'meeting_type' exists...\n";
    $pdo->query("SELECT meeting_type FROM meetings LIMIT 1");
    echo "Success: 'meeting_type' column already exists in 'meetings' table.\n";
} catch (\Exception $ex) {
    echo "Column 'meeting_type' is missing. Attempting migration...\n";
    
    try {
        if ($is_sqlite) {
            $pdo->exec("ALTER TABLE meetings ADD COLUMN meeting_type VARCHAR(50) DEFAULT 'meeting'");
            echo "SQLite Migration: Added 'meeting_type' column successfully.\n";
        } else {
            $pdo->exec("ALTER TABLE meetings ADD COLUMN `meeting_type` VARCHAR(50) DEFAULT 'meeting' AFTER `description`");
            echo "MySQL Migration: Added 'meeting_type' column successfully.\n";
        }
    } catch (\Exception $migration_error) {
        echo "\n[ERROR] Migration failed: " . $migration_error->getMessage() . "\n\n";
        echo "Please run the following SQL command manually in your MySQL Database (phpMyAdmin, Navicat, CLI, etc.):\n\n";
        echo "ALTER TABLE meetings ADD COLUMN `meeting_type` VARCHAR(50) DEFAULT 'meeting' AFTER `description`;\n\n";
    }
}
