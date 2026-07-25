<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

echo "=== SQLITE CHECK_ITEMS PRODUCT_NAME INSPECTION ===" . PHP_EOL;
$items = $sqlite->table('check_items')->get(['id', 'check_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'total_price']);
foreach ($items as $i) {
    echo "Item ID: {$i->id} | CheckID: {$i->check_id} | ProdID: {$i->product_id} | Name: '{$i->product_name}' | Qty: {$i->quantity} | Price: ₺{$i->unit_price}" . PHP_EOL;
}
