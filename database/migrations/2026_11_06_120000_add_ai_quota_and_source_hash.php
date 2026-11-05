<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log pemakaian AI untuk batas per pengguna per hari dan ringkasan di Pengaturan AI.
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('polda_id')->nullable()->constrained('poldas')->nullOnDelete();
            $table->string('feature', 30);   // media | social | topic | chat
            $table->string('model', 80)->nullable();
            $table->string('status', 20);    // ok | error | quota | skipped
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['created_at', 'status']);
        });

        Schema::table('ai_settings', function (Blueprint $table) {
            // Batas permintaan AI per pengguna per hari (0 = tanpa batas).
            $table->unsignedSmallInteger('daily_user_limit')->default(40)->after('temperature');
        });

        // Sidik jari data masukan: bila data tidak berubah sejak analisis terakhir, analisis tidak diulang (hemat kuota).
        Schema::table('media_intelligence_reports', function (Blueprint $table) {
            $table->string('source_hash', 40)->nullable()->after('raw_input');
        });
        Schema::table('social_intelligence_reports', function (Blueprint $table) {
            $table->string('source_hash', 40)->nullable()->after('virality_forecast');
        });
    }

    public function down(): void
    {
        Schema::table('social_intelligence_reports', fn (Blueprint $t) => $t->dropColumn('source_hash'));
        Schema::table('media_intelligence_reports', fn (Blueprint $t) => $t->dropColumn('source_hash'));
        Schema::table('ai_settings', fn (Blueprint $t) => $t->dropColumn('daily_user_limit'));
        Schema::dropIfExists('ai_usage_logs');
    }
};
