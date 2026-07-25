<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$response = \Illuminate\Support\Facades\Http::withoutVerifying()
    ->timeout(15)
    ->withHeaders(['X-Device-Api-Key' => $apiKey])
    ->get($apiUrl);

if ($response->successful() && $response->json('success')) {
    $checks = $response->json('data.checks') ?? [];
    echo "=== CLOUD CHECKS RETURNED (" . count($checks) . ") ===" . PHP_EOL;
    foreach ($checks as $c) {
        $items = $c['items'] ?? [];
        echo "Check ID: {$c['id']} | TableID: {$c['dining_table_id']} | Status: {$c['status']} | Total: ₺{$c['total']} | ItemsCount: " . count($items) . PHP_EOL;
        foreach ($items as $i) {
            echo "   -> Item ID: {$i['id']} | CheckID: {$i['check_id']} | SyncUUID: " . ($i['sync_uuid'] ?? '') . " | Name: '{$i['product_name']}' | Qty: {$i['quantity']} | UnitPrice: ₺{$i['unit_price']}" . PHP_EOL;
        }
    }
} else {
    echo "API error" . PHP_EOL;
}
