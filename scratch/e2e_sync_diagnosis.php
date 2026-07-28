<?php
/**
 * UÇTAN UCA TEST:
 * 1. SQLite'daki Türk Kahvesi'nin mevcut durumunu göster
 * 2. Senkronize edilmemiş stock_movements'ları göster
 * 3. Manuel PUSH yap ve yanıtı göster
 * 4. Tekrar PULL yap ve MySQL'deki durumu göster
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

echo "========================================\n";
echo "1. SQLITE'DAKİ TÜM ÜRÜNLER (STOK BİLGİSİ)\n";
echo "========================================\n";
$localProducts = DB::connection('sqlite')->table('products')->get();
foreach ($localProducts as $p) {
    echo sprintf("  ID:%d | %s | Stock: %s | is_synced: %s | sync_uuid: %s\n",
        $p->id, str_pad($p->name, 25), $p->stock_quantity, var_export($p->is_synced, true), $p->sync_uuid
    );
}

echo "\n========================================\n";
echo "2. SENKRONİZE EDİLMEMİŞ STOCK_MOVEMENTS\n";
echo "========================================\n";
$unsyncedSM = DB::connection('sqlite')->table('stock_movements')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();
echo "  Toplam: " . $unsyncedSM->count() . " adet\n";
foreach ($unsyncedSM as $sm) {
    $prodName = DB::connection('sqlite')->table('products')->where('id', $sm->product_id)->value('name') ?? '???';
    $prodSyncUuid = DB::connection('sqlite')->table('products')->where('id', $sm->product_id)->value('sync_uuid') ?? '???';
    echo sprintf("  SM_ID:%d | product_id:%d (%s) | type:%s | qty:%s | sync_uuid:%s | product_sync_uuid:%s\n",
        $sm->id, $sm->product_id, $prodName, $sm->type, $sm->quantity, $sm->sync_uuid, $prodSyncUuid
    );
}

echo "\n========================================\n";
echo "3. SENKRONİZE EDİLMEMİŞ CHECKS\n";
echo "========================================\n";
$unsyncedChecks = DB::connection('sqlite')->table('checks')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();
echo "  Toplam: " . $unsyncedChecks->count() . " adet\n";
foreach ($unsyncedChecks as $c) {
    echo sprintf("  ID:%d | %s | status:%s | sync_uuid:%s | total:%.2f\n",
        $c->id, $c->check_number, $c->status, $c->sync_uuid, $c->total
    );
}

echo "\n========================================\n";
echo "4. SENKRONİZE EDİLMEMİŞ CHECK_ITEMS\n";
echo "========================================\n";
$unsyncedCI = DB::connection('sqlite')->table('check_items')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();
echo "  Toplam: " . $unsyncedCI->count() . " adet\n";
foreach ($unsyncedCI as $ci) {
    echo sprintf("  ID:%d | check_id:%d | product_id:%d | %s | qty:%s | sync_uuid:%s\n",
        $ci->id, $ci->check_id, $ci->product_id ?? 0, $ci->product_name ?? '?', $ci->quantity, $ci->sync_uuid ?? 'NULL'
    );
}

echo "\n========================================\n";
echo "5. SENKRONİZE EDİLMEMİŞ PAYMENTS\n";
echo "========================================\n";
$unsyncedPay = DB::connection('sqlite')->table('payments')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();
echo "  Toplam: " . $unsyncedPay->count() . " adet\n";

echo "\n========================================\n";
echo "6. PUSH SİMÜLASYONU - PAYLOAD\n";
echo "========================================\n";

// Build same payload as SyncLocalDatabaseCommand::pushUnsyncedLocalDataToCloud
$unsyncedProducts = DB::connection('sqlite')->table('products')
    ->where(fn($q) => $q->where('is_synced', false)->orWhere('is_synced', 0)->orWhereNull('is_synced'))
    ->get();
echo "  Unsynced products: " . $unsyncedProducts->count() . "\n";

// Also add stock movement products
if ($unsyncedSM->isNotEmpty()) {
    $stockProductIds = $unsyncedSM->pluck('product_id')->filter()->unique()->toArray();
    if (!empty($stockProductIds)) {
        $extraProducts = DB::connection('sqlite')->table('products')->whereIn('id', $stockProductIds)->get();
        $existingProductIds = $unsyncedProducts->pluck('id')->toArray();
        foreach ($extraProducts as $ep) {
            if (!in_array($ep->id, $existingProductIds, true)) {
                $unsyncedProducts->push($ep);
            }
        }
    }
}
echo "  Products in PUSH payload (with stock_movement products): " . $unsyncedProducts->count() . "\n";

$stockPayload = [];
foreach ($unsyncedSM as $stock) {
    $pSyncUuid = DB::connection('sqlite')->table('products')->where('id', $stock->product_id)->value('sync_uuid');
    $stockPayload[] = [
        'sync_uuid' => $stock->sync_uuid,
        'product_id' => $stock->product_id,
        'product_sync_uuid' => $pSyncUuid,
        'type' => $stock->type,
        'quantity' => (float) $stock->quantity,
        'notes' => $stock->notes ?? null,
    ];
}

$productsPayload = [];
foreach ($unsyncedProducts as $prod) {
    $categorySyncUuid = DB::connection('sqlite')->table('categories')->where('id', $prod->category_id)->value('sync_uuid');
    $productsPayload[] = [
        'sync_uuid' => $prod->sync_uuid,
        'name' => $prod->name,
        'category_sync_uuid' => $categorySyncUuid,
        'stock_quantity' => (float) ($prod->stock_quantity ?? 0),
        'price' => (float) $prod->price,
        'track_stock' => (bool) ($prod->track_stock ?? false),
        'is_active' => (bool) ($prod->is_active ?? true),
    ];
}

echo "\n  Stock Movements Payload:\n";
foreach ($stockPayload as $sp) {
    echo "    product_sync_uuid: {$sp['product_sync_uuid']} | type: {$sp['type']} | qty: {$sp['quantity']}\n";
}

echo "\n  Products Payload:\n";
foreach ($productsPayload as $pp) {
    echo "    sync_uuid: {$pp['sync_uuid']} | name: {$pp['name']} | stock: {$pp['stock_quantity']}\n";
}

echo "\n========================================\n";
echo "7. KRİTİK ANALİZ - SUNUCU STOCK_MOVEMENTS İŞLEME MANTIĞI\n";
echo "========================================\n";
echo "  SyncApiController satır 354-355:\n";
echo "    if (empty(\$pSyncUuid) || !in_array(\$pSyncUuid, \$pushedProductUuids, true))\n";
echo "  \$pushedProductUuids = products payload'ındaki sync_uuid'ler\n";
echo "\n";

$pushedProductUuids = collect($productsPayload)->pluck('sync_uuid')->filter()->toArray();
echo "  pushedProductUuids: " . json_encode($pushedProductUuids) . "\n\n";

foreach ($stockPayload as $sp) {
    $inPushed = in_array($sp['product_sync_uuid'], $pushedProductUuids, true);
    echo "  Stock {$sp['product_sync_uuid']} ({$sp['type']}): ";
    if ($inPushed) {
        echo "⚠️ ÜRÜN pushedProductUuids LİSTESİNDE → SUNUCU STOCK DECREMENT YAPMIYOR!\n";
        echo "    → Ürün stock_quantity products PUSH'ta geldiği için sunucu 'zaten düşük' varsayıyor.\n";
        echo "    → AMA PULL'da sunucu bu ürünü geri gönderip SQLite'daki stoğu OVERRIDE EDEBİLİR!\n";
    } else {
        echo "✅ Ürün pushedProductUuids'de YOK → Sunucu decrement yapacak\n";
    }
}

echo "\n========================================\n";
echo "8. MYSQL'DEN GÜNCEL STOK DURUMU (PULL)\n";
echo "========================================\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get('https://adisyon.synaptronic.com/api/v1/sync/pull');

if ($r->successful()) {
    $remoteProducts = $r->json('data.products') ?? [];
    foreach ($remoteProducts as $rp) {
        echo sprintf("  ID:%d | %s | Stock: %s | sync_uuid: %s\n",
            $rp['id'], str_pad($rp['name'], 25), $rp['stock_quantity'], $rp['sync_uuid']
        );
    }
} else {
    echo "  PULL HATASI: HTTP " . $r->status() . "\n";

    // Try correct domain
    $r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get('https://adisyon.synaptropic.com/api/v1/sync/pull');

    if ($r2->successful()) {
        $remoteProducts = $r2->json('data.products') ?? [];
        foreach ($remoteProducts as $rp) {
            echo sprintf("  ID:%d | %s | Stock: %s | sync_uuid: %s\n",
                $rp['id'], str_pad($rp['name'], 25), $rp['stock_quantity'], $rp['sync_uuid']
            );
        }
    } else {
        echo "  PULL HATASI 2: HTTP " . $r2->status() . "\n";
    }
}

echo "\n========================================\n";
echo "9. SENKRONIZASYON SORUNUNUN KÖK NEDENİ\n";
echo "========================================\n";
echo "  PUSH sıralaması: Önce PRODUCTS (stock_quantity=99 dahil), sonra STOCK_MOVEMENTS\n";
echo "  SyncApiController şöyle çalışır:\n";
echo "    1) Products: MySQL Türk Kahvesi'ni stock_quantity=99 olarak GÜNCELLER (✅)\n";
echo "    2) Stock Movements: product_sync_uuid pushedProductUuids'de MI diye bakar\n";
echo "       → EVETSE: MySQL'de DECREMENT YAPMAZ (çünkü ürün zaten güncel stokla geldi der)\n";
echo "       → HAYIRSA: MySQL'de DECREMENT YAPAR\n";
echo "\n";
echo "  SORUN NOKTASI: Ürün products payload'ında gönderildiğinde stok hareketi İGNORE ediliyor.\n";
echo "  Bu tasarım gereği: ürünün stock_quantity'si zaten güncel olarak gönderildi.\n";
echo "  AMA sonra PULL geldiğinde products MySQL'den geri çekilip SQLite'a yazılıyor.\n";
echo "  VE: Eğer ürün is_synced=0 kalırsa, SONRAKI senkronizasyonda tekrar PUSH ediliyor!\n";
echo "  → Böylece sonsuz döngü: her seferinde aynı stock_movements PUSH ediliyor ama\n";
echo "    sunucu 'zaten var' diye geçiyor (existingStock check satır 313-319)\n";
echo "  → Products ise her seferinde gönderiliyor çünkü is_synced asla 1 olmuyor!\n";
