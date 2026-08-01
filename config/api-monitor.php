<?php

return [
    'enabled' => env('API_TRAFFIC_MONITOR_ENABLED', true),
    'retention_days' => max(1, (int) env('API_TRAFFIC_RETENTION_DAYS', 7)),
    'max_payload_bytes' => max(1024, (int) env('API_TRAFFIC_MAX_PAYLOAD_BYTES', 32768)),
    'poll_interval_ms' => max(750, (int) env('API_TRAFFIC_POLL_INTERVAL_MS', 1500)),
];
