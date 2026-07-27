<?php
echo "=== ÜRÜN VE KATEGORİ SENKRONİZASYON KONTROLÜ ===\n\n";

$catCount = App\Models\Category::count();
$prodCount = App\Models\Product::count();

echo "Kategori Sayısı: {$catCount}\n";
echo "Ürün Sayısı: {$prodCount}\n\n";

$unsyncedProds = App\Models\Product::where('is_synced', false)->orWhereNull('is_synced')->count();
$unsyncedCats = App\Models\Category::where('is_synced', false)->orWhereNull('is_synced')->count();

echo "Senkronize Edilmemiş Ürün Sayısı: {$unsyncedProds}\n";
echo "Senkronize Edilmemiş Kategori Sayısı: {$unsyncedCats}\n";

$sampleProd = App\Models\Product::first();
if ($sampleProd) {
    echo "\nÖrnek Ürün:\n";
    echo "ID: {$sampleProd->id}\n";
    echo "Ad: {$sampleProd->name}\n";
    echo "Fiyat: {$sampleProd->price}\n";
    echo "Stok Miktarı: {$sampleProd->stock_quantity}\n";
    echo "sync_uuid: {$sampleProd->sync_uuid}\n";
    echo "is_synced: " . ($sampleProd->is_synced ? 'YES' : 'NO') . "\n";
}

echo "\n✅ Kontrol tamamlandı.\n";
