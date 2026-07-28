<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== SQLITE dining_tables COLUMNS ===\n";
print_r(Schema::connection('sqlite')->getColumnListing('dining_tables'));

echo "\n=== MYSQL dining_tables COLUMNS ===\n";
print_r(Schema::getColumnListing('dining_tables'));
