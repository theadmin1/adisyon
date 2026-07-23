<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            // Cihazın işi kuyruktan "kaptığı" an. Atomik claim ile mükerrer baskı engellenir.
            $table->timestamp('claimed_at')->nullable()->after('status');
            $table->unsignedTinyInteger('attempts')->default(0)->after('claimed_at');

            $table->index(['branch_id', 'status'], 'print_jobs_branch_status_index');
        });

        Schema::table('printers', function (Blueprint $table) {
            // Fiş satır genişliği (karakter). Boşsa paper_width'ten türetilir: 58mm=32, 80mm=48.
            $table->unsignedSmallInteger('char_width')->nullable()->after('paper_width');
            // ESC/POS kod sayfası. Türkçe için CP857 (n=13) varsayılan.
            $table->string('codepage', 20)->default('cp857')->after('char_width');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex('print_jobs_branch_status_index');
            $table->dropColumn(['claimed_at', 'attempts']);
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['char_width', 'codepage']);
        });
    }
};
