<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('license_id')->nullable()->constrained('licenses')->onDelete('set null');
            $table->string('device_code')->default('KASA-01');
            $table->string('device_guid')->unique();
            $table->string('ip_address')->nullable();
            $table->string('os_info')->nullable();
            $table->enum('status', ['Online', 'Offline', 'Blocked'])->default('Offline');
            $table->timestamp('last_ping_at')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
