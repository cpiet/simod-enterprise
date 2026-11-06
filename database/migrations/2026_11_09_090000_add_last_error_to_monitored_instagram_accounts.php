<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_instagram_accounts', function (Blueprint $table) {
            // Alasan kegagalan sinkron terakhir (teks ringkas yang aman ditampilkan); null bila terakhir berhasil.
            $table->string('last_error', 255)->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('monitored_instagram_accounts', function (Blueprint $table) {
            $table->dropColumn('last_error');
        });
    }
};
