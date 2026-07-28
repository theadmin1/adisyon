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

echo "=== DÜZELTME & CANLI SUNUCU EŞLEŞTİRME TESTİ ===\n\n";

// 1. Canlı sunucudaki ürünleri al
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Canlı sunucudan veri çekilemedi: HTTP " . $r->status() . "\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Canlı sunucudaki ürün sayısı: " . $remoteProducts->count() . "\n";

$productsToPush = [];
foreach ($remoteProducts as $rp) {
    $name = $rp['name'];
    $remoteId = $rp['id'];
    $remoteUuid = $rp['sync_uuid'];

    $localProd = Product::where('name', $name)->first();
    if ($localProd) {
        // Canlı sunucudaki ürüne SQLite'taki sync_uuid'yi atıyoruz
        $productsToPush[] = [
            'id' => $remoteId,
            'sync_uuid' => $localProd->sync_uuid,
            'name' => $localProd->name,
            'price' => (float) $localProd->price,
            'stock_quantity' => (float) $localProd->stock_quantity,
            'min_stock_level' => (float) $localProd->min_stock_level,
            'unit' => $localProd->unit ?? 'adet',
            'track_stock' => (bool) $localProd->track_stock,
            'is_active' => (bool) $localProd->is_active,
        ];
    }
}

echo "Canlı sunucuya aktarılacak eşleştirilmiş ürün sayısı: " . count($productsToPush) . "\n";

$pushRes = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'SYNC-STOCK-FIX-' . time(),
    'products' => $productsToPush,
]);

echo "PUSH HTTP Status: " . $pushRes->status() . "\n";
echo "PUSH Response: " . $pushRes->body() . "\n\n";

// 2. Yeniden Canlı Sunucu Ürünlerini Çek ve Doğrula
echo "2. YENİDEN CANLI SUNUCU ÜRÜNLERİ KONTROL EDİLİYOR...\n";
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if ($r2->successful()) {
    $remoteProducts2 = collect($r2->json('data.products') ?? []);
    foreach ($remoteProducts2 as $op) {
        echo "ID: {$op['id']} | Name: {$op['name']} | SyncUUID: '{$op['sync_uuid']}' | Stock: {$op['stock_quantity']} | TrackStock: " . ($op['track_stock'] ? 'true' : 'false') . "\n";
    }
}

echo "\n=== TEST TAMAMLANDI ===\n";
