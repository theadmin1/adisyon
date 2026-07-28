<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE check_items MODIFY quantity DECIMAL(10,3) NOT NULL DEFAULT 1');
        } else {
            Schema::table('check_items', function (Blueprint $table): void {
                $table->decimal('quantity', 10, 3)->default(1)->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE check_items MODIFY quantity INT NOT NULL DEFAULT 1');
        } else {
            Schema::table('check_items', function (Blueprint $table): void {
                $table->integer('quantity')->default(1)->change();
            });
        }
    }
};
