<?php
use App\Models\User;

echo "=== SERVER USERS ===\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id} | Name: {$u->name} | Email: {$u->email} | RestaurantID: " . ($u->restaurant_id ?? 'NULL') . "\n";
}
