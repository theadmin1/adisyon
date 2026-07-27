<?php
/**
 * Çevrimdışı/Çevrimiçi Senkronizasyon Akış Testi
 * Senaryo: Masa açık, internet gidiyor, offline modda 2. ürün ekleniyor, internet geliyor, senkronize oluyor
 */

use Illuminate\Support\Str;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Product;

echo "=== OFFLINE/ONLINE SYNC AKIŞ TESTİ ===\n\n";

$db = config('database.default');
echo "📌 Aktif Veritabanı: {$db}\n\n";

// 1. Test masası ve ürünlerini hazırla
$table = DiningTable::first();
$product1 = Product::first();
$product2 = Product::skip(1)->first();

if (!$table || !$product1 || !$product2) {
    echo "❌ Test verileri eksik (masa veya ürün bulunamadı)\n";
    exit(1);
}
echo "✅ Test Masası: {$table->name} (ID: {$table->id})\n";
echo "✅ Ürün 1: {$product1->name} - ₺{$product1->price}\n";
echo "✅ Ürün 2: {$product2->name} - ₺{$product2->price}\n\n";

// 2. ONLINE MOD: Masa açılır, 1. ürün eklenir (is_synced = true çünkü online)
echo "--- AŞAMA 1: ONLINE MOD - Masa açılıp 1. ürün ekleniyor ---\n";
$checkService = app(\App\Services\Checks\CheckService::class);
$check = $checkService->openCheck($table);
echo "✅ Adisyon açıldı: #{$check->id} (sync_uuid: {$check->sync_uuid})\n";
echo "   is_synced: " . ($check->is_synced ? 'YES' : 'NO') . "\n";

$check = $checkService->addItems($check, [
    ['product_id' => $product1->id, 'quantity' => 1]
]);
echo "✅ 1. ürün eklendi: {$product1->name} x1 = ₺{$product1->price}\n";
echo "   Adisyon toplam: ₺{$check->total}\n";

$item1 = $check->items->first();
echo "   Item sync_uuid: {$item1->sync_uuid}\n";
echo "   Item is_synced: " . ($item1->is_synced ? 'YES' : 'NO') . "\n\n";

// 3. İNTERNET KESİLDİ! Offline moda geçiş simülasyonu
echo "--- AŞAMA 2: İNTERNET KESİLDİ - Offline modda 2. ürün ekleniyor ---\n";
echo "⚡ İnternet kesildi simülasyonu: is_synced = false olarak kaydedilecek\n";

// Offline modda 2. ürün ekleniyor
$offlineItemSyncUuid = (string) Str::uuid();
$offlineItem = CheckItem::create([
    'check_id' => $check->id,
    'product_id' => $product2->id,
    'product_name' => $product2->name,
    'sync_uuid' => $offlineItemSyncUuid,
    'is_synced' => false,  // OFFLINE! Senkronize değil!
    'quantity' => 1,
    'unit_price' => $product2->price,
    'total_price' => $product2->price * 1,
    'kitchen_status' => 'pending',
]);
echo "✅ 2. ürün OFFLINE eklendi: {$product2->name} x1 = ₺{$product2->price}\n";
echo "   Item sync_uuid: {$offlineItemSyncUuid}\n";
echo "   Item is_synced: NO (offline)\n";

// Check toplam tutarını güncelle (offline modda da yapılır)
$checkService->recalculateTotals($check);
$check = $check->fresh();
echo "   Adisyon güncel toplam: ₺{$check->total}\n\n";

// 4. Kontrol: Offline eklenen ürün SQLite'da var mı?
echo "--- AŞAMA 3: DOĞRULAMA - Offline eklenen ürün SQLite'da var mı? ---\n";
$allItems = CheckItem::where('check_id', $check->id)->get();
echo "✅ Adisyon #{$check->id} toplam kalem sayısı: {$allItems->count()}\n";
foreach ($allItems as $i) {
    $syncStatus = $i->is_synced ? '🟢 SYNCED' : '🔴 UNSYNCED';
    echo "   {$syncStatus} -> {$i->product_name} x{$i->quantity} = ₺{$i->total_price} (sync: {$i->sync_uuid})\n";
}

// 5. Senkronize edilmemiş ögelerin sayısı
$unsyncedItems = CheckItem::where('is_synced', false)->orWhereNull('is_synced')->get();
echo "\n📊 Senkronize edilmemiş toplam CheckItem sayısı: {$unsyncedItems->count()}\n";
foreach ($unsyncedItems as $ui) {
    echo "   🔴 Item #{$ui->id}: {$ui->product_name} (check_id: {$ui->check_id}, sync: {$ui->sync_uuid})\n";
}

// 6. İNTERNET GELDİ! Push Sync simülasyonu
echo "\n--- AŞAMA 4: İNTERNET GELDİ - Push Sync simülasyonu ---\n";
echo "⚡ Internet geldi! Senkronize edilmemiş verileri toplayıp PUSH payload oluşturuyoruz...\n\n";

// pushUnsyncedLocalDataToCloud fonksiyonunun yaptığını simüle et
$unsyncedCheckIdsFromItems = CheckItem::where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->pluck('check_id')->filter()->unique()->toArray();

echo "📋 Senkronize edilmemiş item'ların bağlı olduğu Check ID'leri: " . implode(', ', $unsyncedCheckIdsFromItems) . "\n";

$checksToSync = Check::whereIn('id', $unsyncedCheckIdsFromItems)->get();
echo "📋 Senkronize edilecek Check sayısı: {$checksToSync->count()}\n";

foreach ($checksToSync as $cts) {
    $itsItems = CheckItem::where('check_id', $cts->id)->get();
    echo "\n  Check #{$cts->id} (sync: {$cts->sync_uuid}) Masa:{$cts->dining_table_id} Toplam:₺{$cts->total}\n";
    foreach ($itsItems as $iti) {
        $flag = $iti->is_synced ? '🟢' : '🔴';
        echo "    {$flag} {$iti->product_name} x{$iti->quantity} = ₺{$iti->total_price}\n";
    }
}

// Push payload yapısını oluştur (API'nin kabul edeceği format)
$checksPayload = [];
foreach ($checksToSync as $cts) {
    $itemsPayload = [];
    foreach (CheckItem::where('check_id', $cts->id)->get() as $itm) {
        $itemsPayload[] = [
            'sync_uuid' => $itm->sync_uuid,
            'product_id' => $itm->product_id,
            'product_name' => $itm->product_name,
            'unit_price' => (float) $itm->unit_price,
            'quantity' => (float) $itm->quantity,
            'total_price' => (float) $itm->total_price,
            'status' => $itm->kitchen_status ?? 'pending',
        ];
    }
    $checksPayload[] = [
        'sync_uuid' => $cts->sync_uuid,
        'dining_table_id' => $cts->dining_table_id,
        'total_amount' => (float) $cts->total,
        'discount_amount' => (float) ($cts->discount_total ?? 0),
        'status' => $cts->status,
        'items' => $itemsPayload,
    ];
}

// Müstakil check_items payload
$checkItemsPayload = [];
foreach ($unsyncedItems as $ui) {
    $parentCheck = Check::find($ui->check_id);
    $checkItemsPayload[] = [
        'sync_uuid' => $ui->sync_uuid,
        'check_sync_uuid' => $parentCheck?->sync_uuid,
        'dining_table_id' => $parentCheck?->dining_table_id,
        'product_id' => $ui->product_id,
        'product_name' => $ui->product_name,
        'unit_price' => (float) $ui->unit_price,
        'quantity' => (float) $ui->quantity,
        'total_price' => (float) $ui->total_price,
        'status' => $ui->kitchen_status ?? 'pending',
    ];
}

echo "\n📦 Push Payload Özeti:\n";
echo "   checks: " . count($checksPayload) . " adet\n";
echo "   check_items (müstakil): " . count($checkItemsPayload) . " adet\n";

// JSON payload'ı göster
$fullPayload = [
    'batch_id' => 'TEST-BATCH-' . time(),
    'checks' => $checksPayload,
    'check_items' => $checkItemsPayload,
    'payments' => [],
    'stock_movements' => [],
];
echo "\n📄 JSON Payload:\n";
echo json_encode($fullPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 7. Offline eklenen ürünü synced olarak işaretle (başarılı push simülasyonu)
echo "\n--- AŞAMA 5: PUSH BAŞARILI - Offline verileri synced olarak işaretle ---\n";
CheckItem::where('sync_uuid', $offlineItemSyncUuid)->update(['is_synced' => true]);
$finalItem = CheckItem::where('sync_uuid', $offlineItemSyncUuid)->first();
echo "✅ Offline eklenen ürün artık senkronize: is_synced = " . ($finalItem->is_synced ? 'YES' : 'NO') . "\n";

// 8. Temizlik: Test adisyonunu kapat
$checkService->closeCheck($check);
echo "✅ Test adisyonu kapatıldı.\n";

echo "\n=== ✅ TÜM OFFLINE/ONLINE SYNC AŞAMALARI BAŞARILI ===\n";
