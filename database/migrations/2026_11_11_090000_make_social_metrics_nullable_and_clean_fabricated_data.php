<?php

use App\Support\NewsSentiment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data medsos jujur:
 *  1. Metrik keterlibatan & viralitas boleh NULL (= tidak diketahui), bukan 0 dan bukan angka acak.
 *  2. Bersihkan unggahan dari jalur pencarian/sindikasi (prefiks apify_, ondemand_, syndication_) yang dulu diisi
 *     metrik acak, akun resmi tebakan, tagar sisipan, gambar stok Unsplash, dan waktu unggah acak.
 *  3. Hitung ulang sentimennya dengan aturan tunggal NewsSentiment::detectSocial().
 * Data dari sumber lain (mis. Apify Instagram/API ingest) tidak disentuh.
 */
return new class extends Migration
{
    /** Tagar yang dulu disisipkan ke isi unggahan jalur apify_. */
    private const INJECTED_TAGS = [
        '#CekFakta', '#StopHoaks', '#PolriPresisi', '#TertibLantas', '#RaziaKnalpotBrong', '#PolriBerprestasi', '#HumasPolri',
        '#CegahKarhutla', '#MudikAman2026', '#PolisiHumanis', '#Kapolri',
    ];

    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('likes_count')->nullable()->default(null)->change();
            $table->unsignedBigInteger('shares_count')->nullable()->default(null)->change();
            $table->unsignedBigInteger('comments_count')->nullable()->default(null)->change();
            $table->unsignedBigInteger('views_count')->nullable()->default(null)->change();
            $table->unsignedInteger('virality_score')->nullable()->default(null)->change();
        });

        DB::table('social_posts')
            // Pola portabel MySQL/SQLite (backslash bukan escape di SQLite); prefiks dicek ulang di PHP di bawah.
            ->where(fn ($q) => $q->where('post_id', 'like', 'apify%')->orWhere('post_id', 'like', 'ondemand%')->orWhere('post_id', 'like', 'syndication%'))
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $isApify = str_starts_with($row->post_id, 'apify_');
                    $isOnDemand = str_starts_with($row->post_id, 'ondemand_');
                    if (! $isApify && ! $isOnDemand && ! str_starts_with($row->post_id, 'syndication_')) {
                        continue;
                    }
                    $content = (string) $row->content;

                    if ($isApify) {
                        foreach (self::INJECTED_TAGS as $tag) {
                            $content = str_replace(' ' . $tag, '', $content);
                        }
                    } else {
                        // syndication_/ondemand_: satu tagar kata kunci ditempel di akhir isi.
                        $content = preg_replace('/\s#[^\s#]+$/u', '', $content) ?? $content;
                    }
                    $content = trim($content);

                    $update = [
                        'content'        => $content,
                        'likes_count'    => null,
                        'shares_count'   => null,
                        'comments_count' => null,
                        'views_count'    => null,
                        'virality_score' => null,
                        'sentiment'      => NewsSentiment::detectSocial($content),
                    ];

                    if ($row->thumbnail_url && str_contains($row->thumbnail_url, 'images.unsplash.com')) {
                        $update['thumbnail_url'] = null;
                    }

                    // Waktu unggah jalur apify_/ondemand_ dulu acak; syndication_ memakai tanggal terbit sumber.
                    if ($isApify || $isOnDemand) {
                        $update['posted_at'] = null;
                    }

                    // Penulis jalur apify_/ondemand_ selalu tebakan (akun resmi dari kata di teks, "@judul_..." dari judul,
                    // "@warga_xxx" acak): ganti dengan akun dari URL bila ada, selain itu nama situs.
                    if ($isApify || $isOnDemand) {
                        $handle = $this->handleFromUrl((string) $row->post_url);
                        $update['author_handle'] = $handle;
                        $update['author_name'] = mb_substr($handle !== '' ? $handle : (parse_url((string) $row->post_url, PHP_URL_HOST) ?: 'Sumber tidak diketahui'), 0, 150);
                        $update['is_kol'] = false;
                        $update['kol_category'] = null;
                    }

                    DB::table('social_posts')->where('id', $row->id)->update($update);
                }
            });
    }

    public function down(): void
    {
        // Data yang dibersihkan memang karangan; tidak dipulihkan. Kolom dikembalikan ke NOT NULL default 0.
        foreach (['likes_count', 'shares_count', 'comments_count', 'views_count', 'virality_score'] as $col) {
            DB::table('social_posts')->whereNull($col)->update([$col => 0]);
        }
        Schema::table('social_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('likes_count')->default(0)->change();
            $table->unsignedBigInteger('shares_count')->default(0)->change();
            $table->unsignedBigInteger('comments_count')->default(0)->change();
            $table->unsignedBigInteger('views_count')->default(0)->change();
            $table->unsignedInteger('virality_score')->default(0)->change();
        });
    }

    private function handleFromUrl(string $url): string
    {
        if (preg_match('/(?:instagram\.com|tiktok\.com\/@|x\.com|twitter\.com|youtube\.com\/@)\/?@?([a-zA-Z0-9_.]+)/i', $url, $m)
            && ! in_array(strtolower($m[1]), ['p', 'reel', 'reels', 'tv', 'explore', 'video', 'watch', 'status', 'channel', 'shorts', 'search', 'hashtag', 'i'], true)) {
            return '@' . strtolower($m[1]);
        }

        return '';
    }
};
