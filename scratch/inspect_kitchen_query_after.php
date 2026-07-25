<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

echo "=== SQLITE KITCHEN DISPLAY QUERY INSPECTION ===" . PHP_EOL;

$controller = new \App\Http\Controllers\KitchenController();
$request = \Illuminate\Http\Request::create('/kitchen', 'GET');

// Simulate index view
$view = $controller->index($request);
$checks = $view->getData()['checks'];
$stats = $view->getData()['stats'];

echo "Mutfak Toplam Adisyon Sayısı: " . count($checks) . PHP_EOL;
echo "İstatistikler: Total=" . ($stats['total'] ?? 0) . ", Received=" . ($stats['received'] ?? 0) . ", Preparing=" . ($stats['preparing'] ?? 0) . ", Delivered=" . ($stats['delivered'] ?? 0) . PHP_EOL;

foreach ($checks as $c) {
    echo "Check ID: {$c->id} | Table: " . ($c->diningTable?->name ?: 'Hızlı Satış') . " | Status: {$c->status} | KitchenSentAt: '{$c->kitchen_sent_at}'" . PHP_EOL;
    foreach ($c->items as $i) {
        echo "   -> Item ID: {$i->id} | Name: '{$i->product_name}' | Qty: {$i->quantity} | Status: {$i->kitchen_status}" . PHP_EOL;
    }
}
