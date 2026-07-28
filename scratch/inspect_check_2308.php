<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$c = DB::connection('sqlite')->table('checks')->where('id', 2308)->first();
echo "Check 2308: " . json_encode($c, JSON_PRETTY_PRINT) . "\n\n";

$items = DB::connection('sqlite')->table('check_items')->where('check_id', 2308)->get();
echo "Items for 2308: " . json_encode($items, JSON_PRETTY_PRINT) . "\n";
