<?php
// Mutfak sayfası hata tespiti
echo "=== MUTFAK SAYFASI HATA KONTROLÜ ===\n\n";

try {
    // 1. Check tablosu
    $checkCount = App\Models\Check::count();
    echo "1. Check sayısı: {$checkCount}\n";

    // 2. CheckItem tablosu
    $itemCount = App\Models\CheckItem::count();
    echo "2. CheckItem sayısı: {$itemCount}\n";

    // 3. stock_movements tablosu kontrolü
    echo "3. stock_movements tablosu: ";
    if (Illuminate\Support\Facades\Schema::hasTable('stock_movements')) {
        echo "VAR\n";
        // check_item_id kolonu var mı?
        echo "   check_item_id kolonu: ";
        if (Illuminate\Support\Facades\Schema::hasColumn('stock_movements', 'check_item_id')) {
            echo "VAR\n";
        } else {
            echo "YOK! ← BU SORUN OLABİLİR\n";
        }
    } else {
        echo "YOK!\n";
    }

    // 4. Mutfak sorgusu simülasyonu
    echo "\n4. Mutfak sorgusu test:\n";
    $checks = App\Models\Check::where(function ($q) {
            $q->whereNotNull('kitchen_sent_at')
              ->orWhere('status', 'open')
              ->orWhereHas('items');
        })
        ->with(['diningTable.hall', 'waiter', 'items' => function ($q) {
            $q->with('product.category');
        }])
        ->whereHas('items')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get();

    echo "   Mutfak sorgusu başarılı! {$checks->count()} adisyon bulundu.\n";
    foreach ($checks as $c) {
        $table = $c->diningTable?->name ?? 'Tezgah';
        echo "   - Check #{$c->id} Masa:{$table} Items:{$c->items->count()} KitchenSent:{$c->kitchen_sent_at}\n";
    }

    // 5. Product category ilişkisi
    echo "\n5. Ürün kategori ilişkisi:\n";
    $products = App\Models\Product::with('category')->take(3)->get();
    foreach ($products as $p) {
        echo "   - {$p->name} -> Kategori: " . ($p->category?->name ?? 'YOK') . "\n";
    }

    // 6. Settings tablosu
    echo "\n6. Settings tablosu: ";
    if (Illuminate\Support\Facades\Schema::hasTable('settings')) {
        echo "VAR\n";
    } else {
        echo "YOK!\n";
    }

    echo "\n✅ Tüm kontroller tamamlandı.\n";
} catch (\Throwable $e) {
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    echo "Dosya: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
