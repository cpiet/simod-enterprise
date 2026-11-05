<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip dan hapus (tempat sampah) untuk Laporan/Isu:
 *  - `archived_at` + `status_before_archive`: arsip dapat dipulihkan ke tahap semula.
 *  - `deleted_at` + `deleted_by`: hapus lunak; laporan masuk Sampah dan dapat dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('clarified_at');
            $table->string('status_before_archive', 50)->nullable()->after('archived_at');
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->index(['polda_id', 'deleted_at'], 'issues_polda_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex('issues_polda_deleted_idx');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
            $table->dropColumn(['archived_at', 'status_before_archive']);
        });
    }
};
