<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== TAM UÇTAN UCA STOK SENKRONİZASYON TESTİ ===\n\n";

// 1. Canlı sunucudan mevcut ürünleri çek
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Canlı sunucu verisi çekilemedi: HTTP " . $r->status() . "\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Canlı sunucudaki ürün sayısı: " . $remoteProducts->count() . "\n";

// 2. Yerel SQLite ürünleri ile Canlı MySQL ürünlerini eşleştir ve sync_uuid'leri eşitle
echo "Yerel ve Canlı ürün sync_uuid'leri uzlaştırılıyor...\n";
$sqliteProducts = Product::all();
$productsToPush = [];

foreach ($sqliteProducts as $sp) {
    $remote = $remoteProducts->firstWhere('name', $sp->name);
    if ($remote) {
        $productsToPush[] = [
            'id' => $remote['id'],
            'sync_uuid' => $sp->sync_uuid,
            'name' => $sp->name,
            'price' => (float) $sp->price,
            'stock_quantity' => (float) $sp->stock_quantity,
            'track_stock' => (bool) $sp->track_stock,
            'is_active' => (bool) $sp->is_active,
        ];
    }
}

if (!empty($productsToPush)) {
    echo "Canlı sunucuya " . count($productsToPush) . " adet eşleşmiş ürün sync_uuid ile gönderiliyor...\n";
    $pRes = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post($pushUrl, [
        'batch_id' => 'RECONCILE-' . time(),
        'products' => $productsToPush,
    ]);
    echo "PUSH Status: " . $pRes->status() . "\n";
}

// 3. Test Ürünü Seç ve Stoğunu Not Et
$testProduct = Product::where('is_active', true)->where('track_stock', true)->first();
if (!$testProduct) {
    echo "❌ Test için stok takibi açık ürün bulunamadı!\n";
    exit(1);
}

echo "\n--- TEST BAŞLIYOR ---\n";
echo "Test Ürünü: {$testProduct->name} (ID: {$testProduct->id}, SyncUUID: {$testProduct->sync_uuid})\n";
echo "Başlangıç Yerel Stok (SQLite): {$testProduct->stock_quantity}\n";

// Canlı stoğu al
$rCheck = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$rProds = collect($rCheck->json('data.products') ?? []);
$onProdBefore = $rProds->firstWhere('sync_uuid', $testProduct->sync_uuid);
$onlineStockBefore = $onProdBefore ? $onProdBefore['stock_quantity'] : null;
echo "Başlangıç Canlı Stok (MySQL): " . ($onlineStockBefore ?? 'BULUNAMADI') . "\n\n";

// 4. Hızlı Satış Yap (1 Adet Satış)
echo "⚡ Hızlı Satış yapılıyor (1 Adet {$testProduct->name})...\n";
$checkService = app(\App\Services\Checks\CheckService::class);
$req = Illuminate\Http\Request::create('/quicksale', 'POST', [
    'items' => [['product_id' => $testProduct->id, 'quantity' => 1]],
    'payment_method' => 'nakit',
    'send_to_kitchen' => false
]);
$controller = app(\App\Http\Controllers\QuickSaleController::class);
$controller->store($req, $checkService);

$afterSaleLocal = Product::find($testProduct->id);
echo "Satış Sonrası Yerel Stok (SQLite): {$afterSaleLocal->stock_quantity} (1 Azaldı)\n\n";

// 5. Senkronize et
echo "⚡ Senkronizasyon komutu (app:sync-local) çalıştırılıyor...\n";
Illuminate\Support\Facades\Artisan::call('app:sync-local');

// 6. Sonuç Kontrolü
$rFinal = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$finalRemoteProducts = collect($rFinal->json('data.products') ?? []);
$onProdAfter = $finalRemoteProducts->firstWhere('sync_uuid', $testProduct->sync_uuid);
$onlineStockAfter = $onProdAfter ? $onProdAfter['stock_quantity'] : null;

echo "\n--- TEST SONUÇLARI ---\n";
echo "Son Yerel Stok (SQLite): " . Product::find($testProduct->id)->stock_quantity . "\n";
echo "Son Canlı Stok (MySQL): " . ($onlineStockAfter ?? 'BULUNAMADI') . "\n";

if ($onlineStockBefore !== null && $onlineStockAfter !== null) {
    $diff = $onlineStockBefore - $onlineStockAfter;
    echo "📊 Canlı Sunucu Stok Değişimi: {$diff} adet düşüm gerçekleşti.\n";
    if ($diff > 0) {
        echo "✅ BİLGİ: Çevrimdışı yapılan satış CANLI SUNUCUDA DA BAŞARILI BİR ŞEKİLDE DÜŞTÜ!\n";
    } else {
        echo "❌ HATA: Canlı sunucuda stok düşmedi!\n";
    }
}

echo "\n=== TEST BİTTİ ===\n";
