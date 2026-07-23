<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'stock_quantity')) {
                $table->decimal('stock_quantity', 10, 2)->default(100)->after('price');
            }
            if (!Schema::hasColumn('products', 'min_stock_level')) {
                $table->decimal('min_stock_level', 10, 2)->default(10)->after('stock_quantity');
            }
            if (!Schema::hasColumn('products', 'unit')) {
                $table->string('unit')->default('adet')->after('min_stock_level');
            }
            if (!Schema::hasColumn('products', 'track_stock')) {
                $table->boolean('track_stock')->default(true)->after('unit');
            }
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('check_id')->nullable()->constrained('checks')->nullOnDelete();
            $table->foreignId('check_item_id')->nullable()->constrained('check_items')->nullOnDelete();
            $table->string('type')->default('sale_deduction'); // sale_deduction, cancellation_pending, return_approved, manual_addition, manual_subtraction
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('status')->default('completed'); // completed, pending_approval, approved, rejected
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'min_stock_level', 'unit', 'track_stock']);
        });
    }
};
