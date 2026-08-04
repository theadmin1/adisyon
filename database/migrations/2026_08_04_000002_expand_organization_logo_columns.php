<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->longText('logo_path')->nullable()->change();
            $table->longText('logo_light_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->change();
            $table->string('logo_light_path')->nullable()->change();
        });
    }
};
