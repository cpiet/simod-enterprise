<?php

use App\Support\NewsSearch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `title_hash`: sidik judul ternormalisasi untuk menggabungkan berita ganda lintas portal menjadi satu kartu.
 * Indeks (polda_id, pub_date) mempercepat daftar, filter periode, dan pencarian per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->string('title_hash', 32)->nullable()->after('title');
            $table->index(['polda_id', 'title_hash'], 'media_articles_polda_title_hash_idx');
            $table->index(['polda_id', 'pub_date'], 'media_articles_polda_pub_date_idx');
        });

        // Isi ulang untuk data yang sudah ada (ringan: hanya md5 judul, per potongan 500 baris).
        DB::table('media_articles')->orderBy('id')->select(['id', 'title'])->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('media_articles')->where('id', $row->id)->update(['title_hash' => NewsSearch::titleHash($row->title)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_articles', function (Blueprint $table) {
            $table->dropIndex('media_articles_polda_title_hash_idx');
            $table->dropIndex('media_articles_polda_pub_date_idx');
            $table->dropColumn('title_hash');
        });
    }
};
