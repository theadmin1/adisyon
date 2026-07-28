<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$c = DB::connection('sqlite')->table('checks')->where('check_number', 'CHK-CMLE5XGW')->first();
var_dump($c);
