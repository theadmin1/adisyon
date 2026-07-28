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
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('products', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'sync_uuid')) {
                $table->string('sync_uuid', 64)->nullable();
            }
            if (! Schema::hasColumn('categories', 'is_synced')) {
                $table->boolean('is_synced')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sync_uuid', 'is_synced']);
        });
    }
};
