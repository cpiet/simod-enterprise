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
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            
            $table->string('platform', 30); // x, tiktok, instagram, youtube
            $table->string('author_name', 150);
            $table->string('author_handle', 100);
            $table->string('author_avatar', 500)->nullable();
            $table->boolean('is_kol')->default(false); // Key Opinion Leader / Tokoh Kritis
            $table->string('kol_category', 100)->nullable(); // Aktivis Hukum, LBH, Jurnalis Investigasi, Pengamat, Warga Viral
            
            $table->string('post_id', 150)->nullable()->index();
            $table->text('content');
            $table->string('post_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            
            // Metrics
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            
            // Intelligence Assessment
            $table->enum('sentiment', ['positif', 'netral', 'negatif'])->default('netral');
            $table->unsignedInteger('virality_score')->default(0); // 0 - 100
            $table->boolean('is_bookmarked')->default(false);
            
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            
            $table->index(['polda_id', 'platform']);
            $table->index(['polda_id', 'sentiment']);
            $table->index(['polda_id', 'is_kol']);
            $table->index(['polda_id', 'virality_score']);
        });

        Schema::create('social_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->unique()->constrained('poldas')->cascadeOnDelete();
            $table->string('apify_api_token', 255)->nullable();
            $table->text('keywords_tracked')->nullable(); // JSON or comma-separated
            $table->string('sync_frequency', 50)->default('every_3_hours');
            $table->boolean('auto_sync_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_settings');
        Schema::dropIfExists('social_posts');
    }
};
