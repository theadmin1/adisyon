<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deleted_records')) {
            Schema::create('deleted_records', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // product, category, check, item
                $table->string('sync_uuid')->nullable()->index();
                $table->unsignedBigInteger('record_id')->nullable();
                $table->string('name')->nullable();
                $table->boolean('is_synced')->default(false);
                $table->timestamps();
            });
        } elseif (!Schema::hasColumn('deleted_records', 'name')) {
            Schema::table('deleted_records', function (Blueprint $table) {
                $table->string('name')->nullable();
                $table->unsignedBigInteger('record_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_records');
    }
};
