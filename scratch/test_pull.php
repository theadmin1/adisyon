<?php
use Illuminate\Support\Facades\Http;

$apiKey = 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c';
$apiUrl = 'https://adisyon.synaptropic.com/api/v1/sync/pull';

echo "Testing GET request to: $apiUrl\n";
try {
    $r = Http::withoutVerifying()->timeout(15)->withHeaders([
        'X-Device-Api-Key' => $apiKey,
        'Accept' => 'application/json',
    ])->get($apiUrl);

    echo "HTTP Status Code: " . $r->status() . "\n";
    echo "Is Successful: " . ($r->successful() ? 'YES' : 'NO') . "\n";
    echo "Response Body (first 300 chars):\n" . substr($r->body(), 0, 300) . "\n";
} catch (\Throwable $e) {
    echo "EXCEPTION OCCURRED: " . $e->getMessage() . "\n";
}
