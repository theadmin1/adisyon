<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_traffic_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('restaurant_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('staff_name')->nullable();
            $table->string('method', 10);
            $table->string('path', 1000);
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('request_size')->default(0);
            $table->unsignedInteger('response_size')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['branch_id', 'occurred_at']);
            $table->index(['status_code', 'occurred_at']);
            $table->index(['route_name', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_traffic_logs');
    }
};
