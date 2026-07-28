<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table): void {
            $table->unsignedBigInteger('waiter_staff_profile_id')->nullable()->after('waiter_id');
            $table->string('waiter_name')->nullable()->after('waiter_staff_profile_id');
            $table->text('customer_notes')->nullable()->after('waiter_name');
            $table->foreign('waiter_staff_profile_id', 'checks_waiter_staff_fk')
                ->references('id')
                ->on('staff_profiles')
                ->nullOnDelete();
        });

        Schema::table('check_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('added_by_staff_profile_id')->nullable()->after('product_id');
            $table->string('added_by_name')->nullable()->after('added_by_staff_profile_id');
            $table->foreign('added_by_staff_profile_id', 'check_items_added_staff_fk')
                ->references('id')
                ->on('staff_profiles')
                ->nullOnDelete();
        });

        DB::table('role_permissions')
            ->whereIn('role_name', ['Garson', 'Kaptan', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                if (! in_array('garson', $permissions, true)) {
                    $permissions[] = 'garson';
                    DB::table('role_permissions')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('role_name', ['Garson', 'Kaptan', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                $permissions = array_values(array_filter($permissions, fn (mixed $permission): bool => $permission !== 'garson'));
                DB::table('role_permissions')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('check_items', function (Blueprint $table): void {
            $table->dropForeign('check_items_added_staff_fk');
            $table->dropColumn(['added_by_staff_profile_id', 'added_by_name']);
        });
        Schema::table('checks', function (Blueprint $table): void {
            $table->dropForeign('checks_waiter_staff_fk');
            $table->dropColumn(['waiter_staff_profile_id', 'waiter_name', 'customer_notes']);
        });
    }
};
