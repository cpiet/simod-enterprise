<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->cascadeOnDelete();
            $table->string('sync_mode', 50)->default('interval'); // 'manual', 'interval', 'scheduled_times'
            $table->integer('interval_minutes')->default(180); // 60, 180, 360, 720, 1440
            $table->json('scheduled_times')->nullable(); // ["06:00", "12:00", "18:00"]
            $table->integer('max_articles_per_feed')->default(25);
            $table->integer('max_retained_articles_per_polda')->default(1000); // safety cap to prevent bloat
            $table->integer('auto_prune_days')->default(90);
            $table->integer('timeout_seconds')->default(8);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 50)->nullable()->default('idle'); // idle, success, running, failed
            $table->text('last_sync_message')->nullable();
            $table->integer('last_articles_count')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rss_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->cascadeOnDelete();
            $table->string('sync_type', 50)->default('automatic'); // automatic, manual_ui, artisan_cli
            $table->string('status', 50)->default('success'); // success, failed, skipped_locked
            $table->integer('feeds_processed')->default(0);
            $table->integer('articles_fetched')->default(0);
            $table->integer('articles_stored')->default(0);
            $table->integer('articles_pruned')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->decimal('memory_usage_mb', 6, 2)->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_sync_logs');
        Schema::dropIfExists('rss_sync_settings');
    }
};

