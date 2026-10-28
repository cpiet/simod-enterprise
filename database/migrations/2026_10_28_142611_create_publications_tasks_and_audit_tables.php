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
        // 1. PUBLICATIONS (Multi-Kanal: Tribratanews, IG, TikTok, FB, X, YT)
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('channel', ['tribratanews', 'instagram', 'facebook', 'twitter', 'tiktok', 'youtube', 'siaran_pers'])->default('tribratanews');
            $table->string('title');
            $table->text('content_draft');
            $table->string('url', 500)->nullable();
            $table->enum('approval_status', ['draft_release', 'pending_approval', 'approved', 'rejected', 'published'])->default('published');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();

            $table->index(['polda_id', 'channel']);
            $table->index(['polda_id', 'approval_status']);
        });

        // 2. TASKS & DISPOSISI OPERASIONAL
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('assigned_unit'); // Ditreskrimsus, Ditlantas, Polresta Mamuju, dll.
            $table->string('assigned_officer')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['polda_id', 'status']);
        });

        // 3. TASK UPDATES (Laporan Progres Personel)
        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->string('attachment_url', 500)->nullable();
            $table->timestamps();
        });

        // 4. AUDIT LOGS (Immutable Activity Log)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100); // e.g. login, create_issue, publish_release, clarify_hoax
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['polda_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('task_updates');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('publications');
    }
};
