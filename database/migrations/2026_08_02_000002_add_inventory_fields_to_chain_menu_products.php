<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_menu_products', function (Blueprint $table): void {
            $table->string('unit', 20)->default('adet')->after('base_price');
            $table->string('item_type', 30)->default('menu_item')->after('unit');
            $table->boolean('track_stock')->default(true)->after('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('chain_menu_products', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'item_type', 'track_stock']);
        });
    }
};
