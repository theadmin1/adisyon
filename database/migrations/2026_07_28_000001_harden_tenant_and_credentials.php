<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        foreach (['check_items', 'payments', 'stock_movements'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('branches')
                        ->nullOnDelete();
                    $table->index('branch_id', "{$tableName}_branch_id_index");
                });
            }
        }

        if (! Schema::hasColumn('devices', 'api_key_hash')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->string('api_key_hash', 64)->nullable()->unique()->after('api_key');
            });
        }

        if (! Schema::hasColumn('staff_profiles', 'pin_hash')) {
            Schema::table('staff_profiles', function (Blueprint $table): void {
                $table->string('pin_hash')->nullable()->after('pin_code');
            });
        }

        if (! Schema::hasColumn('staff_profiles', 'pin_length')) {
            Schema::table('staff_profiles', function (Blueprint $table): void {
                $table->unsignedTinyInteger('pin_length')->default(4)->after('pin_hash');
            });
        }

        if (Schema::hasTable('delivery_orders')) {
            Schema::table('delivery_orders', function (Blueprint $table): void {
                $table->dropUnique('delivery_orders_order_number_unique');
                $table->unique(
                    ['branch_id', 'channel', 'order_number'],
                    'delivery_orders_branch_channel_order_unique'
                );
            });
        }

        if (Schema::hasTable('delivery_integrations')) {
            Schema::table('delivery_integrations', function (Blueprint $table): void {
                $table->unique(
                    ['branch_id', 'channel'],
                    'delivery_integrations_branch_channel_unique'
                );
            });
        }

        $firstBranchId = DB::table('branches')->orderBy('id')->value('id');

        if ($firstBranchId) {
            DB::table('users')
                ->where(function ($query): void {
                    $query->whereNull('is_admin')->orWhere('is_admin', false);
                })
                ->whereNull('branch_id')
                ->update(['branch_id' => $firstBranchId]);
        }

        DB::table('check_items')
            ->whereNull('branch_id')
            ->update([
                'branch_id' => DB::raw('(SELECT branch_id FROM checks WHERE checks.id = check_items.check_id)'),
            ]);

        DB::table('payments')
            ->whereNull('branch_id')
            ->update([
                'branch_id' => DB::raw('(SELECT branch_id FROM checks WHERE checks.id = payments.check_id)'),
            ]);

        DB::table('stock_movements')
            ->whereNull('branch_id')
            ->update([
                'branch_id' => DB::raw(
                    'COALESCE('.
                    '(SELECT branch_id FROM checks WHERE checks.id = stock_movements.check_id), '.
                    '(SELECT branch_id FROM products WHERE products.id = stock_movements.product_id)'.
                    ')'
                ),
            ]);

        DB::table('devices')
            ->whereNotNull('api_key')
            ->whereNull('api_key_hash')
            ->orderBy('id')
            ->eachById(function ($device): void {
                DB::table('devices')->where('id', $device->id)->update([
                    'api_key_hash' => hash('sha256', $device->api_key),
                    'api_key' => null,
                ]);
            });

        DB::table('staff_profiles')
            ->whereNull('pin_hash')
            ->orderBy('id')
            ->eachById(function ($profile): void {
                DB::table('staff_profiles')->where('id', $profile->id)->update([
                    'pin_hash' => Hash::make((string) $profile->pin_code),
                    'pin_length' => min(6, max(4, strlen(trim((string) $profile->pin_code)))),
                    'pin_code' => 'migrated',
                ]);
            });

        DB::table('delivery_integrations')
            ->orderBy('id')
            ->eachById(function ($integration): void {
                $updates = [];
                foreach (['api_key', 'api_secret'] as $column) {
                    $value = $integration->{$column};
                    if (is_string($value) && $value !== '') {
                        $updates[$column] = Crypt::encryptString($value);
                    }
                }

                if ($updates !== []) {
                    DB::table('delivery_integrations')->where('id', $integration->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        DB::table('delivery_integrations')
            ->orderBy('id')
            ->eachById(function ($integration): void {
                $updates = [];
                foreach (['api_key', 'api_secret'] as $column) {
                    $value = $integration->{$column};
                    if (is_string($value) && $value !== '') {
                        try {
                            $updates[$column] = Crypt::decryptString($value);
                        } catch (Throwable) {
                            //
                        }
                    }
                }

                if ($updates !== []) {
                    DB::table('delivery_integrations')->where('id', $integration->id)->update($updates);
                }
            });

        if (Schema::hasTable('delivery_integrations')) {
            Schema::table('delivery_integrations', function (Blueprint $table): void {
                $table->dropUnique('delivery_integrations_branch_channel_unique');
            });
        }

        if (Schema::hasTable('delivery_orders')) {
            Schema::table('delivery_orders', function (Blueprint $table): void {
                $table->dropUnique('delivery_orders_branch_channel_order_unique');
                $table->unique('order_number');
            });
        }

        if (Schema::hasColumn('staff_profiles', 'pin_hash')) {
            Schema::table('staff_profiles', function (Blueprint $table): void {
                $table->dropColumn('pin_hash');
            });
        }

        if (Schema::hasColumn('staff_profiles', 'pin_length')) {
            Schema::table('staff_profiles', function (Blueprint $table): void {
                $table->dropColumn('pin_length');
            });
        }

        if (Schema::hasColumn('devices', 'api_key_hash')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropUnique(['api_key_hash']);
                $table->dropColumn('api_key_hash');
            });
        }

        foreach (['stock_movements', 'payments', 'check_items'] as $tableName) {
            if (Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropIndex("{$tableName}_branch_id_index");
                    $table->dropConstrainedForeignId('branch_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
