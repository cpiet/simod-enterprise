<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantau Akun lintas platform (Instagram, TikTok, X, YouTube, Halaman Facebook) dan Ringkasan Komentar.
 * Tabel monitored_instagram_accounts diganti nama; baris lama otomatis menjadi platform "instagram".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Setiap langkah dijaga agar aman dijalankan ulang (DDL MySQL tidak transaksional).
        if (Schema::hasTable('monitored_instagram_accounts') && ! Schema::hasTable('monitored_social_accounts')) {
            Schema::rename('monitored_instagram_accounts', 'monitored_social_accounts');
        }

        if (! Schema::hasColumn('monitored_social_accounts', 'platform')) {
            Schema::table('monitored_social_accounts', function (Blueprint $table) {
                $table->string('platform', 20)->default('instagram')->after('polda_id');
                $table->string('username', 100)->change();
                $table->json('profile')->nullable()->after('display_name'); // pengikut/pelanggan bila tersedia
            });
        }

        // Indeks baru DIBUAT DULU: MySQL menolak menghapus indeks lama selama dipakai foreign key polda_id.
        $indexes = collect(Schema::getIndexes('monitored_social_accounts'))->pluck('name');
        if (! $indexes->contains('monitored_social_accounts_polda_id_platform_username_unique')) {
            Schema::table('monitored_social_accounts', fn (Blueprint $table) => $table->unique(['polda_id', 'platform', 'username']));
        }
        if ($indexes->contains('monitored_instagram_accounts_polda_id_username_unique')) {
            Schema::table('monitored_social_accounts', fn (Blueprint $table) => $table->dropUnique('monitored_instagram_accounts_polda_id_username_unique'));
        }

        if (Schema::hasTable('social_comment_digests')) {
            return;
        }
        Schema::create('social_comment_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polda_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('monitored_social_accounts')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('post_url', 500);
            $table->string('post_caption', 500)->nullable();
            $table->json('comments')->nullable();          // komentar asli (maks. 50) + sentimen per komentar
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('positif')->default(0);
            $table->unsignedInteger('netral')->default(0);
            $table->unsignedInteger('negatif')->default(0);
            $table->json('top_words')->nullable();
            $table->string('source_hash', 64)->nullable(); // hash komentar; ringkasan AI tidak diulang bila sama
            $table->text('ai_summary')->nullable();
            $table->string('ai_model', 80)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('summarized_at')->nullable();
            $table->timestamps();
            $table->unique(['polda_id', 'post_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_comment_digests');
        Schema::table('monitored_social_accounts', function (Blueprint $table) {
            $table->dropUnique(['polda_id', 'platform', 'username']);
            $table->dropColumn(['platform', 'profile']);
        });
        Schema::rename('monitored_social_accounts', 'monitored_instagram_accounts');
        Schema::table('monitored_instagram_accounts', function (Blueprint $table) {
            $table->unique(['polda_id', 'username']);
        });
    }
};
