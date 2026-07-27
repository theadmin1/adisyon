<?php
// FINAL: (1) kilit testi (2) Masa 5 items_complete temizliği (3) tam yerel<->sunucu eşitlik doğrulaması
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$hdr = ['X-Device-Api-Key' => $apiKey, 'Accept' => 'application/json'];

// --- 1. KİLİT TESTİ: kilidi elle tutup sync başlat -> atlanmalı ---
$lh = fopen(storage_path('framework/sync-local.lock'), 'c');
flock($lh, LOCK_EX);
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$out = \Illuminate\Support\Facades\Artisan::output();
echo "1) KİLİT TESTİ: " . (str_contains($out, 'atlandı') ? "✅ eşzamanlı sync atlandı" : "❌ kilit çalışmadı!") . "\n";
flock($lh, LOCK_UN); fclose($lh);

// --- 2. Masa 5'i yeniden senkrona işaretle (items_complete push kalıntıyı süpürecek) ---
$c38 = DB::connection('sqlite')->table('checks')->where('dining_table_id', 5)->where('status', 'open')->first();
DB::connection('sqlite')->table('checks')->where('id', $c38->id)->update(['is_synced' => false]);
DB::connection('sqlite')->table('check_items')->where('check_id', $c38->id)->update(['is_synced' => false]);
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo "2) Masa 5 senkronu tamamlandı.\n";

// --- 3. TAM KARŞILAŞTIRMA ---
$r = Http::withoutVerifying()->timeout(20)->withHeaders($hdr)->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$srvChecks = collect($r->json('data.checks'))->where('status', 'open')->keyBy('sync_uuid');
$locChecks = DB::connection('sqlite')->table('checks')->where('status', 'open')->get();
$allOk = true;
echo "\n=== TAM KARŞILAŞTIRMA (open checks) ===\n";
foreach ($locChecks as $c) {
    $s = $srvChecks->get($c->sync_uuid);
    $li = DB::connection('sqlite')->table('check_items')->where('check_id', $c->id)->where('is_cancelled', 0)->pluck('sync_uuid')->sort()->values()->all();
    $si = collect($s['items'] ?? [])->filter(fn($i) => empty($i['is_cancelled']))->pluck('sync_uuid')->sort()->values()->all();
    $nullUuidCount = collect($s['items'] ?? [])->filter(fn($i) => empty($i['sync_uuid']))->count();
    $ok = $s && abs((float)$c->total - (float)$s['total']) < 0.01 && $li == $si && $nullUuidCount === 0;
    if (!$ok) $allOk = false;
    echo sprintf("masa=%-3s yerel[total=%s items=%d] sunucu[total=%s items=%d nullUuid=%d] %s\n",
        $c->dining_table_id, $c->total, count($li), $s['total'] ?? '-', count($si), $nullUuidCount, $ok ? '✅' : '❌');
}
foreach ($srvChecks as $uuid => $s) {
    if (!$locChecks->firstWhere('sync_uuid', $uuid)) { echo "SADECE SUNUCUDA: masa={$s['dining_table_id']} ❌\n"; $allOk = false; }
}
echo "\nGENEL: " . ($allOk ? "🎉 İKİ TARAF BİREBİR EŞİT — çift yönlü senkron sağlıklı" : "❌ uyumsuzluk sürüyor") . "\n";
