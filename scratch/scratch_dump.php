<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = new \Illuminate\Http\Request();
$controller = new \App\Http\Controllers\Api\SyncApiController();
$res = $controller->pullSyncData($req);
echo "RESPONSE: " . $res->getContent() . PHP_EOL;
