<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== SATIŞ & SENKRONİZASYON ADIM ADIM CANLI KONTROL ===\n\n";

$product = Product::where('name', 'like', '%Pizza%')->first();
if (!$product) {
    echo "❌ Ürün bulunamadı!\n";
    exit(1);
}

echo "1. ÜRÜN BİLGİLERİ:\n";
echo "   Name: {$product->name}\n";
echo "   Sync UUID: {$product->sync_uuid}\n";
echo "   SQLite Stok (Satış Öncesi): {$product->stock_quantity}\n";

$rBefore = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$onProdBefore = collect($rBefore->json('data.products') ?? [])->firstWhere('sync_uuid', $product->sync_uuid);
echo "   MySQL Online Stok (Satış Öncesi): " . ($onProdBefore ? $onProdBefore['stock_quantity'] : 'N/A') . "\n\n";

echo "2. 1 ADET SATIŞ YAPILIYOR...\n";
$checkService = app(\App\Services\Checks\CheckService::class);
$req = Illuminate\Http\Request::create('/quicksale', 'POST', [
    'items' => [['product_id' => $product->id, 'quantity' => 1]],
    'payment_method' => 'nakit',
    'send_to_kitchen' => false
]);
$controller = app(\App\Http\Controllers\QuickSaleController::class);
$controller->store($req, $checkService);

$pAfterSale = Product::find($product->id);
echo "   SQLite Stok (Satış Sonrası): {$pAfterSale->stock_quantity}\n";
echo "   SQLite is_synced (Satış Sonrası): " . ($pAfterSale->is_synced ? 'true' : 'false') . "\n\n";

echo "3. ARKA PLAN SENKRONİZASYONUNUN (AutoSyncService) TAMAMLANIŞI BEKLENİYOR (4 saniye)...\n";
sleep(4);

$pFinal = Product::find($product->id);
$rAfter = Http::withoutVerifying()->timeout(15)->withHeaders(['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'])->get($pullUrl);
$onProdAfter = collect($rAfter->json('data.products') ?? [])->firstWhere('sync_uuid', $product->sync_uuid);

echo "4. ARKA PLAN SENKRONİZASYON SONRASI CANLI DURUM:\n";
echo "   SQLite Son Stok: {$pFinal->stock_quantity}\n";
echo "   SQLite is_synced: " . ($pFinal->is_synced ? 'true (BAŞARILI)' : 'false (BEKLİYOR)') . "\n";
echo "   MySQL Online Son Stok: " . ($onProdAfter ? $onProdAfter['stock_quantity'] : 'N/A') . "\n\n";

if ((float)$pFinal->stock_quantity === (float)$onProdAfter['stock_quantity']) {
    echo "🎉 TÜRKÇE BAŞARI: ÇEVRİMDİŞİ YAPILAN SATIŞ ARKA PLAN SENKRONİZASYONU İLE CANLI MYSQL SUNUCUSUNDA DA BAŞARIYLA DÜŞTÜ VE EŞİTLENDİ!\n";
} else {
    echo "❌ HATA: STOKLAR UYUŞMUYOR!\n";
}
