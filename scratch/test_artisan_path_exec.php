<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$artisanPath = base_path('artisan');
echo "Artisan Path: {$artisanPath}\n";

$cmd = 'cmd /c "php "' . $artisanPath . '" app:sync-local"';
echo "Executing: {$cmd}\n\n";

$out = shell_exec($cmd);
echo "Output:\n{$out}\n";
