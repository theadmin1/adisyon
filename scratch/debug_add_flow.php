<?php
// Masa 7'ye (boş) gerçek CheckService akışıyla adisyon+kalem ekler,
// sonra app:sync-local'ın yaptığının aynısını adım adım izler.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

// 1. Gerçek akışla check + kalem ekle
$table = \App\Models\DiningTable::find(7);
$svc = new \App\Services\Checks\CheckService();
$check = $svc->openCheck($table);
$svc->addItems($check, [['product_id' => 18, 'quantity' => 1]]); // dede
$check = $check->fresh('items');
echo "1) YEREL: check{$check->id} uuid=" . substr($check->sync_uuid,0,8) . " total={$check->total} synced=" . ($check->is_synced?1:0) . "\n";
foreach ($check->items as $i) {
    echo "   item{$i->id} {$i->product_name} c=" . ($i->is_cancelled?1:0) . " s=" . ($i->is_synced?1:0) . " uuid=" . substr($i->sync_uuid,0,8) . "\n";
}

// 2. pushUnsynced mantığıyla payload üret
$itemsPayload = [];
foreach (DB::connection('sqlite')->table('check_items')->where('check_id', $check->id)->get() as $item) {
    $itemsPayload[] = [
        'sync_uuid' => $item->sync_uuid,
        'product_id' => $item->product_id,
        'product_sync_uuid' => DB::connection('sqlite')->table('products')->where('id', $item->product_id)->value('sync_uuid'),
        'product_name' => $item->product_name,
        'unit_price' => (float) $item->unit_price,
        'quantity' => (float) $item->quantity,
        'total_price' => (float) $item->total_price,
        'status' => $item->kitchen_status ?? 'pending',
        'is_cancelled' => (bool) ($item->is_cancelled ?? false),
    ];
}
$payload = [
    'batch_id' => 'DEBUG-ADD-' . time(),
    'checks' => [[
        'sync_uuid' => $check->sync_uuid,
        'dining_table_id' => $check->dining_table_id,
        'waiter_id' => $check->waiter_id,
        'check_number' => $check->check_number,
        'subtotal' => (float) $check->subtotal,
        'total' => (float) $check->total,
        'total_amount' => (float) $check->total,
        'discount_amount' => 0,
        'status' => 'open',
        'created_at' => (string) $check->created_at,
        'items' => $itemsPayload,
    ]],
];
echo "\n2) GÖNDERİLEN item JSON: " . json_encode($itemsPayload, JSON_UNESCAPED_UNICODE) . "\n";

// 3. Gönder
$r = Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$apiKey,'Accept'=>'application/json'])->post('https://adisyon.synaptropic.com/api/v1/sync/push', $payload);
echo "\n3) YANIT HTTP {$r->status()}: " . substr($r->body(), 0, 400) . "\n";

// 4. Sunucuda ne oluştu?
$r2 = Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$apiKey,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$srv = collect($r2->json('data.checks'))->firstWhere('sync_uuid', $check->sync_uuid);
if ($srv) {
    echo "\n4) SUNUCU: check{$srv['id']} total={$srv['total']} items=[" . collect($srv['items']??[])->map(fn($i)=>$i['product_name'].'(tp='.$i['total_price'].')')->implode(', ') . "]\n";
    echo "SONUÇ: " . (count($srv['items']??[])>0 ? "✅ kalem sunucuda oluştu" : "❌ KALEM SUNUCUDA YOK — push item yaratmıyor!") . "\n";
} else {
    echo "\n4) SUNUCU: check hiç oluşmadı ❌\n";
}
