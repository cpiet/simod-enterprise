<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peringatan otomatis social listening + pemantauan berkala akun prioritas + status baca per pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monitored_social_accounts', 'auto_monitor')) {
            Schema::table('monitored_social_accounts', function (Blueprint $table) {
                $table->boolean('auto_monitor')->default(false)->after('platform');
            });
        }

        if (! Schema::hasTable('social_alerts')) {
            Schema::create('social_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('polda_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('monitored_social_accounts')->nullOnDelete();
                $table->string('platform', 20)->nullable();
                $table->string('type', 30);          // kamtibmas | topik | lonjakan | komentar_negatif
                $table->string('severity', 10);      // tinggi | sedang
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->string('post_url', 500)->nullable();
                $table->timestamps();
                $table->unique(['polda_id', 'type', 'post_url']);
                $table->index(['polda_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('users', 'alerts_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('alerts_seen_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_alerts');
        Schema::table('monitored_social_accounts', fn (Blueprint $table) => $table->dropColumn('auto_monitor'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('alerts_seen_at'));
    }
};
