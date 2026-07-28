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

echo "=== FINAL MYSQL DUPLICATE CLEANUP & SYNC VERIFICATION ===\n\n";

// 1. Canlı sunucudaki tüm ürünleri al
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Canlı veriler alınamadı!\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Canlı sunucu toplam ürün sayısı: " . $remoteProducts->count() . "\n";

// Empty sync_uuid olan kayıtları tespit et
$emptyUuidProducts = $remoteProducts->filter(fn($p) => empty($p['sync_uuid']));
echo "Boş sync_uuid'ye sahip ürün sayısı: " . $emptyUuidProducts->count() . "\n";

$deletedPayload = [];
foreach ($emptyUuidProducts as $ep) {
    $deletedPayload[] = [
        'sync_uuid' => '',
        'name' => $ep['name'],
        'record_id' => $ep['id'],
    ];
}

if (!empty($deletedPayload)) {
    echo "Boş sync_uuid'li eski mükerrer ürünler sunucudan siliniyor...\n";
    $delRes = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post($pushUrl, [
        'batch_id' => 'FINAL-CLEAN-' . time(),
        'deleted_products' => $deletedPayload,
    ]);
    echo "Silme HTTP Status: " . $delRes->status() . "\n";
}

// 2. Şimdi SQLite'taki tüm güncel stok ve sync_uuid bilgilerini PUSH et
$sqliteProducts = Product::all();
$pushPayload = [];

foreach ($sqliteProducts as $sp) {
    $pushPayload[] = [
        'id' => $sp->id,
        'sync_uuid' => $sp->sync_uuid,
        'name' => $sp->name,
        'slug' => $sp->slug,
        'price' => (float) $sp->price,
        'stock_quantity' => (float) $sp->stock_quantity,
        'track_stock' => (bool) $sp->track_stock,
        'is_active' => (bool) $sp->is_active,
    ];
}

echo "SQLite'taki " . count($pushPayload) . " ürün güncel stoklarıyla canlı sunucuya PUSH ediliyor...\n";
$pushRes = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'FINAL-PUSH-ALIGN-' . time(),
    'products' => $pushPayload,
]);
echo "PUSH HTTP Status: " . $pushRes->status() . "\n\n";

// 3. Son durumu doğrula
$rFinal = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$finalRemoteProds = collect($rFinal->json('data.products') ?? []);
echo "--- DOĞRULANMIŞ CANLI MYSQL ÜRÜN LİSTESİ ---\n";
foreach ($finalRemoteProds as $fp) {
    $sqProd = $sqliteProducts->firstWhere('sync_uuid', $fp['sync_uuid']);
    $sqStock = $sqProd ? $sqProd->stock_quantity : 'N/A';
    $statusIcon = ($sqProd && (float)$sqProd->stock_quantity === (float)$fp['stock_quantity']) ? '🟢 EŞİT' : '🔴 FARK VAR';
    echo "{$statusIcon} | ID: {$fp['id']} | Name: {$fp['name']} | Local Stock: {$sqStock} | Online Stock: {$fp['stock_quantity']}\n";
}

echo "\n=== TAMAMLANDI ===\n";
