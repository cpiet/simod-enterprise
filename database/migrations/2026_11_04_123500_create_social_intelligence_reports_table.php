<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_intelligence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('report_date');
            $table->string('report_time', 50)->default('12.00 WITA');
            $table->string('title')->default('LAPORAN INTELIJEN MEDIA SOSIAL & SENTIMEN WARGANET');
            $table->integer('posts_count')->default(0);
            $table->string('model_used', 100)->default('SADEWA Social AI');
            $table->integer('latency_ms')->default(0);
            $table->longText('executive_brief')->nullable();
            $table->text('top_concern')->nullable();
            $table->json('sentiment_analysis')->nullable();
            $table->json('buzzer_vs_organic')->nullable();
            $table->json('critical_accounts')->nullable();
            $table->json('cooling_system')->nullable();
            $table->longText('whatsapp_script')->nullable();
            $table->json('counter_narrative')->nullable();
            $table->json('virality_forecast')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_intelligence_reports');
    }
};

