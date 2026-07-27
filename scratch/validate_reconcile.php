<?php
// reconcileTombstones() iki dalını deterministik doğrular (Reflection ile private metodu çağırır).
use Illuminate\Support\Facades\DB;
use App\Console\Commands\SyncLocalDatabaseCommand;

echo "=== reconcileTombstones() DOĞRULAMA ===\n";

// Temiz başlangıç
DB::connection('sqlite')->table('deleted_records')->whereIn('sync_uuid', ['U1-still','U2-gone'])->delete();

// İki tombstone: T1 sunucuda HÂLÂ var, T2 sunucudan gitmiş
DB::connection('sqlite')->table('deleted_records')->insert([
    ['type'=>'product','sync_uuid'=>'U1-still','record_id'=>111,'name'=>'HalaVarUrun','is_synced'=>true,'created_at'=>now(),'updated_at'=>now()],
    ['type'=>'product','sync_uuid'=>'U2-gone','record_id'=>222,'name'=>'GittiUrun','is_synced'=>true,'created_at'=>now(),'updated_at'=>now()],
]);
echo "Kurulum: T1(U1-still, is_synced=1), T2(U2-gone, is_synced=1)\n";

// Taze PULL payload'u: SADECE T1'e karşılık gelen ürün sunucuda var (farklı sunucu id'siyle!)
$serverProducts = [
    ['id'=>9999, 'sync_uuid'=>'U1-still', 'name'=>'HalaVarUrun'],
];

$cmd = new SyncLocalDatabaseCommand();
$m = new ReflectionMethod($cmd, 'reconcileTombstones');
$m->setAccessible(true);
$m->invoke($cmd, 'product', $serverProducts);

$t1 = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid','U1-still')->first();
$t2 = DB::connection('sqlite')->table('deleted_records')->where('sync_uuid','U2-gone')->first();

echo "\nSONUÇ:\n";
echo "  T1 (sunucuda hâlâ var): ".($t1 ? "KORUNDU, is_synced={$t1->is_synced} (0 olmalı -> tekrar PUSH edilecek) ".($t1->is_synced==0?'✅':'❌') : "SİLİNDİ ❌ (hortlama riski!)")."\n";
echo "  T2 (sunucudan gitmiş):  ".($t2 ? "KORUNDU ❌ (gereksiz)" : "SİLİNDİ ✅ (silme onaylandı)")."\n";

// temizlik
DB::connection('sqlite')->table('deleted_records')->whereIn('sync_uuid', ['U1-still','U2-gone'])->delete();
