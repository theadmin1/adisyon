<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->string('qr_token', 40)->nullable()->unique()->after('code');
        });

        DB::table('dining_tables')->whereNull('qr_token')->orderBy('id')->eachById(function ($table): void {
            DB::table('dining_tables')->where('id', $table->id)->update([
                'qr_token' => Str::lower(Str::random(32)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
