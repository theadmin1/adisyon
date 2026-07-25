<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "User IDs: " . json_encode(DB::connection('sqlite')->table('users')->pluck('id')) . "\n";
echo "Branch IDs: " . json_encode(DB::connection('sqlite')->table('branches')->pluck('id')) . "\n";
echo "Dining Table IDs: " . json_encode(DB::connection('sqlite')->table('dining_tables')->pluck('id')) . "\n";
echo "Staff Profile IDs: " . json_encode(DB::connection('sqlite')->table('staff_profiles')->pluck('id')) . "\n";
