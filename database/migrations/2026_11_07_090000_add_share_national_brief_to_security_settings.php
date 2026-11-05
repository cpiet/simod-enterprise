<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Saklar admin: tampilkan ringkasan isu nasional (laporan Divhumas) di Command Center daerah. Baris global (polda_id null). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_settings', function (Blueprint $table) {
            $table->boolean('share_national_brief')->default(true)->after('audit_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('security_settings', function (Blueprint $table) {
            $table->dropColumn('share_national_brief');
        });
    }
};
