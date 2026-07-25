<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "==========================================================" . PHP_EOL;
echo "🔍 CANLI (MYSQL) vs YEREL (SQLITE) VERİ KARŞILAŞTIRMA TESTİ" . PHP_EOL;
echo "==========================================================" . PHP_EOL;

// 1. Canlı Sunucudan (MySQL / API) Verileri Çekelim (withoutVerifying kullanarak)
$apiUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$onlineData = [];
try {
    $response = \Illuminate\Support\Facades\Http::withoutVerifying()
        ->timeout(15)
        ->withHeaders(['X-Device-Api-Key' => $apiKey])
        ->get($apiUrl);

    if ($response->successful() && $response->json('success')) {
        $onlineData = $response->json('data') ?? [];
        echo "✅ Canlı Sunucu (adisyon.synaptropic.com) API Bağlantısı BAŞARILI!" . PHP_EOL;
    } else {
        echo "⚠️ Canlı API Yanıtı: Status " . $response->status() . " Body: " . $response->body() . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "❌ Canlı API İsteği Hatası: " . $e->getMessage() . PHP_EOL;
}

// 2. SQLite Master Tablolarını Temizleyelim ve Senkronize Edelim
echo PHP_EOL . "🔄 Yerel SQLite veritabanına indirme (app:sync-local --fresh) tetikleniyor..." . PHP_EOL;
\Illuminate\Support\Facades\Artisan::call('app:sync-local', ['--fresh' => true]);
echo \Illuminate\Support\Facades\Artisan::output();

// 3. Tablo Tablo Karşılaştıralım
$sqlite = \Illuminate\Support\Facades\DB::connection('sqlite');

$tablesToCompare = [
    'categories' => 'categories',
    'products' => 'products',
    'halls' => 'halls',
    'dining_tables' => 'tables',
    'users' => 'users',
    'staff_profiles' => 'staff_profiles',
    'checks' => 'checks',
    'payments' => 'payments',
    'delivery_orders' => 'delivery_orders',
    'delivery_integrations' => 'delivery_integrations',
    'settings' => 'settings',
];

echo PHP_EOL . sprintf("%-25s | %-15s | %-15s | %-10s", "TABLO ADI", "CANLI (ONLINE)", "YEREL (SQLITE)", "DURUM") . PHP_EOL;
echo str_repeat("-", 75) . PHP_EOL;

foreach ($tablesToCompare as $sqliteTable => $apiKeyName) {
    $onlineCount = count($onlineData[$apiKeyName] ?? []);
    
    $sqliteCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::connection('sqlite')->hasTable($sqliteTable)) {
            $sqliteCount = $sqlite->table($sqliteTable)->count();
        }
    } catch (\Throwable $e) {}

    $status = ($onlineCount === $sqliteCount) ? "✅ Birebir" : "❌ UYUŞMUYOR";
    echo sprintf("%-25s | %-15d | %-15d | %-10s", $sqliteTable, $onlineCount, $sqliteCount, $status) . PHP_EOL;
}

echo PHP_EOL . "==========================================================" . PHP_EOL;
