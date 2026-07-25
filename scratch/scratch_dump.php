<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$d = \App\Models\Device::first();
echo "DEVICE BRANCH ID: " . ($d ? $d->branch_id : 'NO DEVICE') . PHP_EOL;

$req = new \Illuminate\Http\Request();
$controller = new \App\Http\Controllers\Api\SyncApiController();
$res = $controller->pullSyncData($req);
echo "RESPONSE: " . $res->getContent() . PHP_EOL;
