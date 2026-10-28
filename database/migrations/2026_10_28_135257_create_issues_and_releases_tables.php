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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('summary');
            $table->string('category')->default('Harkamtibmas'); // Harkamtibmas, Lalu Lintas, Kriminalitas, Pelayanan Publik, Bencana, Internal Polri
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['monitoring', 'investigasi', 'klarifikasi', 'selesai', 'arsip'])->default('monitoring');
            $table->enum('sentiment', ['positif', 'netral', 'negatif'])->default('netral');
            
            // Media tracking fields
            $table->string('source_media')->nullable(); // Antara, Detik, TikTok, Instagram, X
            $table->string('source_url', 500)->nullable();
            $table->integer('virality_score')->default(0);
            $table->integer('engagement_count')->default(0);
            $table->string('assigned_unit')->nullable(); // Ditreskrimsus, Satlantas, Propam, dll.
            
            // Fact Check / Hoax Clarification
            $table->boolean('is_hoax')->default(false);
            $table->text('fact_check_result')->nullable();
            $table->text('official_clarification')->nullable();
            $table->string('clarification_url', 500)->nullable();
            $table->timestamp('clarified_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['polda_id', 'status']);
            $table->index(['polda_id', 'priority']);
        });

        Schema::create('press_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('title');
            $table->string('press_number')->nullable(); // e.g. B/SP-12/X/HUM.6.1.1/2026
            $table->longText('content');
            $table->json('talking_points')->nullable(); // Array of talking points for spokesperson
            $table->json('dos_and_donts')->nullable();  // Media guidance
            $table->enum('status', ['draft', 'review', 'approved', 'published'])->default('published');
            $table->string('spokesperson_name')->nullable();
            $table->timestamp('published_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['polda_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('press_releases');
        Schema::dropIfExists('issues');
    }
};
