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
        Schema::create('social_investigation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->string('title');
            $table->text('keywords');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('high'); // urgent, high, medium, low
            $table->string('status', 20)->default('active'); // active, archived
            $table->json('platforms')->nullable(); // ['x', 'tiktok', 'instagram', 'youtube', 'facebook']
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_engagements')->default(0);
            $table->json('sentiment_breakdown')->nullable(); // {"positif": X, "netral": Y, "negatif": Z}
            $table->text('intelligence_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();

            $table->index(['polda_id', 'status']);
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable()->after('polres_id')->constrained('social_investigation_cases')->nullOnDelete();
            $table->index('case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
            $table->dropColumn('case_id');
        });

        Schema::dropIfExists('social_investigation_cases');
    }
};

