<?php

return [
    'offline_mode' => env('ADISYON_OFFLINE_MODE', false),
    'table_lock_ttl_seconds' => (int) env('ADISYON_TABLE_LOCK_TTL_SECONDS', 180),
];
