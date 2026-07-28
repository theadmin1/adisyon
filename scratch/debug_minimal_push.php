<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

// Simple payload with just 1 product + 1 stock_movement
$turkKahvesi = DB::connection('sqlite')->table('products')->where('name', 'like', '%Türk Kahvesi%')->first();
$sm = DB::connection('sqlite')->table('stock_movements')
    ->where('product_id', $turkKahvesi->id)
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->first();

echo "=== MINIMAL PUSH TEST ===\n";
echo "Product: {$turkKahvesi->name} | sync_uuid: {$turkKahvesi->sync_uuid}\n";
if ($sm) {
    echo "Stock Movement: {$sm->sync_uuid} | type: {$sm->type} | qty: {$sm->quantity}\n";
}

// Test 1: Push only products
echo "\n--- TEST 1: Products only ---\n";
$r1 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post('https://adisyon.synaptropic.com/api/v1/sync/push', [
    'batch_id' => 'TEST1-' . time(),
    'products' => [[
        'sync_uuid' => $turkKahvesi->sync_uuid,
        'name' => $turkKahvesi->name,
        'stock_quantity' => (float) $turkKahvesi->stock_quantity,
        'price' => (float) $turkKahvesi->price,
        'track_stock' => (bool) $turkKahvesi->track_stock,
        'is_active' => true,
    ]],
]);
echo "Status: {$r1->status()} | Body: " . substr($r1->body(), 0, 500) . "\n";

// Test 2: Push only stock_movements  
if ($sm) {
    echo "\n--- TEST 2: Stock movements only ---\n";
    $r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post('https://adisyon.synaptropic.com/api/v1/sync/push', [
        'batch_id' => 'TEST2-' . time(),
        'stock_movements' => [[
            'sync_uuid' => $sm->sync_uuid,
            'product_id' => $sm->product_id,
            'product_sync_uuid' => $turkKahvesi->sync_uuid,
            'type' => $sm->type,
            'quantity' => (float) $sm->quantity,
            'notes' => $sm->notes,
        ]],
    ]);
    echo "Status: {$r2->status()} | Body: " . substr($r2->body(), 0, 500) . "\n";
}

// Test 3: Push checks with items
echo "\n--- TEST 3: One check ---\n";
$unsyncedCheck = DB::connection('sqlite')->table('checks')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->first();
if ($unsyncedCheck) {
    $items = DB::connection('sqlite')->table('check_items')->where('check_id', $unsyncedCheck->id)->get();
    $itemsPayload = [];
    foreach ($items as $item) {
        $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
        $itemsPayload[] = [
            'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
            'product_id' => (int)$item->product_id,
            'product_sync_uuid' => $pSyncUuid,
            'product_name' => $item->product_name ?? 'Ürün',
            'unit_price' => (float) $item->unit_price,
            'quantity' => (float) $item->quantity,
            'total_price' => (float) $item->total_price,
            'status' => $item->kitchen_status ?? 'pending',
            'is_cancelled' => (bool) ($item->is_cancelled ?? false),
        ];
    }
    
    $r3 = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post('https://adisyon.synaptropic.com/api/v1/sync/push', [
        'batch_id' => 'TEST3-' . time(),
        'checks' => [[
            'sync_uuid' => $unsyncedCheck->sync_uuid,
            'items_complete' => true,
            'dining_table_id' => $unsyncedCheck->dining_table_id ? (int) $unsyncedCheck->dining_table_id : null,
            'waiter_id' => $unsyncedCheck->waiter_id ? (int) $unsyncedCheck->waiter_id : null,
            'staff_profile_id' => $unsyncedCheck->waiter_id ? (int) $unsyncedCheck->waiter_id : null,
            'check_number' => $unsyncedCheck->check_number,
            'subtotal' => (float) ($unsyncedCheck->subtotal ?? $unsyncedCheck->total),
            'discount_total' => (float) ($unsyncedCheck->discount_total ?? 0),
            'total' => (float) $unsyncedCheck->total,
            'total_amount' => (float) $unsyncedCheck->total,
            'discount_amount' => (float) ($unsyncedCheck->discount_total ?? 0),
            'status' => $unsyncedCheck->status,
            'created_at' => $unsyncedCheck->created_at ?? (string) now(),
            'items' => $itemsPayload,
        ]],
    ]);
    echo "Check: {$unsyncedCheck->check_number} | Items: " . count($itemsPayload) . "\n";
    echo "Status: {$r3->status()} | Body: " . substr($r3->body(), 0, 500) . "\n";
}
