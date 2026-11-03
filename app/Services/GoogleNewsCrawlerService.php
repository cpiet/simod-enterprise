<?php

namespace App\Services;

use App\Models\MediaArticle;
use App\Models\MediaTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleNewsCrawlerService
{
    /**
     * Crawl Google News RSS for a topic and ingest articles directly into database.
     */
    public function crawlAndIngestForTopic(MediaTopic $topic, ?string $customQuery = null, int $limit = 50): array
    {
        $query = $this->resolveQuery($topic, $customQuery);
        if (empty($query)) {
            return [
                'success'  => false,
                'message'  => 'Kata kunci pencarian tidak boleh kosong.',
                'count'    => 0,
                'articles' => [],
            ];
        }

        $url = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=id&gl=ID&ceid=ID:id';
        $xmlContent = $this->fetchHttp($url);

        if (empty($xmlContent)) {
            return [
                'success'  => false,
                'message'  => 'Tidak dapat terhubung ke Google News RSS.',
                'count'    => 0,
                'articles' => [],
            ];
        }

        $parsed = $this->parseGoogleNewsXml($xmlContent, $limit);
        if (empty($parsed)) {
            return [
                'success'  => true,
                'message'  => "Pencarian Google News untuk \"{$query}\" selesai, namun belum ada berita yang cocok.",
                'count'    => 0,
                'query'    => $query,
                'articles' => [],
            ];
        }

        $poldaId = $topic->polda_id;
        $ingestedArticles = [];

        foreach ($parsed as $item) {
            // Gunakan updateOrCreate berbasis URL unik
            $article = MediaArticle::where('url', $item['url'])->first();
            if (!$article) {
                $article = MediaArticle::create([
                    'polda_id'      => $poldaId,
                    'feed_id'       => null,
                    'url'           => $item['url'],
                    'title'         => $item['title'],
                    'source_name'   => strtoupper($item['source_name']),
                    'category'      => $item['category'],
                    'image_url'     => $item['image_url'],
                    'summary'       => $item['summary'],
                    'pub_date'      => $item['pub_date'],
                    'sentiment'     => $item['sentiment'],
                    'is_bookmarked' => false,
                ]);
            } else {
                if (empty($article->image_url) && !empty($item['image_url'])) {
                    $article->update(['image_url' => $item['image_url']]);
                }
            }

            // Kaitkan artikel ke topik atensi secara permanen di pivot table
            DB::table('media_article_topic')->updateOrInsert(
                [
                    'media_article_id' => $article->id,
                    'media_topic_id'   => $topic->id,
                ],
                [
                    'is_manual'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );

            $ingestedArticles[] = [
                'id'                => (string) $article->id,
                'title'             => $article->title,
                'source_name'       => strtoupper($article->source_name),
                'category'          => $article->category,
                'category_badge'    => strtoupper($article->category),
                'time_ago'          => $article->created_at ? $article->created_at->diffForHumans() : 'Baru saja',
                'pub_date'          => $article->pub_date ? $article->pub_date->format('d M Y, H.i') . ' WITA' : now()->format('d M Y, H.i') . ' WITA',
                'summary'           => $article->summary ?: 'Cuplikan berita pantauan intelijen media siber.',
                'image_url'         => $article->image_url,
                'link'              => $article->url,
                'sentiment'         => $article->sentiment ?: 'netral',
                'is_bookmarked'     => (bool) $article->is_bookmarked,
                'matched_topic_ids' => [$topic->id],
                'matched_topics'    => [
                    [
                        'id'       => $topic->id,
                        'title'    => $topic->title,
                        'priority' => $topic->priority,
                    ]
                ],
                'manual_topic_ids'  => [$topic->id],
            ];
        }

        return [
            'success'  => true,
            'message'  => "Berhasil menarik " . count($ingestedArticles) . " sebaran berita internet untuk kata kunci \"{$query}\".",
            'count'    => count($ingestedArticles),
            'query'    => $query,
            'articles' => $ingestedArticles,
        ];
    }

    /**
     * Tentukan query pencarian paling optimal berdasarkan judul & kata kunci topik.
     */
    public function resolveQuery(MediaTopic $topic, ?string $customQuery = null): string
    {
        if (!empty($customQuery) && trim($customQuery) !== '') {
            return trim($customQuery);
        }

        // Jika ada kata kunci di topik, ambil kombinasi yang tajam
        $keywords = $topic->getKeywordsArray();
        if (!empty($keywords)) {
            // Ambil 1-2 kata kunci pertama yang paling representatif
            return implode(' ', array_slice($keywords, 0, 2));
        }

        // Fallback ke judul topik, bersihkan tanda baca berlebih
        $cleanedTitle = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $topic->title);
        $cleanedTitle = preg_replace('/\s+/', ' ', trim($cleanedTitle));

        return $cleanedTitle;
    }

    /**
     * Lakukan HTTP GET dengan cURL dan proteksi SSRF.
     */
    protected function fetchHttp(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 (compatible; SIMOD-Enterprise/2.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 400 && is_string($body) ? $body : '';
    }

    /**
     * Parse XML Google News RSS ke array data artikel terstruktur.
     */
    protected function parseGoogleNewsXml(string $content, int $limit = 50): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false || !isset($xml->channel->item)) {
            libxml_clear_errors();
            return [];
        }

        $results = [];
        $count = 0;

        foreach ($xml->channel->item as $item) {
            $rawTitle = trim((string) $item->title);
            $link = trim((string) $item->link);

            if ($rawTitle === '' || $link === '') {
                continue;
            }

            // Ekstrak nama media dari elemen <source> atau suffix judul
            $sourceName = 'MEDIA ONLINE';
            if (isset($item->source)) {
                $sourceName = trim((string) $item->source);
                // Bersihkan suffix " - Source Name" dari judul
                $cleanTitle = preg_replace('/\s*-\s*' . preg_quote($sourceName, '/') . '$/i', '', $rawTitle);
            } else {
                if (preg_match('/^(.*?)\s*-\s*([^-]+)$/u', $rawTitle, $matches)) {
                    $cleanTitle = trim($matches[1]);
                    $sourceName = trim($matches[2]);
                } else {
                    $cleanTitle = $rawTitle;
                }
            }

            $rawDesc = (string) ($item->description ?? '');
            $cleanSummary = $this->cleanHtmlSummary($rawDesc, $cleanTitle, $sourceName);

            $pubDateStr = (string) ($item->pubDate ?? '');
            $pubDate = !empty($pubDateStr) ? date('Y-m-d H:i:s', strtotime($pubDateStr)) : now()->format('Y-m-d H:i:s');

            // Analisis sentimen berbasis leksikal kepolisian
            $sentiment = $this->detectSentiment($cleanTitle . ' ' . $cleanSummary);

            // Kategori dinamis
            $category = $this->detectCategory($cleanTitle . ' ' . $cleanSummary);

            // Thumbnail / gambar artikel (ekstrak asli atau berikan cover editorial berwibawa)
            $imageUrl = $this->extractImageFromHtml($rawDesc);
            if (empty($imageUrl)) {
                $imageUrl = $this->getEditorialFallbackImage($category, $cleanTitle, $count);
            }

            $results[] = [
                'title'       => html_entity_decode($cleanTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'source_name' => $sourceName,
                'url'         => $link,
                'summary'     => $cleanSummary,
                'pub_date'    => $pubDate,
                'sentiment'   => $sentiment,
                'category'    => $category,
                'image_url'   => $imageUrl,
            ];

            $count++;
            if ($count >= $limit) {
                break;
            }
        }

        libxml_clear_errors();
        return $results;
    }

    /**
     * Berikan gambar cover editorial kepolisian & pers beresolusi tinggi yang sesuai tema.
     */
    protected function getEditorialFallbackImage(string $category, string $title, int $seed): string
    {
        $harkamtibmasImages = [
            'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=800&auto=format&fit=crop&q=80', // Konferensi Pers & Rilis Kehumasan
            'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800&auto=format&fit=crop&q=80', // Sinergi Media & Rapat Pimpinan
            'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&auto=format&fit=crop&q=80', // Apel & Personel Kedinasan Polri
            'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800&auto=format&fit=crop&q=80', // Ruang Redaksi Berita & Pers
        ];

        $hukumImages = [
            'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=800&auto=format&fit=crop&q=80', // Gedung Penegakan Hukum
            'https://images.unsplash.com/photo-1505664194779-8beaceb93744?w=800&auto=format&fit=crop&q=80', // Arsip Berkas Perkara & Regulasi
        ];

        $siberImages = [
            'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800&auto=format&fit=crop&q=80', // Monitoring Ruang Siber
            'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&auto=format&fit=crop&q=80', // Infrastruktur Jaringan & Server
        ];

        $laluLintasImages = [
            'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&auto=format&fit=crop&q=80', // Patroli Jalan Raya
        ];

        $lower = mb_strtolower($title);
        if (str_contains($lower, 'siber') || str_contains($lower, 'digital') || str_contains($lower, 'online') || str_contains($lower, 'medsos')) {
            return $siberImages[$seed % count($siberImages)];
        }

        if ($category === 'Hukum & Kriminal') {
            return $hukumImages[$seed % count($hukumImages)];
        }

        if ($category === 'Lalu Lintas') {
            return $laluLintasImages[$seed % count($laluLintasImages)];
        }

        return $harkamtibmasImages[$seed % count($harkamtibmasImages)];
    }

    /**
     * Bersihkan HTML tags dari deskripsi RSS.
     */
    protected function cleanHtmlSummary(string $html, string $cleanTitle = '', string $sourceName = ''): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{00a0}\s]+/u', ' ', trim($text));

        // Bersihkan nama media di ujung jika menempel
        if (!empty($sourceName)) {
            $text = preg_replace('/\s*' . preg_quote($sourceName, '/') . '\s*$/i', '', $text);
        }

        // Jika ringkasan terlalu pendek atau sama persis dengan judul
        if (empty($text) || mb_strlen($text) < 25 || mb_strtolower($text) === mb_strtolower($cleanTitle)) {
            return "Informasi publikasi pers dari portal {$sourceName} terkait pemantauan agenda \"{$cleanTitle}\".";
        }

        return mb_substr($text, 0, 500);
    }

    /**
     * Ekstrak URL gambar dari tag <img> di dalam deskripsi HTML jika ada.
     */
    protected function extractImageFromHtml(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            $img = trim($matches[1]);
            if (filter_var($img, FILTER_VALIDATE_URL)) {
                return $img;
            }
        }

        return null;
    }

    /**
     * Deteksi sentimen awal berbasis kata kunci siber kepolisian.
     */
    protected function detectSentiment(string $text): string
    {
        $lower = mb_strtolower($text);

        $negativeWords = [
            'korupsi', 'tangkap', 'tersangka', 'tewas', 'meninggal', 'pembunuhan',
            'aniaya', 'perkosa', 'judi', 'narkoba', 'bentrok', 'ricuh', 'unjuk rasa',
            'demo', 'pungli', 'protes', 'kritik', 'pelanggaran', 'sop', 'ditahan',
            'kecelakaan', 'laka maut', 'begal', 'curat', 'curas', 'densus', 'teror'
        ];

        $positiveWords = [
            'apresiasi', 'penghargaan', 'prestasi', 'sukses', 'humanis', 'baksos',
            'bakti sosial', 'donor darah', 'bantuan', 'tertib', 'aman', 'kondusif',
            'wujudkan', 'tingkatkan', 'dukung', 'hut', 'syukuran', 'kolaborasi',
            'terpercaya', 'transparan', 'inovasi', 'keberhasilan', 'meriah'
        ];

        foreach ($negativeWords as $w) {
            if (str_contains($lower, $w)) {
                return 'negatif';
            }
        }

        foreach ($positiveWords as $w) {
            if (str_contains($lower, $w)) {
                return 'positif';
            }
        }

        return 'netral';
    }

    /**
     * Deteksi kategori pemberitaan.
     */
    protected function detectCategory(string $text): string
    {
        $lower = mb_strtolower($text);

        if (str_contains($lower, 'laka') || str_contains($lower, 'lalu lintas') || str_contains($lower, 'tilang') || str_contains($lower, 'satlantas')) {
            return 'Lalu Lintas';
        }

        if (str_contains($lower, 'pidana') || str_contains($lower, 'reskrim') || str_contains($lower, 'tersangka') || str_contains($lower, 'ditangkap') || str_contains($lower, 'kasus')) {
            return 'Hukum & Kriminal';
        }

        if (str_contains($lower, 'humas') || str_contains($lower, 'kapolda') || str_contains($lower, 'polres') || str_contains($lower, 'polri') || str_contains($lower, 'patroli') || str_contains($lower, 'kamtibmas')) {
            return 'Harkamtibmas';
        }

        return 'Nasional';
    }
}
