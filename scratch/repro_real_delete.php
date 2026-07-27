<?php
// Gerçek destroy() akışını birebir taklit eder: tombstone (uuid+name+record_id) + delete + SENKRON sync
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
function pull($apiKey){ return Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$apiKey,'Accept'=>'application/json'])->get('https://adisyon.synaptropic.com/api/v1/sync/pull'); }

echo "=== GERÇEK destroy() AKIŞI TAKLİDİ (tombstone: uuid+name+record_id) ===\n";

$uuid = (string) Str::uuid();
$name = "RealDel " . rand(1000,9999);
$catId = DB::connection('sqlite')->table('categories')->value('id') ?? 1;

// 1) ekle + push
$localId = DB::connection('sqlite')->table('products')->insertGetId([
    'category_id'=>$catId,'branch_id'=>1,'name'=>$name,'slug'=>Str::slug($name),
    'price'=>50,'sync_uuid'=>$uuid,'is_synced'=>false,'created_at'=>now(),'updated_at'=>now(),
]);
echo "1) eklendi local_id=$localId uuid=".substr($uuid,0,8)."\n";
\Illuminate\Support\Facades\Artisan::call('app:sync-local');

$srv = collect(pull($apiKey)->json('data.products')??[])->firstWhere('sync_uuid',$uuid);
echo "   server'da: ".($srv?"VAR (server_id={$srv['id']})":"YOK")."\n";

// 2) GERÇEK destroy() tombstone: uuid + name + record_id (LOCAL id!)
DB::connection('sqlite')->table('deleted_records')->updateOrInsert(
    ['sync_uuid'=>$uuid,'type'=>'product'],
    ['record_id'=>$localId,'name'=>$name,'is_synced'=>false,'created_at'=>now(),'updated_at'=>now()]
);
DB::connection('sqlite')->table('products')->where('id',$localId)->delete();
echo "2) local'de silindi + tombstone(uuid+name+record_id=$localId) yazıldı\n";

// 3) sync
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
echo "3) sync çalıştı\n";

// 4) kontrol
$srv2 = collect(pull($apiKey)->json('data.products')??[])->firstWhere('sync_uuid',$uuid);
$loc2 = DB::connection('sqlite')->table('products')->where('sync_uuid',$uuid)->first();
$tomb = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid',$uuid)->first();
echo "4) SONUÇ: server=".($srv2?"VAR(silinmedi!)":"YOK")." | local=".($loc2?"VAR(hortladı!)":"YOK")." | tombstone=".($tomb?"kaldı(synced={$tomb->is_synced})":"temizlendi")."\n";

// 5) İKİNCİ bir sync daha çalıştır (tombstone temizlendikten sonra tekrar pull hortlatıyor mu?)
\Illuminate\Support\Facades\Artisan::call('app:sync-local');
$srv3 = collect(pull($apiKey)->json('data.products')??[])->firstWhere('sync_uuid',$uuid);
$loc3 = DB::connection('sqlite')->table('products')->where('sync_uuid',$uuid)->first();
echo "5) 2. SYNC SONRASI: server=".($srv3?"VAR":"YOK")." | local=".($loc3?"VAR(hortladı!)":"YOK")."\n";
