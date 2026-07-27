<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== deleted_records TABLO DURUMU ===" . PHP_EOL;

// Sütunları kontrol et
$cols = DB::connection('sqlite')->select('PRAGMA table_info(deleted_records)');
echo "Sütunlar: ";
foreach ($cols as $c) {
    echo $c->name . '(' . $c->type . ') ';
}
echo PHP_EOL;

// Kayıtları kontrol et
$all = DB::connection('sqlite')->table('deleted_records')->get();
echo "Toplam kayıt: " . count($all) . PHP_EOL;
foreach ($all as $r) {
    echo "  type={$r->type} uuid=" . ($r->sync_uuid ?? 'NULL') . " name=" . ($r->name ?? 'NULL') . " record_id=" . ($r->record_id ?? 'NULL') . " is_synced={$r->is_synced}" . PHP_EOL;
}

// Products sayısını kontrol et
$prodCount = DB::connection('sqlite')->table('products')->count();
echo PHP_EOL . "Products sayısı: {$prodCount}" . PHP_EOL;

// Bir ürün sil ve kontrol et
$lastProduct = DB::connection('sqlite')->table('products')->orderBy('id', 'desc')->first();
if ($lastProduct) {
    echo PHP_EOL . "=== SİLME TESTİ ===" . PHP_EOL;
    echo "Silinecek ürün: {$lastProduct->name} (ID:{$lastProduct->id}, UUID:{$lastProduct->sync_uuid})" . PHP_EOL;
    
    // Model üzerinden sil (booted event tetiklensin)
    $product = \App\Models\Product::find($lastProduct->id);
    if ($product) {
        $product->delete();
        echo "Model->delete() çağrıldı." . PHP_EOL;
        
        // deleted_records kontrol
        $delRec = DB::connection('sqlite')->table('deleted_records')
            ->where('sync_uuid', $lastProduct->sync_uuid)
            ->orWhere('name', $lastProduct->name)
            ->first();
        if ($delRec) {
            echo "✅ deleted_records kaydı bulundu: uuid={$delRec->sync_uuid}, name=" . ($delRec->name ?? 'NULL') . ", record_id=" . ($delRec->record_id ?? 'NULL') . ", is_synced={$delRec->is_synced}" . PHP_EOL;
        } else {
            echo "❌ deleted_records kaydı BULUNAMADI!" . PHP_EOL;
        }
        
        // Products'ta hala var mı?
        $stillExists = DB::connection('sqlite')->table('products')
            ->where('id', $lastProduct->id)
            ->exists();
        echo "Ürün SQLite'da " . ($stillExists ? "HALA VAR ❌" : "SİLİNDİ ✅") . PHP_EOL;
        
        // Sync çalıştır
        echo PHP_EOL . "Sync çalıştırılıyor..." . PHP_EOL;
        \Illuminate\Support\Facades\Artisan::call('app:sync-local');
        echo Artisan::output();
        
        // Ürün geri geldi mi?
        $cameBack = DB::connection('sqlite')->table('products')
            ->where(function($q) use ($lastProduct) {
                $q->where('sync_uuid', $lastProduct->sync_uuid)
                  ->orWhere('name', $lastProduct->name);
            })
            ->first();
        if ($cameBack) {
            echo "❌ ÜRÜN GERİ GELDİ! id={$cameBack->id}, name={$cameBack->name}, sync_uuid={$cameBack->sync_uuid}" . PHP_EOL;
        } else {
            echo "✅ Ürün geri gelmedi — DOĞRU!" . PHP_EOL;
        }
        
        // deleted_records durumu
        $delRecAfter = DB::connection('sqlite')->table('deleted_records')
            ->where('sync_uuid', $lastProduct->sync_uuid)
            ->orWhere('name', $lastProduct->name)
            ->first();
        if ($delRecAfter) {
            echo "deleted_records: uuid={$delRecAfter->sync_uuid}, is_synced={$delRecAfter->is_synced}" . PHP_EOL;
        } else {
            echo "deleted_records temizlendi (is_synced=true sonrası)" . PHP_EOL;
        }
    }
}
