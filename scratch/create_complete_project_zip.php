<?php
/**
 * TÜM PROJE DİZİNİ VE VERİTABANLARINI TEK BİR ZİP PAKETİNDE TOPLAMA
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\File;

$timestamp = date('Ymd_His');
$backupDir = base_path('backups');
$zipPath = base_path("backups/complete_full_system_{$timestamp}.zip");
$zipFileName = basename($zipPath);

echo "=================================================\n";
echo "📦 EKSİKSİZ PROJE ZIP ARŞİVİ OLUŞTURULUYOR...\n";
echo "=================================================\n\n";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("❌ ZIP dosyası oluşturulamadı!\n");
}

$sourcePath = base_path();
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$excludedDirs = ['vendor', 'node_modules', '.git', 'storage/framework/cache', 'storage/logs', 'backups'];
$addedCount = 0;

foreach ($files as $file) {
    $filePath = str_replace('\\', '/', $file->getRealPath());
    $relativePath = str_replace(str_replace('\\', '/', $sourcePath) . '/', '', $filePath);

    // Skip excluded directories
    $skip = false;
    foreach ($excludedDirs as $ex) {
        if (str_starts_with($relativePath, $ex)) {
            $skip = true;
            break;
        }
    }

    if ($skip) continue;

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else if ($file->isFile()) {
        $zip->addFile($filePath, $relativePath);
        $addedCount++;
    }
}

$zip->close();

$sizeMb = round(File::size($zipPath) / (1024 * 1024), 2);
echo "✅ TOPLAM {$addedCount} ADET PROJE VE VERİTABANI DOSYASI ZIP İÇİNE SIKIŞTIRILDI!\n";
echo "📁 Arşiv Adı: {$zipFileName} ({$sizeMb} MB)\n";
echo "📍 Konum: projeler/adisyon-services/backups/{$zipFileName}\n\n";
