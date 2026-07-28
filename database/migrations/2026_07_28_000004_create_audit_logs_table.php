<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('actor_user_name');
            $table->string('actor_staff_name')->nullable();
            $table->string('action');
            $table->string('category', 32);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 1000)->nullable();
            $table->string('route_name')->nullable();
            $table->uuid('request_id');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['action', 'occurred_at']);
            $table->index(['category', 'occurred_at']);
            $table->index(['branch_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
