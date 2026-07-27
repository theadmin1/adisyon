<?php
// Canlı sunucunun YENİ kodu alıp almadığını ayırt eder.
// Test: sadece record_id (=server id) ile eşleşen, uuid/name eşleşMEyen bir silme gönder.
//   ESKI kod: orWhere('id',$delId) -> ürünü YANLIŞLIKLA siler.
//   YENI kod: id ile eşleştirmez  -> ürün HAYATTA kalır.
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$base = 'https://adisyon.synaptropic.com/api/v1/sync';
function pull($k,$b){ return collect(Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$k,'Accept'=>'application/json'])->get($b.'/pull')->json('data.products')??[]); }
function push($k,$b,$p){ return Http::withoutVerifying()->timeout(20)->withHeaders(['X-Device-Api-Key'=>$k,'Accept'=>'application/json'])->post($b.'/push',$p); }

$uuid = (string) Str::uuid();
$name = "DeployProbe " . rand(1000,9999);

echo "1) Sunucuya deneme ürünü ekleniyor...\n";
push($apiKey,$base,['batch_id'=>'B'.time(),'products'=>[['sync_uuid'=>$uuid,'category_id'=>1,'name'=>$name,'price'=>1,'is_active'=>true]]]);
$p = pull($apiKey,$base)->firstWhere('sync_uuid',$uuid);
if(!$p){ echo "   ❌ ürün eklenemedi, test iptal\n"; exit; }
$serverId = $p['id'];
echo "   eklendi: server_id=$serverId, uuid=".substr($uuid,0,8)."\n";

echo "2) SADECE record_id=$serverId ile (uuid/name kasten YANLIŞ) silme isteği gönderiliyor...\n";
push($apiKey,$base,['batch_id'=>'B'.time(),'deleted_products'=>[[
    'sync_uuid'=>'kasten-yanlis-uuid','name'=>'KASTEN_YANLIS_ISIM','record_id'=>$serverId,
]]]);

$still = pull($apiKey,$base)->firstWhere('sync_uuid',$uuid);
echo "3) SONUÇ: ürün ".($still?"HAYATTA -> ✅ YENİ KOD CANLIDA (id ile yanlış silme yok)":"SİLİNDİ -> ❌ hâlâ ESKİ KOD (id ile yanlış silinmiş)")."\n";

echo "4) Temizlik: ürün doğru uuid+name ile siliniyor...\n";
push($apiKey,$base,['batch_id'=>'B'.time(),'deleted_products'=>[['sync_uuid'=>$uuid,'name'=>$name]]]);
$gone = pull($apiKey,$base)->firstWhere('sync_uuid',$uuid);
echo "   temizlik: ".($gone?"BAŞARISIZ (hâlâ var!)":"OK (silindi)")."\n";
