<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Mutfak Yazıcısı, Kasa Yazıcısı, Bar
            $table->string('type')->default('cashier'); // kitchen, cashier, bar
            $table->string('connection_type')->default('windows_driver'); // windows_driver, network_tcp, serial_com, usb
            $table->string('printer_target')->nullable(); // EPSON TM-T20III, 192.168.1.200:9100, COM3
            $table->integer('paper_width')->default(80); // 80mm or 58mm
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_type'); // kitchen_slip, check_slip, payment_slip, z_report
            $table->string('printer_type')->default('cashier'); // kitchen, cashier, bar
            $table->string('title'); // Masa 3 Mutfak Fişi, Adisyon #QCK-1829
            $table->json('payload'); // Fiş içeriği, kalemler, fiyatlar, garson, masa, ESC/POS komutları
            $table->string('status')->default('pending'); // pending, printing, printed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('printers');
    }
};
