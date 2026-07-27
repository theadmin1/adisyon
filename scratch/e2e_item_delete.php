<?php
// E2E: masa adisyon kaleminin offline->online silme (soft-cancel) akışını uçtan uca doğrular.
// Gerçek removeItem() kodunu kullanır (UI'daki DELETE butonunun çağırdığı servis).
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
function srvCheck46($k){
    $r = Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$k,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');
    return collect($r->json('data.checks'))->firstWhere('id', 46);
}

echo "=== E2E MASA KALEMİ SİLME TESTİ (check 46) ===\n";

// 0. Başlangıç durumu
$chk = srvCheck46($apiKey);
echo "SUNUCU BAŞLANGIÇ: total={$chk['total']}, kalemler: " . collect($chk['items'])->pluck('product_name')->implode(', ') . "\n";
$localItems = DB::connection('sqlite')->table('check_items')->where('check_id', 46)->get();
echo "YEREL BAŞLANGIÇ: " . $localItems->map(fn($i) => "{$i->product_name}(c={$i->is_cancelled},s={$i->is_synced})")->implode(', ') . "\n";

// 1. Hortlak kalemleri GERÇEK removeItem() ile sil (dede kalsın)
$victims = DB::connection('sqlite')->table('check_items')
    ->where('check_id', 46)
    ->where('product_name', '!=', 'dede')
    ->pluck('id');
foreach ($victims as $vid) {
    $item = \App\Models\CheckItem::find($vid);
    if ($item) {
        (new \App\Services\Checks\CheckService())->removeItem($item);
        echo "removeItem() çağrıldı: {$item->product_name} (id=$vid)\n";
    }
}

// 2. Senkron sync (arka plan değil — deterministik test)
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo "sync #1 tamamlandı\n";

// 3. Doğrulama
$chk2 = srvCheck46($apiKey);
$names2 = collect($chk2['items'])->pluck('product_name');
$local2 = DB::connection('sqlite')->table('check_items')->where('check_id', 46)->get();
echo "\nSYNC SONRASI:\n";
echo "  SUNUCU: total={$chk2['total']}, kalemler: " . $names2->implode(', ') . "\n";
echo "  YEREL:  " . $local2->map(fn($i) => "{$i->product_name}(c={$i->is_cancelled},s={$i->is_synced})")->implode(', ') . "\n";

// 4. İkinci sync (hortlama testi — PULL geri getiriyor mu?)
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$chk3 = srvCheck46($apiKey);
$names3 = collect($chk3['items'])->pluck('product_name');
$local3 = DB::connection('sqlite')->table('check_items')->where('check_id', 46)->get();
echo "\n2. SYNC SONRASI (hortlama kontrolü):\n";
echo "  SUNUCU: total={$chk3['total']}, kalemler: " . $names3->implode(', ') . "\n";
echo "  YEREL:  " . $local3->map(fn($i) => "{$i->product_name}(c={$i->is_cancelled},s={$i->is_synced})")->implode(', ') . "\n";

$srvOk = !$names3->contains('Coca-Cola 330ml') && !$names3->contains('CancelTest Cola');
$locOk = !$local3->pluck('product_name')->contains('Coca-Cola 330ml') && !$local3->pluck('product_name')->contains('CancelTest Cola');
$dedeOk = $names3->contains('dede') && $local3->pluck('product_name')->contains('dede');
echo "\nSONUÇ: sunucudan silindi=" . ($srvOk?'✅':'❌') . " | yerelde hortlamadı=" . ($locOk?'✅':'❌') . " | dede korundu=" . ($dedeOk?'✅':'❌') . "\n";
