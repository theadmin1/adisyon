<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diji_menu_integrations', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('branch_slugs');
        });
    }

    public function down(): void
    {
        Schema::table('diji_menu_integrations', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
