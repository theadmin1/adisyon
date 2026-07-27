<?php
use Illuminate\Support\Facades\DB;

echo "=== SQLITE LİSANSLAR ===\n";
$sqliteLicenses = DB::connection('sqlite')->table('licenses')->get();
foreach ($sqliteLicenses as $l) {
    echo "ID: {$l->id} | Key: {$l->license_key} | Status: {$l->status} | MaxDevices: {$l->max_devices} | ExpiresAt: " . ($l->expires_at ?? 'NULL') . "\n";
}

echo "\n=== SQLITE CİHAZLAR ===\n";
$sqliteDevices = DB::connection('sqlite')->table('devices')->get();
foreach ($sqliteDevices as $d) {
    echo "ID: {$d->id} | GUID: {$d->device_guid} | Code: {$d->device_code} | Key: {$d->api_key}\n";
}
