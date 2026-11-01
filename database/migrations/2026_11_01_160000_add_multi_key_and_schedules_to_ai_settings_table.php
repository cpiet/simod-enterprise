<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->json('api_keys_pool')->nullable()->after('gemini_api_key');
            $table->string('sync_mode', 50)->default('after_rss')->after('is_auto_analyze');
            $table->integer('interval_minutes')->default(180)->after('sync_mode');
            $table->json('scheduled_times')->nullable()->after('interval_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn(['api_keys_pool', 'sync_mode', 'interval_minutes', 'scheduled_times']);
        });
    }
};
