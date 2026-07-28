<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('tax_number', 32)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 24)->default('draft');
            $table->string('created_by_name');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'order_date']);
            $table->index(['supplier_id', 'order_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('unit', 32);
            $table->decimal('quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('line_tax', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);
            $table->index(['branch_id', 'product_id']);
        });

        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('supplier_invoice_number')->nullable();
            $table->date('supplier_invoice_date')->nullable();
            $table->string('received_by_name');
            $table->decimal('received_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['branch_id', 'received_at']);
            $table->index(['purchase_order_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('purchase_receipt_id')
                ->nullable()
                ->after('check_item_id')
                ->constrained('purchase_receipts')
                ->nullOnDelete();
        });

        DB::table('role_permissions')
            ->whereIn('role_name', ['Kasa', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                if (! in_array('satinalma', $permissions, true)) {
                    $permissions[] = 'satinalma';
                    DB::table('role_permissions')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('role_name', ['Kasa', 'Yönetici', 'Müdür'])
            ->orderBy('id')
            ->eachById(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true);
                $permissions = is_array($permissions) ? $permissions : [];
                $permissions = array_values(array_filter($permissions, fn (mixed $permission): bool => $permission !== 'satinalma'));
                DB::table('role_permissions')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_receipt_id');
        });

        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
