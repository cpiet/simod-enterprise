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
        // 1. Add missing operational columns to issues
        Schema::table('issues', function (Blueprint $table) {
            if (!Schema::hasColumn('issues', 'report_code')) {
                $table->string('report_code', 50)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('issues', 'location_text')) {
                $table->string('location_text', 255)->nullable()->after('summary');
            }
            if (!Schema::hasColumn('issues', 'occurred_at')) {
                $table->dateTime('occurred_at')->nullable()->after('location_text');
            }
            if (!Schema::hasColumn('issues', 'resolved_note')) {
                $table->text('resolved_note')->nullable()->after('official_clarification');
            }
        });

        // 2. Create media_files table for private & verified evidence storage
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained('poldas')->cascadeOnDelete();
            $table->foreignId('polres_id')->nullable()->constrained('polres')->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('caption')->nullable();
            $table->enum('file_type', ['photo', 'video', 'document'])->default('photo');
            $table->string('file_path', 500)->nullable();
            $table->string('file_url', 500);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 100)->default('image/jpeg');
            $table->enum('review_status', ['approved', 'pending', 'rejected'])->default('approved');
            $table->boolean('is_private')->default(true);

            $table->timestamps();

            $table->index(['polda_id', 'file_type']);
            $table->index(['polda_id', 'review_status']);
        });

        // 3. Create issue_status_history table for append-only audit trail
        Schema::create('issue_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 50);
            $table->string('to_status', 50);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['issue_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_status_history');
        Schema::dropIfExists('media_files');

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['report_code', 'location_text', 'occurred_at', 'resolved_note']);
        });
    }
};
