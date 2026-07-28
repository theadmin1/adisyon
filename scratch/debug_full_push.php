<?php
/**
 * Simüle: pushUnsyncedLocalDataToCloud'un tam bir PUSH yapıp
 * synced_uuids vs local stock_movements karşılaştırmasını yap
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

// Unsynced stock movements
$unsyncedSM = DB::connection('sqlite')->table('stock_movements')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();

echo "=== UNSYNCED STOCK MOVEMENTS sync_uuids ===\n";
foreach ($unsyncedSM as $sm) {
    echo "  SM ID:{$sm->id} | sync_uuid: {$sm->sync_uuid} | product_id: {$sm->product_id}\n";
}

// Simulate PUSH: build full payload
$unsyncedCheckIdsWithItems = DB::connection('sqlite')->table('check_items')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->pluck('check_id')->filter()->toArray();

$unsyncedChecks = DB::connection('sqlite')->table('checks')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced')->orWhereIn('id', $unsyncedCheckIdsWithItems))
    ->get();

$unsyncedCheckItems = DB::connection('sqlite')->table('check_items')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();

$unsyncedPayments = DB::connection('sqlite')->table('payments')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();

$unsyncedProducts = DB::connection('sqlite')->table('products')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();

// Add stock products
if ($unsyncedSM->isNotEmpty()) {
    $stockProductIds = $unsyncedSM->pluck('product_id')->filter()->unique()->toArray();
    if (!empty($stockProductIds)) {
        $extraProducts = DB::connection('sqlite')->table('products')->whereIn('id', $stockProductIds)->get();
        $existingProductIds = $unsyncedProducts->pluck('id')->toArray();
        foreach ($extraProducts as $ep) {
            if (!in_array($ep->id, $existingProductIds, true)) {
                $unsyncedProducts->push($ep);
            }
        }
    }
}

// Build checks payload
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
        'waiter_id' => $check->waiter_id,
        'staff_profile_id' => $check->waiter_id,
        'check_number' => $check->check_number,
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

// Build check_items payload  
$checkItemsPayload = [];
foreach ($unsyncedCheckItems as $item) {
    $checkSyncUuid = DB::connection('sqlite')->table('checks')->where('id', $item->check_id)->value('sync_uuid');
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
    $checkItemsPayload[] = [
        'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'check_sync_uuid' => $checkSyncUuid,
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

// Build payments payload
$paymentsPayload = [];
foreach ($unsyncedPayments as $payment) {
    $checkSyncUuid = $payment->check_id ? DB::connection('sqlite')->table('checks')->where('id', $payment->check_id)->value('sync_uuid') : null;
    $paymentsPayload[] = [
        'sync_uuid' => $payment->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'check_sync_uuid' => $checkSyncUuid,
        'amount' => (float) $payment->amount,
        'payment_method' => $payment->payment_method ?? 'cash',
        'created_at' => $payment->created_at ?? (string) now(),
    ];
}

// Build stock movements payload
$stockPayload = [];
foreach ($unsyncedSM as $stock) {
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('sync_uuid');
    $stockPayload[] = [
        'sync_uuid' => $stock->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'product_id' => $stock->product_id,
        'product_sync_uuid' => $pSyncUuid,
        'type' => $stock->type,
        'quantity' => (float) $stock->quantity,
        'notes' => $stock->notes ?? null,
    ];
}

// Build products payload
$productsPayload = [];
foreach ($unsyncedProducts as $prod) {
    $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
    $productsPayload[] = [
        'id' => $prod->id,
        'sync_uuid' => $prod->sync_uuid,
        'category_id' => $prod->category_id,
        'category_sync_uuid' => $categorySyncUuid,
        'name' => $prod->name,
        'slug' => $prod->slug ?? \Illuminate\Support\Str::slug($prod->name),
        'sku' => $prod->sku ?? null,
        'price' => (float) $prod->price,
        'discounted_price' => $prod->discounted_price ? (float) $prod->discounted_price : null,
        'stock_quantity' => (float) ($prod->stock_quantity ?? 0),
        'min_stock_level' => (float) ($prod->min_stock_level ?? 0),
        'unit' => $prod->unit ?? 'adet',
        'track_stock' => (bool) ($prod->track_stock ?? false),
        'description' => $prod->description ?? null,
        'kitchen_department' => $prod->kitchen_department ?? null,
        'is_active' => (bool) ($prod->is_active ?? true),
    ];
}

$fullPayload = [
    'batch_id' => 'DEBUG-FULL-' . time(),
    'checks' => $checksPayload,
    'check_items' => $checkItemsPayload,
    'payments' => $paymentsPayload,
    'stock_movements' => $stockPayload,
    'categories' => [],
    'products' => $productsPayload,
    'deleted_products' => [],
    'deleted_categories' => [],
];

echo "\n=== FULL PUSH PAYLOAD SUMMARY ===\n";
echo "  checks: " . count($checksPayload) . "\n";
echo "  check_items: " . count($checkItemsPayload) . "\n";
echo "  payments: " . count($paymentsPayload) . "\n";
echo "  stock_movements: " . count($stockPayload) . "\n";
echo "  products: " . count($productsPayload) . "\n";

echo "\n=== SENDING PUSH ===\n";
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$response = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $fullPayload);

echo "Response Status: " . $response->status() . "\n";
$responseData = $response->json();
echo "Success: " . var_export($responseData['success'] ?? false, true) . "\n";
$syncedUuids = $responseData['synced_uuids'] ?? [];
echo "Synced UUIDs count: " . count($syncedUuids) . "\n";

// Check which local stock_movements got synced
echo "\n=== STOCK MOVEMENT UUID MATCHING ===\n";
$smUuids = $unsyncedSM->pluck('sync_uuid')->toArray();
$matchedSM = array_intersect($smUuids, $syncedUuids);
$unmatchedSM = array_diff($smUuids, $syncedUuids);
echo "  Matched (in synced_uuids): " . count($matchedSM) . "\n";
echo "  NOT matched: " . count($unmatchedSM) . "\n";
foreach ($unmatchedSM as $uuid) {
    echo "    ❌ UNMATCHED SM: $uuid\n";
}

// Check product UUIDs
echo "\n=== PRODUCT UUID MATCHING ===\n";
$prodUuids = $unsyncedProducts->pluck('sync_uuid')->toArray();
$matchedProd = array_intersect($prodUuids, $syncedUuids);
$unmatchedProd = array_diff($prodUuids, $syncedUuids);
echo "  Matched (in synced_uuids): " . count($matchedProd) . "\n";
echo "  NOT matched: " . count($unmatchedProd) . "\n";
foreach ($unmatchedProd as $uuid) {
    echo "    ❌ UNMATCHED PRODUCT: $uuid\n";
}

echo "\n=== ALL synced_uuids ===\n";
foreach ($syncedUuids as $u) {
    echo "  $u\n";
}
