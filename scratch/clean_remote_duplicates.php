<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$apiKey = DB::connection('sqlite')->table('settings')->where('key', 'DeviceApiKey')->value('value') ?? 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$pullUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "=== CLEANING REMOTE DUPLICATES & ASSIGNING SYNC_UUIDs ===\n\n";

// 1. Fetch remote products
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

if (!$r->successful()) {
    echo "❌ Failed to fetch remote products: HTTP " . $r->status() . "\n";
    exit(1);
}

$remoteProducts = collect($r->json('data.products') ?? []);
echo "Remote total products count: " . $remoteProducts->count() . "\n";

// Delete duplicate products (IDs > 30 that have valid sync_uuids matching local ones)
$duplicateUuids = [];
foreach ($remoteProducts as $rp) {
    if ($rp['id'] > 30 && !empty($rp['sync_uuid'])) {
        $duplicateUuids[] = [
            'sync_uuid' => $rp['sync_uuid'],
            'name' => $rp['name'],
        ];
    }
}

if (!empty($duplicateUuids)) {
    echo "Deleting " . count($duplicateUuids) . " duplicate product records on remote server...\n";
    $delRes = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->post($pushUrl, [
        'batch_id' => 'CLEAN-DUPS-' . time(),
        'deleted_products' => $duplicateUuids,
    ]);
    echo "Delete PUSH Status: " . $delRes->status() . "\n";
}

// Re-fetch remote products to confirm deletion of duplicates
$r2 = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->get($pullUrl);

$remoteProducts2 = collect($r2->json('data.products') ?? []);
echo "\nRemote products count after cleanup: " . $remoteProducts2->count() . "\n";

// Now, update SQLite products so that their sync_uuid matches remote products by NAME, or update SQLite to match remote products!
echo "\nSynchronizing SQLite products sync_uuid with Remote Products by NAME...\n";
foreach ($remoteProducts2 as $rp) {
    $name = $rp['name'];
    $remoteId = $rp['id'];
    $remoteUuid = $rp['sync_uuid'];

    $localProd = Product::where('name', $name)->first();
    if ($localProd) {
        if (!empty($remoteUuid) && $remoteUuid !== $localProd->sync_uuid) {
            echo "   Updating SQLite product '{$name}' (ID: {$localProd->id}) sync_uuid from '{$localProd->sync_uuid}' to '{$remoteUuid}'...\n";
            DB::connection('sqlite')->table('products')->where('id', $localProd->id)->update(['sync_uuid' => $remoteUuid]);
        } elseif (empty($remoteUuid) && !empty($localProd->sync_uuid)) {
            echo "   Remote product '{$name}' (ID: {$remoteId}) has no sync_uuid. Sending push...\n";
        }
    }
}

echo "\n=== CLEANUP FINISHED ===\n";
