<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';

$unsyncedStockMovements = DB::connection('sqlite')->table('stock_movements')->where('is_synced', 0)->get();
echo "Unsynced Stock Movements count in SQLite: " . $unsyncedStockMovements->count() . "\n";

$stockPayload = [];
foreach ($unsyncedStockMovements as $stock) {
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('sync_uuid');
    $pName = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('name');
    $stockPayload[] = [
        'sync_uuid' => $stock->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $stock->product_id,
        'product_sync_uuid' => $pSyncUuid,
        'product_name' => $pName,
        'type' => $stock->type,
        'quantity' => (float) $stock->quantity,
        'notes' => $stock->notes ?? null,
    ];
}

$payload = [
    'batch_id' => 'DEBUG-STOCK-MOVEMENTS-' . time(),
    'stock_movements' => $stockPayload,
];

echo "Sending Payload to " . $pushUrl . ":\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "HTTP Status: " . $r->status() . "\n";
echo "Response Body: " . $r->body() . "\n";
