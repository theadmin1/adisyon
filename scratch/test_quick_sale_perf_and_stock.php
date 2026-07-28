<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\User;
use App\Services\Checks\CheckService;
use App\Http\Controllers\QuickSaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=== HIZLI SATIŞ PERFORMANS VE STOK SENKRONİZASYON TESTİ ===\n\n";

$product = Product::where('track_stock', true)->where('stock_quantity', '>', 0)->first();
if (!$product) {
    die("Stok takibi yapılan ürün bulunamadı!\n");
}

echo "Seçilen Ürün: {$product->name} (ID: {$product->id}, SyncUUID: {$product->sync_uuid})\n";
echo "Satış Öncesi Yerel (SQLite) Stok: {$product->stock_quantity}\n";

$user = User::first();
$controller = new QuickSaleController();
$checkService = new CheckService();

$request = Request::create('/quick-sale', 'POST', [
    'items' => [
        [
            'product_id' => $product->id,
            'quantity' => 1,
        ]
    ],
    'payment_method' => 'nakit',
    'discount_amount' => 0,
    'send_to_kitchen' => 0,
]);
$request->setUserResolver(fn() => $user);
$request->headers->set('Accept', 'application/json');

$startTime = microtime(true);
$response = $controller->store($request, $checkService);
$endTime = microtime(true);

$durationMs = round(($endTime - $startTime) * 1000, 2);
echo "\n⚡ Hızlı Satış HTTP İsteği Tamamlanma Süresi: {$durationMs} ms\n";
echo "Yanıt: " . $response->getContent() . "\n";

$productFresh = $product->fresh();
echo "\nSatış Sonrası Yerel (SQLite) Stok: {$productFresh->stock_quantity}\n";

echo "\n--- Arka Plan Senkronizasyon Komutu (app:sync-local) Çalıştırılıyor ---\n";
$syncStart = microtime(true);
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$syncEnd = microtime(true);
echo "Senkronizasyon Süresi: " . round(($syncEnd - $syncStart) * 1000, 2) . " ms\n";

echo "\n--- Canlı Sunucu (adisyon.synaptropic.com) Stok Kontrol Ediliyor ---\n";
$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

if ($r->successful()) {
    $remoteProducts = collect($r->json('data.products') ?? []);
    $remoteProd = $remoteProducts->firstWhere('sync_uuid', $product->sync_uuid);
    if ($remoteProd) {
        echo "✅ Canlı Sunucu (MySQL) Ürün Stoğu: {$remoteProd['stock_quantity']} (İsim: {$remoteProd['name']})\n";
    } else {
        echo "❌ Canlı sunucuda ürün bulunamadı!\n";
    }
} else {
    echo "❌ Canlı sunucuya erişilemedi: " . $r->status() . "\n";
}

echo "\n=== TEST TAMAMLANDI ===\n";
