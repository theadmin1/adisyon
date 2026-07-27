<?php
use Illuminate\Support\Facades\DB;

echo "=== SQLITE KULLANICILARI VE RESTORAN ID'LERİ ===\n\n";

$users = DB::connection('sqlite')->table('users')->get();
foreach ($users as $u) {
    echo "ID: {$u->id}\n";
    echo "Name: {$u->name}\n";
    echo "Email: {$u->email}\n";
    echo "Restaurant ID: " . ($u->restaurant_id ?? 'N/A') . "\n";
    echo "Is Admin: " . ($u->is_admin ?? 0) . "\n";
    echo "-----------------------------------------\n";
}
