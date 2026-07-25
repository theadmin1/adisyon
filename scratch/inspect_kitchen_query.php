<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

echo "=== SQLITE KITCHEN CHECKS INSPECTION ===" . PHP_EOL;

$totalChecks = $sqlite->table('checks')->count();
$kitchenNotNull = $sqlite->table('checks')->whereNotNull('kitchen_sent_at')->count();
$openChecks = $sqlite->table('checks')->where('status', 'open')->count();

echo "Toplam Adisyon: {$totalChecks} | kitchen_sent_at Dolu Olan: {$kitchenNotNull} | Status Open Olan: {$openChecks}" . PHP_EOL;

$checks = $sqlite->table('checks')->get(['id', 'status', 'kitchen_sent_at', 'opened_at']);
foreach ($checks as $c) {
    echo "Check ID: {$c->id} | Status: {$c->status} | KitchenSentAt: '{$c->kitchen_sent_at}' | OpenedAt: '{$c->opened_at}'" . PHP_EOL;
}
