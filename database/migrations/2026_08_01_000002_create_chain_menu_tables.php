<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chain_menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('chain_menu_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chain_menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->decimal('base_price', 10, 2);
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->string('kitchen_department')->nullable();
            $table->text('description')->nullable();
            $table->longText('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'sku']);
        });

        Schema::create('chain_menu_product_branch', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chain_menu_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['chain_menu_product_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_menu_product_branch');
        Schema::dropIfExists('chain_menu_products');
        Schema::dropIfExists('chain_menu_categories');
    }
};
