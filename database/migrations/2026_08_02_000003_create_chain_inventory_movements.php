<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_menu_products',function(Blueprint $table):void{
            $table->decimal('stock_quantity',14,3)->default(0)->after('track_stock');
            $table->decimal('min_stock_level',14,3)->default(0)->after('stock_quantity');
        });
        Schema::create('chain_inventory_movements',function(Blueprint $table):void{
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chain_menu_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type',40);
            $table->decimal('quantity',14,3);
            $table->string('unit',20);
            $table->decimal('stock_before',14,3);
            $table->decimal('stock_after',14,3);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('chain_inventory_movements');
        Schema::table('chain_menu_products',fn(Blueprint $table)=>$table->dropColumn(['stock_quantity','min_stock_level']));
    }
};
