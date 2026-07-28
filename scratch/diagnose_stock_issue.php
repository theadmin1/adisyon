<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

echo "=== HIZLI SATIŞ & STOK SENKRONİZASYON TEŞHİS TESTİ ===\n\n";

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

// 1. Ürünü Seç
$product = Product::where('is_active', true)->first();
if (!$product) {
    echo "❌ Ürün bulunamadı!\n";
    exit(1);
}

echo "1. SEÇİLEN ÜRÜN (SQLite):\n";
echo "   ID: {$product->id}\n";
echo "   Name: {$product->name}\n";
echo "   Sync UUID: {$product->sync_uuid}\n";
echo "   Track Stock: " . ($product->track_stock ? 'EVET (true)' : 'HAYIR (false)') . "\n";
echo "   Yerel Stok (SQLite): {$product->stock_quantity}\n";
echo "   Yerel is_synced: " . ($product->is_synced ? 'true' : 'false') . "\n\n";

// 2. Canlı Sunucu (MySQL) Ürün Stoğunu Çek
echo "2. CANLI SUNUCU (MySQL) DURUMU KONTROL EDİLİYOR...\n";
$onlineStockBefore = null;
try {
    $r = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get($pullUrl);

    if ($r->successful()) {
        $onlineProducts = collect($r->json('data.products') ?? []);
        $onProd = $onlineProducts->firstWhere('sync_uuid', $product->sync_uuid);
        if ($onProd) {
            $onlineStockBefore = $onProd['stock_quantity'];
            echo "   ✅ Canlı Sunucu (MySQL) Ürün Bulundu. Online ID: {$onProd['id']}, Online Stok: {$onlineStockBefore}, Track Stock: " . ($onProd['track_stock'] ? 'true' : 'false') . "\n\n";
        } else {
            echo "   ⚠️ Ürün canlı sunucuda sync_uuid ile bulunamadı!\n\n";
        }
    } else {
        echo "   ❌ Canlı API isteği başarısız: HTTP " . $r->status() . "\n\n";
    }
} catch (\Throwable $e) {
    echo "   ❌ Canlı API bağlantı hatası: " . $e->getMessage() . "\n\n";
}

// 3. Hızlı Satış Simülasyonu (1 Adet Satış)
echo "3. HIZLI SATIŞ YAPILIYOR (1 Adet {$product->name})...\n";

if (!$product->track_stock) {
    echo "   ⚠️ Ürünün track_stock değeri false idi. Test için track_stock = true yapılıyor...\n";
    $product->update(['track_stock' => true]);
}

$checkService = app(\App\Services\Checks\CheckService::class);

$req = Illuminate\Http\Request::create('/quicksale', 'POST', [
    'items' => [
        ['product_id' => $product->id, 'quantity' => 1]
    ],
    'payment_method' => 'nakit',
    'send_to_kitchen' => false
]);

$controller = app(\App\Http\Controllers\QuickSaleController::class);
$res = $controller->store($req, $checkService);

echo "   Hızlı Satış Sonucu HTTP Status: " . ($res->getStatusCode() ?? 200) . "\n";

// 4. Satış Sonrası SQLite Durumu
$productAfterSale = Product::find($product->id);
$latestMovement = StockMovement::where('product_id', $product->id)->latest()->first();

echo "\n4. SATIŞ SONRASI YEREL (SQLite) DURUMU:\n";
echo "   Yerel Stok (SQLite): {$productAfterSale->stock_quantity}\n";
echo "   Yerel is_synced: " . ($productAfterSale->is_synced ? 'true' : 'false') . "\n";
if ($latestMovement) {
    echo "   Son Stok Hareketi: Type={$latestMovement->type}, Qty={$latestMovement->quantity}, is_synced=" . ($latestMovement->is_synced ? 'true' : 'false') . ", sync_uuid={$latestMovement->sync_uuid}\n";
} else {
    echo "   ⚠️ Stok hareketi oluşmadı!\n";
}

// 5. Senkronizasyonu Çalıştır (PUSH + PULL)
echo "\n5. SENKRONİZASYON ÇALIŞTIRILIYOR (app:sync-local)...\n";
Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo "   " . trim(Illuminate\Support\Facades\Artisan::output()) . "\n\n";

// 6. Senkronizasyon Sonrası Kontrol (SQLite ve MySQL)
$productAfterSync = Product::find($product->id);
echo "6. SENKRONİZASYON SONRASI YEREL (SQLite) DURUMU:\n";
echo "   Yerel Stok (SQLite): {$productAfterSync->stock_quantity}\n";
echo "   Yerel is_synced: " . ($productAfterSync->is_synced ? 'true' : 'false') . "\n\n";

echo "7. SENKRONİZASYON SONRASI CANLI SUNUCU (MySQL) KONTROLÜ...\n";
try {
    $r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get($pullUrl);

    if ($r2->successful()) {
        $onlineProducts2 = collect($r2->json('data.products') ?? []);
        $onProd2 = $onlineProducts2->firstWhere('sync_uuid', $product->sync_uuid);
        if ($onProd2) {
            echo "   ✅ Canlı Sunucu (MySQL) Ürün Stoğu: " . $onProd2['stock_quantity'] . " (Önceki: {$onlineStockBefore})\n";
            $diff = $onlineStockBefore !== null ? ($onlineStockBefore - $onProd2['stock_quantity']) : null;
            if ($diff !== null) {
                echo "   📊 Canlı Sunucu Stok Değişimi: " . ($diff > 0 ? "-{$diff} (BAŞARILI DÜŞTÜ)" : "{$diff} (DÜŞMEDİ!)") . "\n";
            }
        } else {
            echo "   ⚠️ Ürün canlı sunucuda bulunamadı!\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ❌ Canlı API bağlantı hatası: " . $e->getMessage() . "\n";
}

echo "\n=== TEŞHİS TESTİ BİTTİ ===\n";
