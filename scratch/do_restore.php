<?php
$baseDir = 'c:/Users/Admin/Desktop/projeler/adisyon-services';
$zipPath = $baseDir . '/backups/project_backup_20260727.zip';
$dbBackupPath = $baseDir . '/backups/database_backup_20260727_0848.sqlite';
$targetDbPath = $baseDir . '/database/database.sqlite';

echo "=== RESTORING BACKUP ===\n\n";

if (!file_exists($zipPath)) {
    echo "❌ ZIP backup file not found at: {$zipPath}\n";
    exit(1);
}

echo "1. Unzipping {$zipPath} into {$baseDir}...\n";
$zip = new ZipArchive();
if ($zip->open($zipPath) === true) {
    $zip->extractTo($baseDir);
    $zip->close();
    echo "✅ Project files restored from ZIP archive.\n\n";
} else {
    echo "❌ Failed to open ZIP archive.\n";
    exit(1);
}

if (file_exists($dbBackupPath)) {
    echo "2. Restoring SQLite database from {$dbBackupPath} to {$targetDbPath}...\n";
    if (copy($dbBackupPath, $targetDbPath)) {
        echo "✅ SQLite database restored successfully.\n\n";
    } else {
        echo "❌ Failed to copy SQLite database file.\n\n";
    }
} else {
    echo "⚠️ Database backup file not found at {$dbBackupPath}, skipping DB restore.\n\n";
}

echo "=== RESTORE COMPLETE ===\n";
