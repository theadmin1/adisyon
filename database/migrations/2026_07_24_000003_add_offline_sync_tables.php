<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Checks tablosuna sync kolonları
        Schema::table('checks', function (Blueprint $table) {
            if (! Schema::hasColumn('checks', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('checks', 'is_synced')) {
                $table->boolean('is_synced')->default(true)->after('status');
            }
            if (! Schema::hasColumn('checks', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('is_synced');
            }
        });

        // 2. CheckItems tablosuna sync kolonları
        Schema::table('check_items', function (Blueprint $table) {
            if (! Schema::hasColumn('check_items', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('check_items', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        // 3. Payments tablosuna sync kolonları
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('payments', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        // 4. StockMovements tablosuna sync kolonları
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('stock_movements', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        // 5. Products tablosuna sync kolonları
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('products', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        // 6. Categories tablosuna sync kolonları
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('categories', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        // 7. Offline Sync Logları Tablosu
        if (! Schema::hasTable('offline_sync_logs')) {
            Schema::create('offline_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('sync_uuid', 64)->index();
                $table->string('payload_type', 32); // check, payment, stock, etc.
                $table->string('status', 20)->default('success'); // success, error, pending
                $table->text('error_message')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_sync_logs');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced']);
        });

        Schema::table('check_items', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced']);
        });

        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced', 'synced_at']);
        });
    }
};
