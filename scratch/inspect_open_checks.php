<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

echo "=== SQLITE OPEN CHECKS ===" . PHP_EOL;
$openChecks = $sqlite->table('checks')->where('status', 'open')->get();
foreach ($openChecks as $c) {
    echo "Check ID: {$c->id} | DiningTableID: '{$c->dining_table_id}' | Status: {$c->status} | Total: ₺{$c->total}" . PHP_EOL;
    $items = $sqlite->table('check_items')->where('check_id', $c->id)->get();
    foreach ($items as $i) {
        echo "   -> Item ID: {$i->id} | ProductID: {$i->product_id} | Name: '{$i->product_name}' | Qty: {$i->quantity} | Price: ₺{$i->unit_price}" . PHP_EOL;
    }
}
