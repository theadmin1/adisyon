<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

echo "=== OFFLINE -> ONLINE PUSH TESTİ ===\n";

$testUuid = (string) Str::uuid();
$testName = "Test Ürün " . rand(1000, 9999);

echo "1. Yerel SQLite'a senkronize edilmemiş ürün ekleniyor (sync_uuid: {$testUuid}, name: {$testName})...\n";

$catId = DB::connection('sqlite')->table('categories')->value('id') ?? 1;

DB::connection('sqlite')->table('products')->insert([
    'category_id' => $catId,
    'branch_id' => 1,
    'name' => $testName,
    'slug' => Str::slug($testName),
    'price' => 99.90,
    'sync_uuid' => $testUuid,
    'is_synced' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "2. php artisan app:sync-local çalıştırılıyor...\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo \Illuminate\Support\Facades\Artisan::output() . "\n";

echo "3. Canlı MySQL sunucusunda ürün aranıyor (https://adisyon.synaptropic.com)...\n";
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

if ($r->successful()) {
    $data = $r->json('data.products') ?? [];
    $found = collect($data)->firstWhere('sync_uuid', $testUuid);
    if ($found) {
        echo "✅ BAŞARILI! Ürün canlı MySQL sunucusuna PUSH edildi ve bulundu: ID={$found['id']}, Name={$found['name']}\n";
    } else {
        echo "❌ HATA! Ürün canlı sunucuda bulunamadı! Toplam gelen ürün sayısı: " . count($data) . "\n";
    }
} else {
    echo "❌ HATA! Canlı sunucudan pull verisi alınamadı (HTTP {$r->status()})\n";
}
