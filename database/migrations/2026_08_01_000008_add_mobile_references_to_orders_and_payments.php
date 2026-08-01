<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table): void {
            $table->uuid('client_reference')->nullable()->after('check_number');
            $table->unique(['branch_id', 'client_reference'], 'checks_branch_client_reference_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('client_reference')->nullable()->after('payment_method');
            $table->unique(['branch_id', 'client_reference'], 'payments_branch_client_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_branch_client_reference_unique');
            $table->dropColumn('client_reference');
        });

        Schema::table('checks', function (Blueprint $table): void {
            $table->dropUnique('checks_branch_client_reference_unique');
            $table->dropColumn('client_reference');
        });
    }
};
