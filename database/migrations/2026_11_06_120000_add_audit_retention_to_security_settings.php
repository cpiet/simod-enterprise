<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retensi otomatis log audit (hari; 0 = simpan selamanya) dan indeks waktu agar penyaringan, ekspor,
 * serta pembersihan tetap cepat setelah log menumpuk di hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('audit_retention_days')->default(180)->after('session_timeout_minutes');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'audit_logs_created_at_idx');
            $table->index('action', 'audit_logs_action_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_created_at_idx');
            $table->dropIndex('audit_logs_action_idx');
        });

        Schema::table('security_settings', function (Blueprint $table) {
            $table->dropColumn('audit_retention_days');
        });
    }
};
