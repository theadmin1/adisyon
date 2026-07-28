<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Every business record that can exist on the offline terminal receives a
     * stable cross-database identity and a local dirty flag.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'role_permissions',
        'staff_profiles',
        'halls',
        'dining_tables',
        'categories',
        'products',
        'checks',
        'check_items',
        'payments',
        'stock_movements',
        'settings',
        'delivery_integrations',
        'delivery_orders',
        'printers',
        'cash_shifts',
        'cash_movements',
        'suppliers',
        'purchase_orders',
        'purchase_order_items',
        'purchase_receipts',
        'purchase_receipt_items',
        'supplier_quote_requests',
        'supplier_quote_items',
        'supplier_product_submissions',
        'supplier_product_submission_items',
        'login_logs',
        'audit_logs',
    ];

    /**
     * These columns were introduced by the original offline sync migration and
     * must not be removed if this migration is rolled back.
     *
     * @var array<int, string>
     */
    private array $preexistingSyncTables = [
        'categories',
        'products',
        'checks',
        'check_items',
        'payments',
        'stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'sync_uuid')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('sync_uuid', 64)->nullable()->unique();
                });
            }

            if (! Schema::hasColumn($tableName, 'is_synced')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->boolean('is_synced')->default(true)->index();
                });
            }

            DB::table($tableName)
                ->where(fn ($query) => $query->whereNull('sync_uuid')->orWhere('sync_uuid', ''))
                ->orderBy('id')
                ->eachById(function (object $record) use ($tableName): void {
                    DB::table($tableName)->where('id', $record->id)->update([
                        'sync_uuid' => (string) Str::uuid(),
                        'is_synced' => true,
                    ]);
                });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName)
                || in_array($tableName, $this->preexistingSyncTables, true)) {
                continue;
            }

            $columns = array_values(array_filter(
                ['sync_uuid', 'is_synced'],
                fn (string $column): bool => Schema::hasColumn($tableName, $column)
            ));

            if ($columns !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
