<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');

echo "==========================================================" . PHP_EOL;
echo "🍕 YEMEKSEPETI CANLI SİPARİŞ & WEBHOOK ENTEGRASYON TESTİ" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

$controller = new \App\Http\Controllers\Api\YemeksepetiController(new \App\Services\YemeksepetiService());
$request = \Illuminate\Http\Request::create('/api/v1/integrations/yemeksepeti/test-order', 'POST');

$response = $controller->simulateTestOrder($request);
$data = json_decode($response->getContent(), true);

echo "HTTP Status: " . $response->getStatusCode() . PHP_EOL;
echo "Yanıt Gövdesi:" . PHP_EOL;
print_r($data);

// Veritabanı Kontrolü
$latestOrder = \App\Models\DeliveryOrder::on('sqlite')->where('channel', 'yemeksepeti')->latest()->first();
if ($latestOrder) {
    echo PHP_EOL . "✅ YEMEKSEPETI VERİTABANI DOĞRULAMA BAŞARILI!" . PHP_EOL;
    echo "Sipariş ID: {$latestOrder->id} | No: {$latestOrder->order_number} | Platform ID: {$latestOrder->platform_order_id}" . PHP_EOL;
    echo "Müşteri: {$latestOrder->customer_name} ({$latestOrder->customer_phone})" . PHP_EOL;
    echo "Adres: {$latestOrder->delivery_address} (Not: {$latestOrder->address_note})" . PHP_EOL;
    echo "Tutar: ₺{$latestOrder->total} (Ara Toplam: ₺{$latestOrder->subtotal}, Kurye: ₺{$latestOrder->delivery_fee}, İskonto: ₺{$latestOrder->discount_total})" . PHP_EOL;
    echo "Ödeme Yöntemi: {$latestOrder->payment_method} | Kurye: {$latestOrder->courier_name}" . PHP_EOL;
    echo "--- Sipariş Kalemleri ---" . PHP_EOL;
    $items = is_string($latestOrder->items) ? json_decode($latestOrder->items, true) : $latestOrder->items;
    foreach ($items as $item) {
        echo "   -> {$item['quantity']}x {$item['name']} (₺{$item['price']})" . (!empty($item['options']) ? " [Seçenekler: {$item['options']}]" : "") . (!empty($item['note']) ? " (Not: {$item['note']})" : "") . PHP_EOL;
    }
}
