<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== SQLITE DINING TABLES & OPEN CHECKS ===\n";
$tables = DB::connection('sqlite')->table('dining_tables')->get();
foreach ($tables as $t) {
    $openCheck = DB::connection('sqlite')->table('checks')
        ->where('dining_table_id', $t->id)
        ->whereIn('status', ['open', 'awaiting_payment'])
        ->first();
    
    $checkStr = $openCheck ? "OPEN CHECK ID: {$openCheck->id} | Total: {$openCheck->total} TL" : "NO OPEN CHECK";
    echo "Table ID: {$t->id} | Name: {$t->name} | DB Status: {$t->status} | Real Status: {$checkStr}\n";
}
