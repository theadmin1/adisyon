<?php
// Hortlama (resurrection) kusurunu deterministik üretir:
// Sunucu 0 satır silse bile uuid'i synced_uuids'e ekliyor -> tombstone erken temizleniyor -> sonraki PULL ürünü geri getiriyor
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
function pull($k){ return collect(Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$k,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull')->json('data.products')??[]); }
function push($k,$payload){ return Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$k,'Accept'=>'application/json'])->post('https://adisyon.synaptropic.com/api/v1/sync/push',$payload); }

echo "=== HORTLAMA KUSURU TESTİ (eşleşmeyen tombstone) ===\n";

$serverUuid = (string) Str::uuid();
$name = "Hortlak " . rand(1000,9999);

// 1) Sunucuya bir ürün ekle (uuid = serverUuid)
push($apiKey, ['batch_id'=>'B'.time(),'products'=>[['sync_uuid'=>$serverUuid,'category_id'=>1,'name'=>$name,'price'=>10,'is_active'=>true]]]);
$onSrv = pull($apiKey)->firstWhere('sync_uuid',$serverUuid);
echo "1) Sunucuya eklendi: ".($onSrv?"VAR (id={$onSrv['id']}, uuid=".substr($serverUuid,0,8).")":"YOK")."\n";

// 2) Local'e çek
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$loc = DB::connection('sqlite')->table('products')->where('sync_uuid',$serverUuid)->first();
echo "2) Local'e çekildi: ".($loc?"VAR (local_id={$loc->id})":"YOK")."\n";

// 3) Local'de sil AMA tombstone'u EŞLEŞMEYECEK şekilde yaz (farklı uuid, farklı isim, farklı id)
//    (Gerçek hayatta: isim düzenlenmiş / legacy null-uuid / concurrent stale pull bunu tetikler)
$mismatchUuid = (string) Str::uuid();
DB::connection('sqlite')->table('products')->where('sync_uuid',$serverUuid)->delete();
DB::connection('sqlite')->table('deleted_records')->updateOrInsert(
    ['sync_uuid'=>$mismatchUuid,'type'=>'product'],
    ['record_id'=>999999,'name'=>'FARKLI_ISIM','is_synced'=>false,'created_at'=>now(),'updated_at'=>now()]
);
echo "3) Local'de silindi + EŞLEŞMEYEN tombstone yazıldı (uuid=".substr($mismatchUuid,0,8).", name=FARKLI_ISIM, rid=999999)\n";

// 4) Sync çalıştır
\Illuminate\Support\Facades\Artisan::call('app:sync-local');

// 5) Sonuç
$srv2 = pull($apiKey)->firstWhere('sync_uuid',$serverUuid);
$loc2 = DB::connection('sqlite')->table('products')->where('sync_uuid',$serverUuid)->first();
$tomb = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid',$mismatchUuid)->first();
echo "5) SONUÇ:\n";
echo "   Server:    ".($srv2?"VAR (silinmedi -> 'online da silmiyor')":"YOK")."\n";
echo "   Local:     ".($loc2?"VAR (HORTLADI -> 'yenileyince geliyor')":"YOK")."\n";
echo "   Tombstone: ".($tomb?"kaldı (synced={$tomb->is_synced})":"TEMİZLENDİ (erken silindi -> filtre kayboldu)")."\n";

// temizlik: server'daki test ürününü gerçek isim/uuid ile sil
push($apiKey, ['batch_id'=>'B'.time(),'deleted_products'=>[['sync_uuid'=>$serverUuid,'name'=>$name,'record_id'=>($onSrv['id']??null)]]]);
DB::connection('sqlite')->table('products')->where('sync_uuid',$serverUuid)->delete();
DB::connection('sqlite')->table('deleted_records')->where('sync_uuid',$mismatchUuid)->delete();
echo "(test verileri temizlendi)\n";
