<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

echo "=== OFFLINE -> ONLINE SİLME (DELETE) SYNC TESTİ ===\n";

// 1. Yeni bir test ürünü oluştur
$testUuid = (string) Str::uuid();
$testName = "Silinecek Ürün " . rand(1000, 9999);
$catId = DB::connection('sqlite')->table('categories')->value('id') ?? 1;

echo "1. Ürün SQLite'a ekleniyor: {$testName} (UUID: {$testUuid})...\n";
DB::connection('sqlite')->table('products')->insert([
    'category_id' => $catId,
    'branch_id' => 1,
    'name' => $testName,
    'slug' => Str::slug($testName),
    'price' => 50.00,
    'sync_uuid' => $testUuid,
    'is_synced' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "2. Senkronizasyon ile canlı sunucuya aktarılıyor...\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');

// 3. Canlı sunucuda ürünün oluştuğunu doğrula
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r1 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

$data1 = $r1->json('data.products') ?? [];
$onlineProd1 = collect($data1)->firstWhere('sync_uuid', $testUuid);

if (!$onlineProd1) {
    echo "❌ HATA! Ürün canlı sunucuya eklenemedi!\n";
    exit(1);
}
echo "✅ Ürün canlı sunucuda oluşturuldu (Online ID: {$onlineProd1['id']}).\n";

echo "4. Ürün yerel SQLite'da siliniyor ve deleted_records kaydediliyor...\n";
$localProd = \App\Models\Product::where('sync_uuid', $testUuid)->first();
if ($localProd) {
    DB::connection('sqlite')->table('deleted_records')->insert([
        'type' => 'product',
        'sync_uuid' => $testUuid,
        'is_synced' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $localProd->delete();
}

echo "5. Senkronizasyon tekrar çalıştırılıyor (silme PUSH ve PULL testi)...\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');

echo "6. Canlı sunucu ve yerel veritabanı kontrol ediliyor...\n";
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

$data2 = $r2->json('data.products') ?? [];
$onlineProd2 = collect($data2)->firstWhere('sync_uuid', $testUuid);
$localProd2 = DB::connection('sqlite')->table('products')->where('sync_uuid', $testUuid)->first();

if (!$onlineProd2 && !$localProd2) {
    echo "🎉 MÜKEMMEL! Ürün hem canlı MySQL sunucusundan hem de yerel SQLite'dan kalıcı olarak SİLİNDİ! Geri gelmedi!\n";
} else {
    echo "❌ HATA! Online mevcudiyeti: " . ($onlineProd2 ? 'VAR (Silinmedi!)' : 'YOK') . " | Local mevcudiyeti: " . ($localProd2 ? 'VAR (Geri Geldi!)' : 'YOK') . "\n";
}
