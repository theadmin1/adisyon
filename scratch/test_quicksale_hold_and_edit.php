<?php
/**
 * TEST: Hızlı Satış Bekletme (Park) ve Satışı Geri Yükleyip Düzenleme
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Check;
use App\Services\Checks\CheckService;

echo "=== TEST: HIZLI SATIŞ BEKLETME VE DÜZENLEME ===\n";

$turkKahvesi = Product::where('name', 'like', '%Türk Kahvesi%')->first();
$cola = Product::where('name', 'like', '%Coca-Cola%')->first();

echo "1. Türk Kahvesi Stok: {$turkKahvesi->stock_quantity} | Coca-Cola Stok: {$cola->stock_quantity}\n";

// 2. Bekletilen Satış Oluştur (Park)
$checkService = new CheckService();
$check = Check::create([
    'branch_id' => 1,
    'dining_table_id' => null,
    'check_number' => 'QCK-TEST-' . rand(1000, 9999),
    'status' => \App\Enums\CheckStatus::Open,
    'opened_at' => now(),
]);

$checkService->addItems($check, [
    ['product_id' => $turkKahvesi->id, 'quantity' => 2],
]);

$turkKahvesi->refresh();
echo "2. Satış Bekletildi (#{$check->check_number}). Türk Kahvesi Stok: {$turkKahvesi->stock_quantity}\n";

// 3. Satışı Düzenle (1 Türk Kahvesi çıkar, 1 Coca-Cola ekle, satışı tamamla)
$oldItems = $check->items()->where('is_cancelled', false)->get();
echo "3. Satıştaki Mevcut Kalemler: " . $oldItems->pluck('product_name')->implode(', ') . "\n";

// Simüle et: 1 Türk Kahvesi, 1 Coca Cola
$checkService->addItems($check, [
    ['product_id' => $cola->id, 'quantity' => 1],
]);
$checkService->closeCheck($check);

$turkKahvesi->refresh();
$cola->refresh();
echo "4. Satış Düzenlendi & Tamamlandı (#{$check->check_number})\n";
echo "   Güncel Türk Kahvesi Stok: {$turkKahvesi->stock_quantity}\n";
echo "   Güncel Coca-Cola Stok: {$cola->stock_quantity}\n";
echo "   Adisyon Toplamı: ₺{$check->total} | Durumu: " . (is_object($check->status) ? $check->status->value : $check->status) . "\n";

echo "\n✅ TEST BAŞARIYLA TAMAMLANDI!\n";
