<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->timestamp('kitchen_sent_at')->nullable()->after('closed_at');
        });

        Schema::table('check_items', function (Blueprint $table) {
            $table->string('kitchen_status')->default('pending')->after('notes');
            $table->timestamp('sent_to_kitchen_at')->nullable()->after('kitchen_status');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropColumn('kitchen_sent_at');
        });

        Schema::table('check_items', function (Blueprint $table) {
            $table->dropColumn(['kitchen_status', 'sent_to_kitchen_at']);
        });
    }
};
