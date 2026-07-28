<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('suppliers', 'portal_enabled')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->boolean('portal_enabled')->default(false)->after('is_active');
            });
        }
        if (! Schema::hasColumn('suppliers', 'portal_token_hash')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->char('portal_token_hash', 64)->nullable()->after('portal_enabled');
            });
        }
        if (! Schema::hasColumn('suppliers', 'portal_token')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->text('portal_token')->nullable()->after('portal_token_hash');
            });
        }
        if (! Schema::hasColumn('suppliers', 'portal_code_hash')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->char('portal_code_hash', 64)->nullable()->after('portal_token');
            });
        }
        if (! Schema::hasColumn('suppliers', 'portal_code')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->text('portal_code')->nullable()->after('portal_code_hash');
            });
        }
        if (! Schema::hasColumn('suppliers', 'portal_credentials_generated_at')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->timestamp('portal_credentials_generated_at')->nullable()->after('portal_code');
            });
        }
        if (! Schema::hasIndex('suppliers', 'suppliers_portal_token_hash_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique('portal_token_hash');
            });
        }
        if (! Schema::hasIndex('suppliers', 'suppliers_portal_code_hash_unique')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique('portal_code_hash');
            });
        }

        if (! Schema::hasTable('supplier_product_submissions')) {
            Schema::create('supplier_product_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('reviewed_by_staff_profile_id')->nullable();
                $table->string('submission_number')->unique();
                $table->string('status', 24)->default('pending');
                $table->string('contact_name');
                $table->string('contact_email')->nullable();
                $table->string('contact_phone', 32)->nullable();
                $table->text('supplier_notes')->nullable();
                $table->string('submitted_ip', 45)->nullable();
                $table->string('submitted_user_agent', 500)->nullable();
                $table->timestamp('submitted_at');
                $table->string('reviewed_by_name')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('reviewed_by_staff_profile_id', 'sps_reviewed_staff_fk')
                    ->references('id')
                    ->on('staff_profiles')
                    ->nullOnDelete();
                $table->index(['branch_id', 'status', 'submitted_at'], 'sps_branch_status_date_idx');
                $table->index(['supplier_id', 'status', 'submitted_at'], 'sps_supplier_status_date_idx');
            });
        }

        if (! Schema::hasTable('supplier_product_submission_items')) {
            Schema::create('supplier_product_submission_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('supplier_product_submission_id');
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('product_name');
                $table->string('supplier_sku')->nullable();
                $table->string('barcode', 64)->nullable();
                $table->string('brand')->nullable();
                $table->string('unit', 32);
                $table->string('package_description')->nullable();
                $table->decimal('unit_price', 14, 4);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('minimum_order_quantity', 14, 3)->default(1);
                $table->unsignedSmallInteger('delivery_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('supplier_product_submission_id', 'spsi_submission_fk')
                    ->references('id')
                    ->on('supplier_product_submissions')
                    ->cascadeOnDelete();
                $table->index(['branch_id', 'product_name'], 'spsi_branch_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_submission_items');
        Schema::dropIfExists('supplier_product_submissions');

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['portal_token_hash']);
            $table->dropUnique(['portal_code_hash']);
            $table->dropColumn([
                'portal_enabled',
                'portal_token_hash',
                'portal_token',
                'portal_code_hash',
                'portal_code',
                'portal_credentials_generated_at',
            ]);
        });
    }
};
