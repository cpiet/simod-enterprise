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
        Schema::table('media_topics', function (Blueprint $table) {
            $table->json('ai_analysis')->nullable()->after('description');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis');
            $table->unsignedInteger('ai_articles_count')->default(0)->after('ai_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_topics', function (Blueprint $table) {
            $table->dropColumn(['ai_analysis', 'ai_analyzed_at', 'ai_articles_count']);
        });
    }
};
