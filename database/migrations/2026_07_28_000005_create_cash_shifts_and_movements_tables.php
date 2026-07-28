<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('open_branch_key')->nullable()->unique();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('shift_number')->unique();
            $table->string('status', 16)->default('open');
            $table->string('opened_by_name');
            $table->string('closed_by_name')->nullable();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('cash_sales', 14, 2)->nullable();
            $table->decimal('cash_in_total', 14, 2)->nullable();
            $table->decimal('cash_out_total', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->json('payment_totals')->nullable();
            $table->json('denomination_counts')->nullable();
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'opened_at']);
            $table->index(['branch_id', 'closed_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained('cash_shifts')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('created_by_name');
            $table->string('type', 16);
            $table->decimal('amount', 14, 2);
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['cash_shift_id', 'occurred_at']);
            $table->index(['branch_id', 'type', 'occurred_at']);
        });

        DB::table('role_permissions')
            ->whereIn('role_name', ['Kasa', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];

                if (! in_array('kasa', $permissions, true)) {
                    $permissions[] = 'kasa';
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
            ->whereIn('role_name', ['Kasa', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                $permissions = array_values(array_filter($permissions, fn (mixed $permission): bool => $permission !== 'kasa'));

                DB::table('role_permissions')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_shifts');
    }
};
