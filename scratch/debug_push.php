<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

echo "=== DEBUG PUSH REQUEST ===\n";

$testUuid = (string) Str::uuid();
$testName = "Debug Ürün " . rand(1000, 9999);

$pushUrl = 'https://adisyon.synaptropic.com/api/v1/sync/push';
$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';

$payload = [
    'batch_id' => 'BATCH-' . time(),
    'checks' => [],
    'check_items' => [],
    'payments' => [],
    'stock_movements' => [],
    'categories' => [],
    'products' => [
        [
            'sync_uuid' => $testUuid,
            'category_id' => 1,
            'name' => $testName,
            'price' => 123.45,
            'is_active' => true,
        ]
    ],
    'deleted_products' => [],
    'deleted_categories' => [],
];

echo "Sending POST request to: {$pushUrl}...\n";
$r = Http::withoutVerifying()->timeout(15)->withHeaders([
    'X-Device-Api-Key' => $apiKey,
    'Accept' => 'application/json',
])->post($pushUrl, $payload);

echo "HTTP Status Code: " . $r->status() . "\n";
echo "Response Body:\n" . $r->body() . "\n";
