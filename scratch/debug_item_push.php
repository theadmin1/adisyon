<?php
// check46'nın PUSH payload'ını pushUnsyncedLocalDataToCloud ile AYNI mantıkla üretir,
// sunucuya gönderir ve ham yanıtı + sunucudaki sonucu gösterir.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$check = DB::connection('sqlite')->table('checks')->where('id', 46)->first();
$items = DB::connection('sqlite')->table('check_items')->where('check_id', 46)->get();

$itemsPayload = [];
foreach ($items as $item) {
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid');
    $itemsPayload[] = [
        'sync_uuid' => $item->sync_uuid,
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
echo "GÖNDERİLEN items payload:\n" . json_encode($itemsPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$payload = [
    'batch_id' => 'DEBUG-' . time(),
    'checks' => [[
        'sync_uuid' => $check->sync_uuid,
        'dining_table_id' => $check->dining_table_id,
        'user_id' => null,
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
    ]],
];

$r = Http::withoutVerifying()->timeout(20)->withHeaders([
    'X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json',
])->post('https://adisyon.synaptropic.com/api/v1/sync/push', $payload);

echo "\nHTTP " . $r->status() . "\n" . substr($r->body(), 0, 600) . "\n";

// Sunucuda ne oldu?
$r2 = Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$apiKey,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$srv = collect($r2->json('data.checks'))->firstWhere('id', 46);
echo "\nSUNUCU check46 SONRASI: total={$srv['total']} items=[" . collect($srv['items'] ?? [])->map(fn($i) => $i['product_name'])->implode(', ') . "]\n";
