<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
$dbNameKey = 'Tables_in_' . DB::getDatabaseName();

$output = "-- ADISYON RESTORAN & POS SYSTEM FULL DATABASE BACKUP\n";
$output .= "-- Generated At: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Host: 10.0.1.1 | Database: adisyon\n\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$count = 0;
foreach ($tables as $t) {
    $tableName = $t->$dbNameKey;
    $output .= "-- --------------------------------------------------------\n";
    $output .= "-- Table structure for table `{$tableName}`\n";
    $output .= "-- --------------------------------------------------------\n";
    $output .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
    
    $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
    $createSql = $createTable[0]->{'Create Table'};
    $output .= $createSql . ";\n\n";
    
    $rows = DB::table($tableName)->get();
    if ($rows->count() > 0) {
        $output .= "-- Dumping data for table `{$tableName}` (" . $rows->count() . " rows)\n";
        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $cols = array_map(fn($c) => "`{$c}`", array_keys($rowArr));
            $vals = array_map(function($v) {
                if ($v === null) return 'NULL';
                return DB::getPdo()->quote($v);
            }, array_values($rowArr));
            
            $output .= "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $output .= "\n";
    }
    $count++;
}

$output .= "SET FOREIGN_KEY_CHECKS=1;\n";
$filePath = __DIR__ . '/storage/app/database_backup_20260724.sql';
file_put_contents($filePath, $output);
echo "SUCCESS: {$count} tables backed up to {$filePath} (" . number_format(strlen($output)) . " bytes)\n";
