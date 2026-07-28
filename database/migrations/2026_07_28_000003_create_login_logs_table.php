<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('user_email');
            $table->string('restaurant_id')->nullable();
            $table->string('portal', 32);
            $table->string('guard', 32);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('remember_me')->default(false);
            $table->timestamp('logged_in_at');
            $table->timestamps();

            $table->index(['branch_id', 'logged_in_at']);
            $table->index(['user_id', 'logged_in_at']);
            $table->index(['portal', 'logged_in_at']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
