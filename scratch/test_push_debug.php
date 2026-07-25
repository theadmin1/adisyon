<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$unsyncedChecks = DB::connection('sqlite')->table('checks')->where('is_synced', 0)->orWhere('is_synced', false)->orWhereNull('is_synced')->get();

echo "Unsynced checks count in SQLite: " . $unsyncedChecks->count() . "\n";

$checksPayload = [];
foreach ($unsyncedChecks as $check) {
    $items = DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get();
    $itemsPayload = [];
    foreach ($items as $item) {
        $itemsPayload[] = [
            'sync_uuid' => $item->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
            'product_id' => $item->product_id,
            'product_name' => $item->product_name ?? 'Ürün',
            'unit_price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'total_price' => (float) $item->total_price,
            'status' => $item->kitchen_status ?? 'pending',
        ];
    }

    $checksPayload[] = [
        'sync_uuid' => $check->sync_uuid ?? (string) \Illuminate\Support\Str::uuid(),
        'dining_table_id' => $check->dining_table_id,
        'user_id' => $check->user_id ?? null,
        'waiter_id' => $check->waiter_id,
        'staff_profile_id' => $check->waiter_id,
        'check_number' => $check->check_number,
        'subtotal' => (float) ($check->subtotal ?? $check->total),
        'discount_total' => (float) ($check->discount_total ?? 0),
        'total' => (float) $check->total,
        'total_amount' => (float) $check->total,
        'status' => $check->status,
        'created_at' => (string) ($check->created_at ?? now()),
        'items' => $itemsPayload,
    ];
}

$res = Http::withHeaders(['X-Device-Api-Key' => $apiKey])->post('https://adisyon.synaptropic.com/api/v1/sync/push', [
    'batch_id' => 'BATCH-' . time(),
    'checks' => $checksPayload,
    'payments' => [],
    'stock_movements' => [],
]);

echo "PUSH HTTP STATUS: " . $res->status() . "\n";
echo "PUSH RAW BODY:\n" . $res->body() . "\n";
