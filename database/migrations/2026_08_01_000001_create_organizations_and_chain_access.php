<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_branch', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['organization_id', 'branch_id']);
            $table->unique('branch_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->string('chain_role')->nullable()->after('is_admin');
        });

        Schema::create('chain_user_branch', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_user_branch');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('chain_role');
        });

        Schema::dropIfExists('organization_branch');
        Schema::dropIfExists('organizations');
    }
};
