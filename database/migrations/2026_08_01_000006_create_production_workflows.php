<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('output_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->decimal('base_servings', 12, 3)->default(1);
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('production_recipe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 20);
            $table->timestamps();
            $table->unique(['production_recipe_id', 'ingredient_product_id'], 'recipe_ingredient_unique');
        });

        Schema::create('production_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('production_recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('workflow_number')->unique();
            $table->string('recipe_name');
            $table->decimal('planned_servings', 12, 3);
            $table->string('status')->default('planned');
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('production_workflow_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('stock_unit', 20);
            $table->decimal('recipe_quantity', 14, 4);
            $table->string('recipe_unit', 20);
            $table->decimal('required_quantity', 14, 4);
            $table->decimal('consumed_quantity', 14, 4);
            $table->decimal('stock_before', 14, 4);
            $table->decimal('stock_after', 14, 4);
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('production_workflow_id')->nullable()->after('purchase_receipt_id')->constrained()->nullOnDelete();
        });

        DB::table('role_permissions')->whereIn('role_name', ['Şef', 'Yönetici', 'Müdür'])->get()->each(function ($role): void {
            $permissions = json_decode($role->permissions, true) ?: [];
            if (! in_array('is-akisi', $permissions, true)) $permissions[] = 'is-akisi';
            DB::table('role_permissions')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions)), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::table('role_permissions')->get()->each(function ($role): void {
            $permissions = array_values(array_filter(json_decode($role->permissions, true) ?: [], fn ($permission) => $permission !== 'is-akisi'));
            DB::table('role_permissions')->where('id', $role->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_workflow_id');
        });
        Schema::dropIfExists('production_workflow_items');
        Schema::dropIfExists('production_workflows');
        Schema::dropIfExists('production_recipe_items');
        Schema::dropIfExists('production_recipes');
    }
};
