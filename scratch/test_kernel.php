<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$req = Illuminate\Http\Request::create('/api/v1/sync/pull', 'GET');
$req->headers->set('X-Device-Api-Key', 'dev_sec_s5DfKmYhRY33qINC0L3ZaPy5bcPxUKsQwBLTI63c');
$res = $kernel->handle($req);
echo $res->getContent();
