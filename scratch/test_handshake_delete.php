<?php
// Uçtan uca test: Offline silme → PUSH → PULL → Silinen ürün geri gelmemeli

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== UÇTAN UCA SİLME HANDSHAKE TESTİ ===" . PHP_EOL;

// Adım 1: SQLite'a test ürünü oluştur
$testName = 'HANDSHAKE_TEST_URUN_' . rand(1000, 9999);
$testUuid = (string) Str::uuid();

// deleted_records tablosu yoksa oluştur
if (!Schema::connection('sqlite')->hasTable('deleted_records')) {
    Schema::connection('sqlite')->create('deleted_records', function ($t) {
        $t->id();
        $t->string('type');
        $t->string('sync_uuid')->nullable()->index();
        $t->unsignedBigInteger('record_id')->nullable();
        $t->string('name')->nullable();
        $t->boolean('is_synced')->default(false);
        $t->timestamps();
    });
}

$catId = DB::connection('sqlite')->table('categories')->value('id') ?? 1;
DB::connection('sqlite')->table('products')->insert([
    'name' => $testName,
    'slug' => Str::slug($testName),
    'category_id' => $catId,
    'branch_id' => 1,
    'price' => 99.99,
    'sync_uuid' => $testUuid,
    'is_synced' => false,
    'is_active' => true,
    'stock_quantity' => 0,
    'min_stock_level' => 0,
    'unit' => 'adet',
    'track_stock' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);
$localId = DB::connection('sqlite')->table('products')->where('sync_uuid', $testUuid)->value('id');
echo "1. ✅ Test ürünü oluşturuldu: {$testName} (UUID: {$testUuid}, ID: {$localId})" . PHP_EOL;

// Adım 2: PUSH ile online'a gönder
echo "2. ⏳ PUSH çalıştırılıyor (ürünü online'a gönder)..." . PHP_EOL;
Artisan::call('app:sync-local');
echo "   " . Artisan::output() . PHP_EOL;

// Online'da oluştu mu kontrol et
$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value');
$checkUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';
$pullResp = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($checkUrl);

if ($pullResp->successful()) {
    $onlineProducts = collect($pullResp->json('data.products') ?? []);
    $found = $onlineProducts->firstWhere('sync_uuid', $testUuid);
    if ($found) {
        echo "3. ✅ Ürün online MySQL'de oluşturuldu (Online ID: " . ($found['id'] ?? '?') . ")" . PHP_EOL;
    } else {
        // İsimle ara
        $foundByName = $onlineProducts->firstWhere('name', $testName);
        if ($foundByName) {
            echo "3. ✅ Ürün online MySQL'de oluşturuldu (isim eşleşmesiyle, ID: " . ($foundByName['id'] ?? '?') . ")" . PHP_EOL;
        } else {
            echo "3. ⚠️ Ürün online'da bulunamadı — yine de silme testine devam ediliyor." . PHP_EOL;
        }
    }
}

// Adım 3: Offline'da ürünü SİL
echo "4. 🗑️ Ürünü offline SQLite'dan siliniyor..." . PHP_EOL;
$product = DB::connection('sqlite')->table('products')->where('sync_uuid', $testUuid)->first();
if ($product) {
    // deleted_records kaydı yap (Model event tetiklenmeyeceği için manuel)
    DB::connection('sqlite')->table('deleted_records')->insert([
        'type' => 'product',
        'sync_uuid' => $testUuid,
        'record_id' => $product->id,
        'name' => $testName,
        'is_synced' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    // Sonra sil
    DB::connection('sqlite')->table('products')->where('sync_uuid', $testUuid)->delete();
    echo "   ✅ Ürün SQLite'dan silindi ve deleted_records'a kaydedildi." . PHP_EOL;
}

// deleted_records'da var mı?
$delRec = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid', $testUuid)->first();
echo "   deleted_records durumu: sync_uuid={$delRec->sync_uuid}, name={$delRec->name}, is_synced=" . ($delRec->is_synced ? 'true' : 'false') . PHP_EOL;

// Adım 4: Senkronizasyonu çalıştır (PUSH silme mesajını gönderir, PULL verileri çeker)
echo "5. ⏳ Senkronizasyon çalıştırılıyor (PUSH silme + PULL)..." . PHP_EOL;
Artisan::call('app:sync-local');
echo "   " . Artisan::output() . PHP_EOL;

// Adım 5: Kontrol et
echo "6. 🔍 Kontrol ediliyor..." . PHP_EOL;

// SQLite'da geri geldi mi?
$localProduct = DB::connection('sqlite')->table('products')
    ->where(function($q) use ($testUuid, $testName) {
        $q->where('sync_uuid', $testUuid)->orWhere('name', $testName);
    })->first();

if ($localProduct) {
    echo "   ❌ HATA! Ürün SQLite'da geri gelmiş! (id={$localProduct->id}, name={$localProduct->name}, sync_uuid={$localProduct->sync_uuid})" . PHP_EOL;
} else {
    echo "   ✅ Ürün SQLite'da YOK — geri gelmedi!" . PHP_EOL;
}

// Online'da hâlâ var mı?
$pullResp2 = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($checkUrl);

if ($pullResp2->successful()) {
    $onlineProducts2 = collect($pullResp2->json('data.products') ?? []);
    $stillOnline = $onlineProducts2->first(function($p) use ($testUuid, $testName) {
        return ($p['sync_uuid'] ?? '') === $testUuid || ($p['name'] ?? '') === $testName;
    });
    if ($stillOnline) {
        echo "   ❌ HATA! Ürün hâlâ online MySQL'de var! (id={$stillOnline['id']}, name={$stillOnline['name']})" . PHP_EOL;
    } else {
        echo "   ✅ Ürün online MySQL'den de SİLİNDİ!" . PHP_EOL;
    }
}

// deleted_records temizlendi mi?
$delRecAfter = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid', $testUuid)->first();
if ($delRecAfter) {
    echo "   ⚠️ deleted_records kaydı hâlâ var (is_synced=" . ($delRecAfter->is_synced ? 'true' : 'false') . ") — bir sonraki sync'te temizlenecek." . PHP_EOL;
} else {
    echo "   ✅ deleted_records kaydı da temizlendi." . PHP_EOL;
}

// Adım 6: İkinci senkronizasyon — ürünün kesinlikle geri gelmediğini teyit et
echo PHP_EOL . "7. ⏳ İKİNCİ senkronizasyon (ürünün tekrar gelmediğini doğrulama)..." . PHP_EOL;
Artisan::call('app:sync-local');
echo "   " . Artisan::output() . PHP_EOL;

$localProductFinal = DB::connection('sqlite')->table('products')
    ->where(function($q) use ($testUuid, $testName) {
        $q->where('sync_uuid', $testUuid)->orWhere('name', $testName);
    })->first();

if ($localProductFinal) {
    echo "   ❌ BAŞARISIZ! Ürün 2. sync sonrasında GERİ GELDİ! Handshake protokolü çalışmıyor." . PHP_EOL;
} else {
    echo "   🎉 MÜKEMMEL! İki ardışık senkronizasyon sonrasında ürün kesinlikle geri gelmedi. Handshake protokolü ÇALIŞIYOR!" . PHP_EOL;
}

echo PHP_EOL . "=== TEST BİTTİ ===" . PHP_EOL;
