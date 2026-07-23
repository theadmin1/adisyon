<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('license_key')->unique();
            $table->string('device_token')->nullable();
            $table->enum('status', ['Active', 'Expired', 'Suspended', 'Trial'])->default('Active');
            $table->dateTime('expires_at')->nullable();
            $table->integer('max_devices')->default(5);
            $table->json('restrictions')->nullable(); // Tarayıcı ve Kiosk kısıtlama kuralları
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
