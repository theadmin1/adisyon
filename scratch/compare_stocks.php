<?php
/**
 * Mevcut SQLite ve MySQL stok durumunu karşılaştır
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

echo "=== SQLite (Offline) ÜRÜNLER ===\n";
$localProducts = DB::connection('sqlite')->table('products')->get()->keyBy('sync_uuid');
foreach ($localProducts as $p) {
    echo sprintf("  %s | Stock: %s | is_synced: %s\n", str_pad($p->name, 30), $p->stock_quantity, $p->is_synced);
}

echo "\n=== MySQL (Online) ÜRÜNLER ===\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

if ($r->successful()) {
    $remoteProducts = collect($r->json('data.products') ?? []);
    foreach ($remoteProducts as $rp) {
        $localP = $localProducts->get($rp['sync_uuid']);
        $localStock = $localP ? $localP->stock_quantity : 'YOK';
        $match = ($localP && (float)$localStock == (float)$rp['stock_quantity']) ? '✅' : '❌';
        echo sprintf("  %s %s | MySQL: %s | SQLite: %s\n", $match, str_pad($rp['name'], 28), $rp['stock_quantity'], $localStock);
    }
} else {
    echo "  PULL HATASI: HTTP " . $r->status() . "\n";
}

echo "\n=== SENKRONİZE EDİLMEMİŞ KAYITLAR ===\n";
$unsyncedSM = DB::connection('sqlite')->table('stock_movements')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->count();
$unsyncedProducts = DB::connection('sqlite')->table('products')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->count();
$unsyncedChecks = DB::connection('sqlite')->table('checks')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->count();
$unsyncedCI = DB::connection('sqlite')->table('check_items')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->count();
echo "  Stock Movements: {$unsyncedSM}\n";
echo "  Products: {$unsyncedProducts}\n";
echo "  Checks: {$unsyncedChecks}\n";
echo "  Check Items: {$unsyncedCI}\n";

// Show last sync log
echo "\n=== SON SYNC LOGU ===\n";
$lastLog = DB::connection('sqlite')->table('offline_sync_logs')->latest()->first();
if ($lastLog) {
    echo "  Status: {$lastLog->status} | Type: {$lastLog->payload_type} | Time: {$lastLog->synced_at}\n";
    echo "  Details: {$lastLog->details}\n";
    if ($lastLog->error_message) echo "  Error: {$lastLog->error_message}\n";
}
