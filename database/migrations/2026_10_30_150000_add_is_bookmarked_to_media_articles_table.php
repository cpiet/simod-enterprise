<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->boolean('is_bookmarked')->default(false)->after('is_flagged');
            $table->index(['polda_id', 'is_bookmarked', 'pub_date'], 'media_articles_cleanup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->dropIndex('media_articles_cleanup_idx');
            $table->dropColumn('is_bookmarked');
        });
    }
};
