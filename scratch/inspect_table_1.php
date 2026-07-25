<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MASA 1 & AÇIK ADİSYON KALEMLERİ ===" . PHP_EOL;

$table = \App\Models\DiningTable::on('sqlite')->find(1);
if (!$table) {
    echo "❌ Masa 1 bulunamadı." . PHP_EOL;
    exit;
}

$statusVal = is_object($table->status) ? $table->status->value : $table->status;
echo "Masa Adı: " . $table->name . " | Durum: " . $statusVal . PHP_EOL;

$activeCheck = \App\Models\Check::on('sqlite')
    ->where('dining_table_id', $table->id)
    ->whereIn('status', ['open', 'awaiting_payment'])
    ->with('items')
    ->latest()
    ->first();

if (!$activeCheck) {
    echo "⚠️ Masa 1'de açık adisyon bulunamadı." . PHP_EOL;
} else {
    echo "Adisyon ID: {$activeCheck->id} | Adisyon No: {$activeCheck->check_number} | Toplam: ₺{$activeCheck->total}" . PHP_EOL;
    echo "--- YEMEK KALEMLERİ ---" . PHP_EOL;
    foreach ($activeCheck->items as $item) {
        $prodName = $item->product_name ?: ($item->product?->name ?: 'BOŞ_İSİM');
        echo "Item ID: {$item->id} | ProductID: {$item->product_id} | Name: '{$prodName}' (Ham product_name: '{$item->product_name}') | Qty: {$item->quantity} | UnitPrice: ₺{$item->unit_price} | Total: ₺{$item->total_price}" . PHP_EOL;
    }
}
