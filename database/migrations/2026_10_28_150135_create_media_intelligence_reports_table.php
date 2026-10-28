<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_intelligence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('report_time', 50)->default('19.03 WITA');
            $table->string('title')->default('LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK');
            $table->string('snapshot_national_status', 50)->default('KUNING - WASPADA');
            $table->text('snapshot_national_desc')->nullable();
            $table->string('snapshot_sulbar_status', 50)->default('MERAH - PRIORITAS TINGGI');
            $table->text('snapshot_sulbar_desc')->nullable();
            $table->text('top_concern')->nullable();
            $table->longText('executive_brief')->nullable();
            $table->text('core_decision')->nullable();
            $table->json('sentiment_national')->nullable();
            $table->json('sentiment_sulbar')->nullable();
            $table->json('priority_responses')->nullable();
            $table->json('national_issues')->nullable();
            $table->json('sulbar_issues')->nullable();
            $table->json('early_warnings')->nullable();
            $table->json('strategic_contents')->nullable();
            $table->json('agenda_setting')->nullable();
            $table->json('recommendations')->nullable();
            $table->longText('raw_input')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_intelligence_reports');
    }
};
