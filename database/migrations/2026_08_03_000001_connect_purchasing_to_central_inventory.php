<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
            $table->string('inventory_destination', 24)->default('branch')->after('status');
            $table->index(['organization_id', 'inventory_destination', 'status'], 'purchase_orders_central_status_index');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->foreignId('chain_menu_product_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->unique(['purchase_order_id', 'chain_menu_product_id'], 'purchase_order_central_product_unique');
        });

        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->foreignId('chain_menu_product_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('chain_menu_product_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropUnique('purchase_order_central_product_unique');
            $table->dropConstrainedForeignId('chain_menu_product_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_central_status_index');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('inventory_destination');
        });
    }
};
