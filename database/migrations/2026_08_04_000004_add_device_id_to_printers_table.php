<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->foreignId('device_id')
                ->nullable()
                ->after('branch_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['branch_id', 'device_id', 'type'], 'printers_branch_device_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropIndex('printers_branch_device_type_index');
            $table->dropConstrainedForeignId('device_id');
        });
    }
};
