<?php
// 1) Test masası 7'yi (benim açtığım check52) gerçek akışla kapatıp temizler.
// 2) Yarışların bozduğu open check'leri yeniden senkronlanabilir işaretler (is_synced=0)
//    ve totallerini yerel kalemlerden yeniden hesaplar.
// 3) Tek (kilitli) sync çalıştırır ve yerel<->sunucu open check eşitliğini doğrular.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

// --- 1. Test check52 temizliği (Masa 7) ---
$c52 = \App\Models\Check::where('dining_table_id', 7)->where('status', 'open')->first();
if ($c52) {
    $svc = new \App\Services\Checks\CheckService();
    foreach ($c52->items()->where('is_cancelled', false)->get() as $it) {
        $svc->removeItem($it); // soft-cancel -> sync sunucudan düşürür
    }
    $svc->closeCheck($c52->fresh());
    // kapanışı sunucuya taşınabilir yap
    $c52->fresh()->update(['is_synced' => false]);
    echo "1) Test check{$c52->id} (Masa 7) kapatıldı ve senkrona işaretlendi.\n";
}

// --- 2. Open check onarımı: kalemlerden totali yeniden hesapla + yeniden senkronlanabilir yap ---
$openChecks = \App\Models\Check::where('status', 'open')->get();
foreach ($openChecks as $chk) {
    $sub = $chk->items()->where('is_cancelled', false)->where('is_complimentary', false)->sum('total_price');
    $tot = max(0, $sub - (float) $chk->discount_total);
    DB::connection('sqlite')->table('checks')->where('id', $chk->id)->update([
        'subtotal' => $sub, 'total' => $tot, 'is_synced' => false,
    ]);
    DB::connection('sqlite')->table('check_items')->where('check_id', $chk->id)->update(['is_synced' => false]);
    echo "2) check{$chk->id} onarıldı: total={$tot}, yeniden senkrona işaretlendi.\n";
}

// --- 3. Tek temiz sync + doğrulama ---
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo "3) sync tamamlandı.\n\n";

$r = Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$apiKey,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
$srvChecks = collect($r->json('data.checks'))->where('status', 'open')->keyBy('sync_uuid');
$locChecks = DB::connection('sqlite')->table('checks')->where('status', 'open')->get();

echo "=== KARŞILAŞTIRMA (open checks) ===\n";
$allOk = true;
foreach ($locChecks as $lc) {
    $sc = $srvChecks->get($lc->sync_uuid);
    $locItems = DB::connection('sqlite')->table('check_items')->where('check_id', $lc->id)->where('is_cancelled', 0)->orderBy('sync_uuid')->get();
    $srvItems = collect($sc['items'] ?? [])->filter(fn($i) => empty($i['is_cancelled']))->sortBy('sync_uuid')->values();
    $totMatch = $sc && abs((float)$lc->total - (float)$sc['total']) < 0.01;
    $itemMatch = $sc && $locItems->pluck('sync_uuid')->values()->all() == $srvItems->pluck('sync_uuid')->values()->all();
    $ok = $sc && $totMatch && $itemMatch;
    if (!$ok) $allOk = false;
    echo sprintf("masa=%-3s yerel[total=%s items=%d] sunucu[%s] %s\n",
        $lc->dining_table_id,
        $lc->total, $locItems->count(),
        $sc ? "total={$sc['total']} items={$srvItems->count()}" : 'YOK',
        $ok ? '✅' : '❌ UYUMSUZ'
    );
}
// Sunucuda olup yerelde olmayan open check?
foreach ($srvChecks as $uuid => $sc) {
    if (!$locChecks->firstWhere('sync_uuid', $uuid)) {
        echo "SADECE SUNUCUDA: check{$sc['id']} masa={$sc['dining_table_id']} ❌\n";
        $allOk = false;
    }
}
echo "\nGENEL: " . ($allOk ? "🎉 İKİ TARAF TAMAMEN EŞİT" : "❌ uyumsuzluk var") . "\n";
