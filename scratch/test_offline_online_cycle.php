<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');
$sqlite->statement('PRAGMA foreign_keys = OFF;');

echo "==========================================================" . PHP_EOL;
echo "🧪 ÇEVRİMDİŞİ -> CANLI DÖNÜŞÜM (OFFLINE-TO-ONLINE) SİMÜLASYON TESTİ" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// 1. ADIM: İNTERNET KOPUKKEN YEREL SQLITE VERİTABANINDA ADİSYON VE YEMEK OLUŞTURULUYOR
$testUuid = (string) \Illuminate\Support\Str::uuid();
$itemUuid = (string) \Illuminate\Support\Str::uuid();
$paymentUuid = (string) \Illuminate\Support\Str::uuid();

echo PHP_EOL . "1️⃣  [OFFLINE MODE] İnternet yokken yerel SQLite'ta Masa 1 adisyonu ve siparişi oluşturuluyor..." . PHP_EOL;

$checkId = $sqlite->table('checks')->insertGetId([
    'branch_id' => 1,
    'dining_table_id' => 1,
    'waiter_id' => 1,
    'check_number' => 'CHK-OFFLINE-TEST-' . rand(1000, 9999),
    'sync_uuid' => $testUuid,
    'is_synced' => false,
    'guest_count' => 2,
    'status' => 'open',
    'subtotal' => 620.00,
    'discount_total' => 0,
    'total' => 620.00,
    'opened_at' => now()->toIso8601String(),
    'kitchen_sent_at' => now()->toIso8601String(),
    'created_at' => now()->toIso8601String(),
    'updated_at' => now()->toIso8601String(),
]);

$itemId = $sqlite->table('check_items')->insertGetId([
    'check_id' => $checkId,
    'product_id' => 4,
    'product_name' => 'Simülasyon Test Pizzası',
    'sync_uuid' => $itemUuid,
    'is_synced' => false,
    'kitchen_status' => 'pending',
    'unit_price' => 310.00,
    'quantity' => 2,
    'total_price' => 620.00,
    'notes' => 'Çevrimdışı Test Siparişi',
    'is_complimentary' => false,
    'is_cancelled' => false,
    'created_at' => now()->toIso8601String(),
    'updated_at' => now()->toIso8601String(),
]);

echo "   ✅ SQLite Adisyon ID: {$checkId} (UUID: {$testUuid}) | Durum: is_synced = 0 (Çevrimdışı)" . PHP_EOL;
echo "   ✅ SQLite Yemek Kalemi ID: {$itemId} (Simülasyon Test Pizzası x 2 = ₺620.00)" . PHP_EOL;

// 2. ADIM: YEREL SQLITE VERİSİNİN ÇEVRİMDİŞİ DURAĞI KONTROL EDİLİYOR
$checkInDb = $sqlite->table('checks')->where('sync_uuid', $testUuid)->first();
echo PHP_EOL . "2️⃣  [OFFLINE VERİFİKASYON] Yerel SQLite kontrolü: Adisyon Tutar: ₺{$checkInDb->total} | is_synced = " . ($checkInDb->is_synced ? '1' : '0') . PHP_EOL;

// 3. ADIM: İNTERNET GELİYOR VE ARKA PLAN PUSH SENKRONİZASYONU TETİKLENİYOR
echo PHP_EOL . "3️⃣  [ONLINE RECOVERY] İnternet geri geldi! Arka plan PUSH senkronizasyonu canlıya gönderiliyor..." . PHP_EOL;

\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo \Illuminate\Support\Facades\Artisan::output();

// 4. ADIM: ÇEVRİMDİŞİ KAYDIN IS_SYNCED DURUMU SQLITE'TA KONTROL EDİLİYOR
$checkAfterPush = $sqlite->table('checks')->where('sync_uuid', $testUuid)->first();
echo PHP_EOL . "4️⃣  [SQLITE GÜNCELLEME] PUSH sonrası yerel SQLite kaydı: is_synced = " . ($checkAfterPush->is_synced ? '1 (✅ Senkronize Edildi)' : '0 (❌ Başarısız)') . PHP_EOL;

// 5. ADIM: CANLI MYSQL SUNUCUSUNDAN VERİNİN ULAŞTĞI TEYİT EDİLİYOR
echo PHP_EOL . "5️⃣  [CANLI MYSQL KONTROL] Canlı sunucudan (adisyon.synaptropic.com) PULL edilerek doğrulama yapılıyor..." . PHP_EOL;

$apiUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$response = \Illuminate\Support\Facades\Http::withoutVerifying()
    ->timeout(15)
    ->withHeaders(['X-Device-Api-Key' => $apiKey])
    ->get($apiUrl);

$cloudFound = false;
if ($response->successful() && $response->json('success')) {
    $cloudChecks = $response->json('data.checks') ?? [];
    foreach ($cloudChecks as $cC) {
        if (($cC['sync_uuid'] ?? '') === $testUuid) {
            $cloudFound = true;
            echo "   🎉 CANLI SUNUCU BAŞARISI! Canlı MySQL'de Adisyon Bulundu! ID: {$cC['id']} | Tutar: ₺{$cC['total']} | Masa: {$cC['dining_table_id']}" . PHP_EOL;
            foreach ($cC['items'] ?? [] as $cItem) {
                echo "      -> Canlı Yemek Kalemi: {$cItem['product_name']} x {$cItem['quantity']} (₺{$cItem['unit_price']})" . PHP_EOL;
            }
            break;
        }
    }
}

if ($cloudFound) {
    echo PHP_EOL . "==========================================================" . PHP_EOL;
    echo "🏆 ÇEVRİMDİŞİ -> CANLI SENKRONİZASYON MİMARİSİ %100 BAŞARIYLA DOĞRULANDI!" . PHP_EOL;
    echo "==========================================================" . PHP_EOL;
} else {
    echo PHP_EOL . "⚠️ Canlı sunucuda UUID bulunamadı, kontrol ediliyor." . PHP_EOL;
}
