<?php

namespace App\Services;

final class OfflineSyncRegistry
{
    /**
     * "manifest" resources keep using the specialised POS payload, but still
     * participate in deletion reconciliation. "push_pull" resources use the
     * generic bidirectional payload. "pull_only" data is controlled centrally,
     * while "push_only" is append-only device telemetry.
     *
     * @var array<string, array<string, mixed>>
     */
    private const RESOURCES = [
        'role_permissions' => [
            'mode' => 'pull_only',
            'branch' => false,
            'natural' => ['role_name'],
        ],
        'staff_profiles' => [
            'mode' => 'push_pull',
            'natural' => ['name', 'role'],
        ],
        'halls' => [
            'mode' => 'push_pull',
            'natural' => ['name'],
        ],
        'dining_tables' => [
            'mode' => 'push_pull',
            'natural' => ['code'],
            'references' => ['hall_id' => 'halls'],
        ],
        'categories' => [
            'mode' => 'manifest',
        ],
        'products' => [
            'mode' => 'manifest',
            'references' => ['category_id' => 'categories'],
        ],
        'checks' => [
            'mode' => 'manifest',
            'references' => [
                'dining_table_id' => 'dining_tables',
                'waiter_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'check_items' => [
            'mode' => 'manifest',
            'references' => [
                'check_id' => 'checks',
                'product_id' => 'products',
                'added_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'payments' => [
            'mode' => 'manifest',
            'references' => ['check_id' => 'checks'],
        ],
        'stock_movements' => [
            'mode' => 'manifest',
            'references' => [
                'product_id' => 'products',
                'check_id' => 'checks',
                'check_item_id' => 'check_items',
                'purchase_receipt_id' => 'purchase_receipts',
            ],
        ],
        'settings' => [
            'mode' => 'push_pull',
            'natural' => ['key'],
            'conditionally_hidden_value' => true,
        ],
        'delivery_integrations' => [
            'mode' => 'push_pull',
            'natural' => ['channel'],
            'hidden' => ['api_key', 'api_secret'],
        ],
        'delivery_orders' => [
            'mode' => 'push_pull',
            'natural' => ['channel', 'order_number'],
        ],
        'printers' => [
            'mode' => 'push_pull',
            'natural' => ['name'],
        ],
        'cash_shifts' => [
            'mode' => 'push_pull',
            'natural' => ['shift_number'],
            'references' => [
                'opened_by_staff_profile_id' => 'staff_profiles',
                'closed_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'cash_movements' => [
            'mode' => 'push_pull',
            'references' => [
                'cash_shift_id' => 'cash_shifts',
                'created_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'suppliers' => [
            'mode' => 'push_pull',
            'natural' => ['name'],
            'hidden' => [
                'portal_token',
                'portal_code',
            ],
        ],
        'purchase_orders' => [
            'mode' => 'push_pull',
            'natural' => ['order_number'],
            'references' => [
                'supplier_id' => 'suppliers',
                'created_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'purchase_order_items' => [
            'mode' => 'push_pull',
            'natural' => ['purchase_order_id', 'product_id'],
            'references' => [
                'purchase_order_id' => 'purchase_orders',
                'product_id' => 'products',
            ],
        ],
        'purchase_receipts' => [
            'mode' => 'push_pull',
            'natural' => ['receipt_number'],
            'references' => [
                'purchase_order_id' => 'purchase_orders',
                'received_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'purchase_receipt_items' => [
            'mode' => 'push_pull',
            'natural' => ['purchase_receipt_id', 'purchase_order_item_id'],
            'references' => [
                'purchase_receipt_id' => 'purchase_receipts',
                'purchase_order_item_id' => 'purchase_order_items',
                'product_id' => 'products',
            ],
        ],
        'supplier_quote_requests' => [
            'mode' => 'push_pull',
            'natural' => ['request_number'],
            'references' => [
                'supplier_id' => 'suppliers',
                'reviewed_by_staff_profile_id' => 'staff_profiles',
                'purchase_order_id' => 'purchase_orders',
            ],
        ],
        'supplier_quote_items' => [
            'mode' => 'push_pull',
            'natural' => ['supplier_quote_request_id', 'product_id'],
            'references' => [
                'supplier_quote_request_id' => 'supplier_quote_requests',
                'product_id' => 'products',
            ],
        ],
        'supplier_product_submissions' => [
            'mode' => 'push_pull',
            'natural' => ['submission_number'],
            'references' => [
                'supplier_id' => 'suppliers',
                'reviewed_by_staff_profile_id' => 'staff_profiles',
            ],
        ],
        'supplier_product_submission_items' => [
            'mode' => 'push_pull',
            'natural' => ['supplier_product_submission_id', 'product_name'],
            'references' => [
                'supplier_product_submission_id' => 'supplier_product_submissions',
            ],
        ],
        'login_logs' => [
            'mode' => 'push_only',
        ],
        'audit_logs' => [
            'mode' => 'push_only',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::RESOURCES;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function genericPullResources(): array
    {
        return array_filter(
            self::RESOURCES,
            fn (array $resource): bool => in_array($resource['mode'], ['push_pull', 'pull_only'], true)
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function genericPushResources(): array
    {
        return array_filter(
            self::RESOURCES,
            fn (array $resource): bool => in_array($resource['mode'], ['push_pull', 'push_only'], true)
        );
    }

    public static function has(string $resource): bool
    {
        return isset(self::RESOURCES[$resource]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $resource): array
    {
        return self::RESOURCES[$resource] ?? [];
    }

    public static function branchScoped(string $resource): bool
    {
        return (bool) (self::RESOURCES[$resource]['branch'] ?? true);
    }

    public static function normalizeDeletedType(string $type): ?string
    {
        $aliases = [
            'category' => 'categories',
            'product' => 'products',
            'check' => 'checks',
            'item' => 'check_items',
            'payment' => 'payments',
            'stock_movement' => 'stock_movements',
        ];

        $normalized = $aliases[$type] ?? $type;

        return self::has($normalized) ? $normalized : null;
    }
}
