<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('request_number')->unique();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 24)->default('open');
            $table->string('requested_by_name');
            $table->text('message')->nullable();
            $table->timestamp('expires_at');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->text('supplier_notes')->nullable();
            $table->string('submitted_ip', 45)->nullable();
            $table->string('submitted_user_agent', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_quote_request_id')->constrained('supplier_quote_requests')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('unit', 32);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_tax', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_quote_request_id', 'product_id']);
            $table->index(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quote_items');
        Schema::dropIfExists('supplier_quote_requests');
    }
};
