<?php
// Masa 5 (check38): sunucuda olup yerelde olmayan kalıntı kalemleri cancelled-push ile sunucudan temizler.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$hdr = ['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'];

$lc = DB::connection('sqlite')->table('checks')->where('dining_table_id', 5)->where('status', 'open')->first();
$locUuids = DB::connection('sqlite')->table('check_items')->where('check_id', $lc->id)->pluck('sync_uuid')->all();
echo "YEREL check (masa5) uuid=" . substr($lc->sync_uuid,0,8) . " kalemler: " . count($locUuids) . "\n";

$r = Http::withoutVerifying()->timeout(20)->withHeaders($hdr)->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$sc = collect($r->json('data.checks'))->firstWhere('sync_uuid', $lc->sync_uuid);

$orphans = collect($sc['items'] ?? [])->filter(fn($i) => !in_array($i['sync_uuid'] ?? '', $locUuids, true));
echo "SUNUCUDA fazlalık: " . $orphans->map(fn($i) => $i['product_name'] . '(' . substr($i['sync_uuid']??'NULL',0,8) . ')')->implode(', ') . "\n";

if ($orphans->isNotEmpty()) {
    $itemsPayload = $orphans->map(fn($i) => [
        'sync_uuid' => $i['sync_uuid'],
        'product_id' => $i['product_id'] ?? 1,
        'product_name' => $i['product_name'],
        'unit_price' => (float) $i['unit_price'],
        'quantity' => (float) $i['quantity'],
        'total_price' => (float) $i['total_price'],
        'is_cancelled' => true,
    ])->values()->all();

    $resp = Http::withoutVerifying()->timeout(20)->withHeaders($hdr)->post('https://adisyon.synaptropic.com/api/v1/sync/push', [
        'batch_id' => 'FIX-MASA5-' . time(),
        'checks' => [[
            'sync_uuid' => $lc->sync_uuid,
            'dining_table_id' => 5,
            'total_amount' => (float) $lc->total,
            'status' => 'open',
            'items' => $itemsPayload,
        ]],
    ]);
    echo "temizlik push: HTTP {$resp->status()} success=" . json_encode($resp->json('success')) . "\n";
}

// tam sync + son karşılaştırma
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$r2 = Http::withoutVerifying()->timeout(20)->withHeaders($hdr)->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$srvChecks = collect($r2->json('data.checks'))->where('status', 'open')->keyBy('sync_uuid');
$locChecks = DB::connection('sqlite')->table('checks')->where('status', 'open')->get();
$allOk = true;
foreach ($locChecks as $c) {
    $s = $srvChecks->get($c->sync_uuid);
    $li = DB::connection('sqlite')->table('check_items')->where('check_id', $c->id)->where('is_cancelled', 0)->pluck('sync_uuid')->sort()->values()->all();
    $si = collect($s['items'] ?? [])->filter(fn($i) => empty($i['is_cancelled']))->pluck('sync_uuid')->sort()->values()->all();
    $ok = $s && abs((float)$c->total - (float)$s['total']) < 0.01 && $li == $si;
    if (!$ok) $allOk = false;
    echo sprintf("masa=%-3s yerel[%s/%d] sunucu[%s/%d] %s\n", $c->dining_table_id, $c->total, count($li), $s['total'] ?? '-', count($si), $ok ? '✅' : '❌');
}
echo "\nGENEL: " . ($allOk ? "🎉 TAM EŞİTLİK" : "❌ hâlâ uyumsuz") . "\n";
