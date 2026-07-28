<?php
/**
 * BAŞDAN AŞAĞI TAM SİSTEM YEDEKLEME KODU
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

$timestamp = date('Ymd_His');
$backupDir = base_path('backups');
if (!File::exists($backupDir)) {
    File::makeDirectory($backupDir, 0755, true, true);
}

echo "=================================================\n";
echo "🚀 BAŞDAN AŞAĞI SISTEM YEDEKLEME BAŞLATILDI ({$timestamp})\n";
echo "=================================================\n\n";

$createdFiles = [];

// 1. YEREL SQLITE VERİTABANI KOPYASI
echo "1. Yerel SQLite Veritabanı Yedekleniyor...\n";
$sqlitePath = config('database.connections.sqlite.database');
if (File::exists($sqlitePath)) {
    $sqliteBackupPath = "{$backupDir}/database_backup_{$timestamp}.sqlite";
    File::copy($sqlitePath, $sqliteBackupPath);
    $sizeKb = round(File::size($sqliteBackupPath) / 1024, 2);
    echo "   ✅ SQLite Yedeği Oluşturuldu: database_backup_{$timestamp}.sqlite ({$sizeKb} KB)\n";
    $createdFiles[] = $sqliteBackupPath;
} else {
    echo "   ⚠️ Yerel SQLite veritabanı bulunamadı.\n";
}

// 2. YEREL SQLITE SQL DUMP
echo "\n2. Yerel Veriler SQL Formatına Dönüştürülüyor...\n";
$tables = ['users', 'staff_profiles', 'halls', 'dining_tables', 'categories', 'products', 'checks', 'check_items', 'payments', 'stock_movements', 'deleted_records', 'settings'];
$sqlDumpContent = "-- ADİSYON SİSTEMİ TAM SQLITE VERİTABANI YEDEĞİ\n";
$sqlDumpContent .= "-- Oluşturulma Tarihi: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable($table)) {
        $rows = DB::connection('sqlite')->table($table)->get();
        $sqlDumpContent .= "-- Table: {$table} (" . $rows->count() . " rows)\n";
        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $cols = array_keys($rowArr);
            $vals = array_map(function ($v) {
                if (is_null($v)) return 'NULL';
                if (is_bool($v)) return $v ? '1' : '0';
                return "'" . addslashes((string)$v) . "'";
            }, array_values($rowArr));
            $sqlDumpContent .= "INSERT INTO `{$table}` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        $sqlDumpContent .= "\n";
    }
}

$sqlDumpPath = "{$backupDir}/database_dump_{$timestamp}.sql";
File::put($sqlDumpPath, $sqlDumpContent);
$sizeKb = round(File::size($sqlDumpPath) / 1024, 2);
echo "   ✅ SQL Dump Oluşturuldu: database_dump_{$timestamp}.sql ({$sizeKb} KB)\n";
$createdFiles[] = $sqlDumpPath;

// 3. CANLI MYSQL CANLI VERİ YEDEĞİ (JSON)
echo "\n3. Canlı Sunucu (MySQL) Verileri Çekilip Yedekleniyor...\n";
$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
try {
    $r = Http::withoutVerifying()->timeout(20)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

    if ($r->successful() && $r->json('success')) {
        $liveBackupPath = "{$backupDir}/mysql_live_data_{$timestamp}.json";
        File::put($liveBackupPath, json_encode($r->json('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $sizeKb = round(File::size($liveBackupPath) / 1024, 2);
        echo "   ✅ Canlı Sunucu Verileri Yedeklendi: mysql_live_data_{$timestamp}.json ({$sizeKb} KB)\n";
        $createdFiles[] = $liveBackupPath;
    } else {
        echo "   ⚠️ Canlı sunucu verileri çekilemedi: HTTP " . $r->status() . "\n";
    }
} catch (\Throwable $e) {
    echo "   ⚠️ Canlı sunucu bağlantı hatası: " . $e->getMessage() . "\n";
}

// 4. GIT BUNDLE KOD VE REPO YEDEĞİ
echo "\n4. Git Kod ve Sürüm Geçmişi Yedeği Alınıyor...\n";
$bundlePath = "{$backupDir}/git_bundle_{$timestamp}.bundle";
$cmd = 'git bundle create "' . $bundlePath . '" --all 2>&1';
$out = shell_exec($cmd);
if (File::exists($bundlePath)) {
    $sizeKb = round(File::size($bundlePath) / 1024, 2);
    echo "   ✅ Git Bundle Yedeği Alındı: git_bundle_{$timestamp}.bundle ({$sizeKb} KB)\n";
    $createdFiles[] = $bundlePath;
} else {
    echo "   ⚠️ Git Bundle oluşturulamadı: {$out}\n";
}

// 5. YEDEK MANİFESTİ OLUŞTUR
$manifestContent = "=================================================\n";
$manifestContent .= "ADİSYON SİSTEMİ TAM YEDEK MANİFESTİ\n";
$manifestContent .= "Oluşturulma Zamanı: " . date('Y-m-d H:i:s') . "\n";
$manifestContent .= "=================================================\n\n";

foreach ($createdFiles as $f) {
    $name = basename($f);
    $bytes = File::size($f);
    $sizeKb = round($bytes / 1024, 2);
    $manifestContent .= "- {$name} ({$sizeKb} KB / {$bytes} bytes)\n";
}

$manifestPath = "{$backupDir}/backup_manifest_{$timestamp}.txt";
File::put($manifestPath, $manifestContent);
echo "\n=================================================\n";
echo "🎉 BAŞDAN AŞAĞI TAM SİSTEM YEDEĞİ BAŞARIYLA TAMAMLANTI!\n";
echo "📁 Tüm yedek dosyaları projeler/adisyon-services/backups dizinine kaydedildi.\n";
echo "=================================================\n";
