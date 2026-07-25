<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

echo "=== SQLITE CHECKS DATES ===" . PHP_EOL;
$checks = $sqlite->table('checks')->get(['id', 'status', 'opened_at', 'closed_at', 'created_at', 'total']);
foreach ($checks as $c) {
    echo "ID: {$c->id} | Status: {$c->status} | OpenedAt: {$c->opened_at} | ClosedAt: {$c->closed_at} | CreatedAt: {$c->created_at} | Total: {$c->total}" . PHP_EOL;
}

echo PHP_EOL . "=== SQLITE PAYMENTS DATES ===" . PHP_EOL;
$payments = $sqlite->table('payments')->get(['id', 'check_id', 'payment_method', 'amount', 'created_at']);
foreach ($payments as $p) {
    echo "ID: {$p->id} | CheckID: {$p->check_id} | Method: {$p->payment_method} | Amount: {$p->amount} | CreatedAt: {$p->created_at}" . PHP_EOL;
}
