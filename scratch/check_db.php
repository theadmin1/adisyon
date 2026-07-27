<?php
// Veritabanı durumunu kontrol et
$db = config('database.default');
echo "Aktif DB: {$db}\n";

$checkCount = App\Models\Check::where('status', 'open')->count();
echo "Açık Adisyon Sayısı: {$checkCount}\n";

$checks = App\Models\Check::with('items')->where('status', 'open')->take(5)->get();
foreach ($checks as $c) {
    echo "Check #{$c->id} (sync:{$c->sync_uuid}) Masa:{$c->dining_table_id} Toplam:{$c->total} Items:{$c->items->count()} is_synced:" . ($c->is_synced ? 'YES' : 'NO') . "\n";
    foreach ($c->items as $i) {
        echo "  -> Item #{$i->id} {$i->product_name} x{$i->quantity} sync:{$i->sync_uuid} is_synced:" . ($i->is_synced ? 'YES' : 'NO') . "\n";
    }
}

// Unsynced items kontrolü
$unsyncedItems = App\Models\CheckItem::where('is_synced', false)->orWhereNull('is_synced')->count();
echo "\nSenkronize edilmemiş CheckItem sayısı: {$unsyncedItems}\n";

$unsyncedChecks = App\Models\Check::where('is_synced', false)->orWhereNull('is_synced')->count();
echo "Senkronize edilmemiş Check sayısı: {$unsyncedChecks}\n";

echo "\nToplam Check: " . App\Models\Check::count() . "\n";
echo "Toplam CheckItem: " . App\Models\CheckItem::count() . "\n";
echo "Toplam Product: " . App\Models\Product::count() . "\n";
echo "Toplam DiningTable: " . App\Models\DiningTable::count() . "\n";
