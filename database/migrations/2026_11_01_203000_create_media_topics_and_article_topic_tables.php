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
        Schema::create('media_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->string('title');
            $table->text('keywords'); // Comma-separated or newline-separated keywords
            $table->text('description')->nullable(); // Catatan atau arahan pimpinan
            $table->string('priority', 20)->default('high'); // urgent, high, medium, low
            $table->string('status', 20)->default('active'); // active, archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['polda_id', 'status']);
        });

        Schema::create('media_article_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_article_id')->constrained('media_articles')->cascadeOnDelete();
            $table->foreignId('media_topic_id')->constrained('media_topics')->cascadeOnDelete();
            $table->boolean('is_manual')->default(true);
            $table->timestamps();

            $table->unique(['media_article_id', 'media_topic_id'], 'article_topic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_article_topic');
        Schema::dropIfExists('media_topics');
    }
};

