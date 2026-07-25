<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $middleware = new App\Http\Middleware\EnsureDeviceApiKey();
    $req = Illuminate\Http\Request::create('/api/v1/sync/pull', 'GET');
    $req->headers->set('X-Device-Api-Key', 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c');

    $res = $middleware->handle($req, function ($request) {
        $controller = new App\Http\Controllers\Api\SyncApiController();
        return $controller->pullSyncData($request);
    });

    echo "MIDDLEWARE & CONTROLLER SUCCESS:\n";
    echo json_encode($res->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo "MIDDLEWARE EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
