<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== NİHAİ CANLI TEST: 1 ADET COCA-COLA HIZLI SATIŞI ===\n\n";

$cocaCola = Product::where('name', 'like', '%Coca-Cola%')->first();
if (!$cocaCola) {
    echo "❌ Coca-Cola bulunamadı!\n";
    exit(1);
}

echo "1. Satış Öncesi Durum:\n";
echo "   Ürün: {$cocaCola->name}\n";
echo "   SQLite Stok: {$cocaCola->stock_quantity}\n";

$r1 = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$onlineProd1 = collect($r1->json('data.products') ?? [])->firstWhere('sync_uuid', $cocaCola->sync_uuid);
echo "   MySQL Online Stok: " . ($onlineProd1 ? $onlineProd1['stock_quantity'] : 'N/A') . "\n\n";

echo "2. Hızlı Satış Yapılıyor (1 Adet {$cocaCola->name})...\n";
$checkService = app(\App\Services\Checks\CheckService::class);
$req = Illuminate\Http\Request::create('/quicksale', 'POST', [
    'items' => [['product_id' => $cocaCola->id, 'quantity' => 1]],
    'payment_method' => 'nakit',
    'send_to_kitchen' => false
]);
$controller = app(\App\Http\Controllers\QuickSaleController::class);
$controller->store($req, $checkService);

$cocaColaAfter = Product::find($cocaCola->id);
echo "   Satış Sonrası SQLite Stok: {$cocaColaAfter->stock_quantity}\n\n";

echo "3. Senkronizasyon Çalıştırılıyor (app:sync-local)...\n";
Illuminate\Support\Facades\Artisan::call('app:sync-local');

// Arka plan PUSH'un tamamlanması için 2 saniye bekle
sleep(2);

echo "4. Senkronizasyon Sonrası Doğrulama:\n";
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$onlineProd2 = collect($r2->json('data.products') ?? [])->firstWhere('sync_uuid', $cocaCola->sync_uuid);

$localFinal = Product::find($cocaCola->id)->stock_quantity;
$onlineFinal = $onlineProd2 ? $onlineProd2['stock_quantity'] : 'N/A';

echo "   SQLite Son Stok: {$localFinal}\n";
echo "   MySQL Online Son Stok: {$onlineFinal}\n\n";

if ((float)$localFinal === (float)$onlineFinal) {
    echo "🎉 TÜRKÇE BAŞARI MESAJI: ÇEVRİMDİŞİ YAPILAN SATIŞ ANINDA CANLI SUNUCUDA DA DÜŞTÜ VE İKİ TARAFTAKİ STOK EŞİTLENDİ!\n";
} else {
    echo "❌ HATA: Stoklar eşleşmiyor!\n";
}

echo "\n=== NİHAİ TEST BİTTİ ===\n";
