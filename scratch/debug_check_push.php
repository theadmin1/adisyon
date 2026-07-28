<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$unsyncedChecks = DB::connection('sqlite')->table('checks')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();

echo "Unsynced Checks count: " . $unsyncedChecks->count() . "\n";
foreach ($unsyncedChecks as $c) {
    echo "  - Check ID: {$c->id} | CheckNumber: {$c->check_number} | TableID: {$c->dining_table_id} | is_synced: " . var_export($c->is_synced, true) . "\n";
}

$checksPayload = [];
foreach ($unsyncedChecks as $check) {
    $items = DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get();
    $itemsPayload = [];
    foreach ($items as $item) {
        $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
        $itemsPayload[] = [
            'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
            'product_id' => $item->product_id,
            'product_sync_uuid' => $pSyncUuid,
            'product_name' => $item->product_name ?? 'Ürün',
            'unit_price' => (float) $item->unit_price,
            'quantity' => (float) $item->quantity,
            'total_price' => (float) $item->total_price,
            'status' => $item->kitchen_status ?? 'pending',
            'is_cancelled' => (bool) ($item->is_cancelled ?? false),
        ];
    }

    $checksPayload[] = [
        'sync_uuid' => $check->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'items_complete' => true,
        'dining_table_id' => $check->dining_table_id,
        'user_id' => null,
        'waiter_id' => null,
        'staff_profile_id' => null,
        'check_number' => $check->check_number ?? null,
        'subtotal' => (float) ($check->subtotal ?? $check->total),
        'discount_total' => (float) ($check->discount_total ?? 0),
        'total' => (float) $check->total,
        'total_amount' => (float) $check->total,
        'discount_amount' => (float) ($check->discount_total ?? 0),
        'status' => $check->status,
        'created_at' => $check->created_at ?? (string) now(),
        'items' => $itemsPayload,
    ];
}

$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$response = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, [
    'batch_id' => 'DEBUG-CHECKS-' . time(),
    'checks' => $checksPayload,
]);

echo "PUSH Status: " . $response->status() . "\n";
echo "PUSH Body: " . $response->body() . "\n";
