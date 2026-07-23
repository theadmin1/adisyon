<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained('halls')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('capacity')->default(4);
            $table->integer('occupant_count')->default(0);
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('kitchen_department')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('check_number')->unique();
            $table->integer('guest_count')->default(1);
            $table->string('status')->default('open');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('checks')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_complimentary')->default(false);
            $table->string('complimentary_reason')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('checks')->cascadeOnDelete();
            $table->string('payment_method')->default('nakit');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('check_items');
        Schema::dropIfExists('checks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('halls');
    }
};
