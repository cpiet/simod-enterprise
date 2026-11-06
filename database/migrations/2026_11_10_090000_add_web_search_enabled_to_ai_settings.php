<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SADEWA: izinkan pencarian internet (Google Search) bila data SIMOD tidak memuat jawaban. Dapat dimatikan admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->boolean('web_search_enabled')->default(true)->after('daily_user_limit');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('web_search_enabled');
        });
    }
};
