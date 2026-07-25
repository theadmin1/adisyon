<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

$tables = [
    'users',
    'staff_profiles',
    'halls',
    'dining_tables',
    'categories',
    'products',
    'checks',
    'check_items',
    'payments',
    'delivery_orders',
    'delivery_integrations',
    'stock_movements',
    'settings',
    'devices',
];

$sqlDump = "-- ADİSYON SYSTEM SQLITE FULL DATABASE BACKUP DUMP" . PHP_EOL;
$sqlDump .= "-- Created At: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;
$sqlDump .= "PRAGMA foreign_keys = OFF;" . PHP_EOL . PHP_EOL;

foreach ($tables as $table) {
    if (!\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable($table)) {
        continue;
    }
    
    $rows = $sqlite->table($table)->get();
    $sqlDump .= "-- Table: {$table} (" . count($rows) . " rows)" . PHP_EOL;
    
    foreach ($rows as $row) {
        $arr = (array) $row;
        $cols = array_keys($arr);
        $vals = array_map(function ($val) use ($sqlite) {
            if ($val === null) return 'NULL';
            return $sqlite->getPdo()->quote((string)$val);
        }, array_values($arr));
        
        $sqlDump .= "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ");" . PHP_EOL;
    }
    $sqlDump .= PHP_EOL;
}

file_put_contents(__DIR__ . '/../backups/database_backup_20260726.sql', $sqlDump);
echo "✅ SQL dump file successfully generated: backups/database_backup_20260726.sql (" . number_format(strlen($sqlDump)) . " bytes)" . PHP_EOL;
