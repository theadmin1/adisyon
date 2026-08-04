<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('send_to_kitchen')->default(true)->after('kitchen_department');
        });

        Schema::table('chain_menu_products', function (Blueprint $table): void {
            $table->boolean('send_to_kitchen')->default(true)->after('kitchen_department');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('send_to_kitchen');
        });

        Schema::table('chain_menu_products', function (Blueprint $table): void {
            $table->dropColumn('send_to_kitchen');
        });
    }
};
