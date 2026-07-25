<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('channel'); // trendyol, yemeksepeti, getir, migros
            $table->string('store_name')->nullable();
            $table->string('store_id')->nullable();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_accept')->default(false);
            $table->timestamps();
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('channel'); // trendyol, yemeksepeti, getir, migros, phone
            $table->string('platform_order_id')->nullable(); // Trendyol/Yemeksepeti order code
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('delivery_address');
            $table->string('address_note')->nullable();
            $table->string('payment_method')->default('online'); // online, cash_on_delivery, pos_on_delivery
            $table->string('payment_status')->default('paid'); // paid, pending
            $table->string('status')->default('new'); // new, preparing, on_the_way, delivered, cancelled
            $table->string('courier_type')->default('restaurant'); // platform, restaurant
            $table->string('courier_name')->nullable();
            $table->string('courier_phone')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->json('items'); // JSON array of ordered items: product_id, name, price, quantity, note
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('delivery_integrations');
    }
};
