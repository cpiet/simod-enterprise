<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\SocialInvestigationCase;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifySyncService
{
    /**
     * Parse raw token string into unique non-empty tokens.
     */
    public function parseTokens(?string $rawTokens): array
    {
        if (empty($rawTokens)) {
            return [];
        }

        $tokens = preg_split('/[\r\n,;]+/', $rawTokens);
        $clean = [];

        foreach ($tokens as $t) {
            $t = trim($t);
            if (!empty($t) && !in_array($t, $clean)) {
                $clean[] = $t;
            }
        }

        return $clean;
    }

    /**
     * Mask token for safe UI display.
     */
    public function maskToken(string $token): string
    {
        if (strlen($token) <= 14) {
            return $token;
        }

        return substr($token, 0, 10) . '...' . substr($token, -4);
    }

    /**
     * Test connection to Apify API using single or multiple tokens.
     */
    public function testConnection(?string $tokenInput = null): array
    {
        if ($tokenInput !== null) {
            $tokens = $this->parseTokens($tokenInput);
        } else {
            $rawTokens = SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token') 
                ?: env('APIFY_API_TOKEN', '');
            $tokens = $this->parseTokens($rawTokens);
        }

        if (empty($tokens)) {
            return [
                'success' => false,
                'status'  => 'missing_token',
                'message' => 'Token API Apify belum diisi. Masukkan satu atau lebih Personal API Token (dipisahkan koma atau baris baru).',
            ];
        }

        $accounts = [];
        $activeCount = 0;

        foreach ($tokens as $token) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                        'Accept'        => 'application/json',
                    ])
                    ->get('https://api.apify.com/v2/users/me');

                if ($response->successful()) {
                    $userData = $response->json('data') ?? [];
                    $accounts[] = [
                        'token_masked' => $this->maskToken($token),
                        'id'           => $userData['id'] ?? null,
                        'username'     => $userData['username'] ?? 'User',
                        'email'        => $userData['email'] ?? null,
                        'plan'         => $userData['plan']['name'] ?? 'Free Tier ($5/bulan)',
                        'status'       => 'connected',
                    ];
                    $activeCount++;
                } else {
                    $accounts[] = [
                        'token_masked' => $this->maskToken($token),
                        'status'       => 'invalid',
                        'error'        => 'Token tidak valid atau kedaluwarsa (HTTP ' . $response->status() . ')',
                    ];
                }
            } catch (\Throwable $e) {
                $accounts[] = [
                    'token_masked' => $this->maskToken($token),
                    'status'       => 'error',
                    'error'        => 'Koneksi gagal: ' . $e->getMessage(),
                ];
            }
        }

        $isSuccess = $activeCount > 0;
        $msg = $isSuccess
            ? ($activeCount === 1 
                ? "Koneksi ke akun Apify ({$accounts[0]['username']}) berhasil terhubung!" 
                : "Multi-API Pool aktif: {$activeCount} dari " . count($tokens) . " akun Apify berhasil terhubung & siap rotasi!")
            : "Semua token yang dimasukkan tidak dapat terhubung ke server Apify.";

        return [
            'success'       => $isSuccess,
            'status'        => $isSuccess ? 'connected' : 'error',
            'message'       => $msg,
            'total_tokens'  => count($tokens),
            'active_count'  => $activeCount,
            'accounts'      => $accounts,
            'user'          => $accounts[0] ?? null,
        ];
    }

    /**
     * Sync social media posts for a specific Polda using Apify Multi-Platform Multi-Query with Token Failover.
     */
    public function syncPolda(int $poldaId, ?string $tokenInput = null): array
    {
        $polda = Polda::find($poldaId);
        $setting = SocialSetting::where('polda_id', $poldaId)->first();
        
        $rawTokens = $tokenInput 
            ?: ($setting?->apify_api_token 
            ?: (SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
            ?: env('APIFY_API_TOKEN', '')));
        $tokens = $this->parseTokens($rawTokens);

        // Fallback simulation if no token exists
        if (empty($tokens)) {
            if ($setting) {
                $setting->update(['last_synced_at' => now()]);
            }

            return [
                'success'   => true,
                'mode'      => 'simulation',
                'message'   => 'Token Apify belum diisi: radar hanya memakai sindikasi publik (Google News). Tidak ada data buatan yang ditampilkan. Masukkan Personal API Token Apify di Pengaturan Mesin AI untuk cakupan lebih luas.',
                'new_posts' => 0,
            ];
        }

        // Prepare multi-query for all 4 platforms & specific target accounts
        $keywordsStr = $setting?->keywords_tracked ?: 'poldasulbar, polda sulbar, polresta mamuju';
        $keywords = array_filter(array_map('trim', explode(',', $keywordsStr)));
        $primary = $keywords[0] ?? 'polda sulbar';

        // 4 Parallel Queries guaranteeing data for: YouTube, TikTok, Instagram, and X
        $queries = [
            "site:youtube.com \"{$primary}\"",
            "site:tiktok.com \"{$primary}\"",
            "site:instagram.com \"{$primary}\"",
            "site:twitter.com OR site:x.com \"{$primary}\"",
        ];

        // Include specific targeted accounts if specified by the user
        $accountsStr = $setting?->accounts_tracked ?: '';
        $targetAccounts = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $accountsStr)));
        foreach (array_slice($targetAccounts, 0, 3) as $acc) {
            $cleanAcc = ltrim($acc, '@');
            if (!empty($cleanAcc)) {
                $queries[] = "site:instagram.com/{$cleanAcc} OR site:tiktok.com/@{$cleanAcc} OR site:youtube.com/@{$cleanAcc} OR site:x.com/{$cleanAcc}";
            }
        }

        $input = [
            'queries'          => implode("\n", $queries),
            'maxPagesPerQuery' => 1,
            'resultsPerPage'   => 5,
            'countryCode'      => 'id',
            'languageCode'     => 'id',
        ];

        $actorId = 'apify~google-search-scraper';
        $response = null;
        $successfulToken = null;
        $failoverUsed = false;
        $lastError = '';

        // Token failover loop
        foreach ($tokens as $idx => $token) {
            try {
                $res = Http::withToken($token)
                    ->timeout(60)
                    ->post("https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items?token={$token}", $input);

                if ($res->successful()) {
                    $response = $res;
                    $successfulToken = $token;
                    if ($idx > 0) {
                        $failoverUsed = true;
                    }
                    break;
                } else {
                    $lastError = "Token #".($idx+1)." gagal (HTTP {$res->status()}): " . ($res->json('error.message') ?? 'Error');
                    Log::warning('Apify token failed, attempting next token in pool', ['index' => $idx, 'error' => $lastError]);
                }
            } catch (\Throwable $e) {
                $lastError = "Token #".($idx+1)." exception: " . $e->getMessage();
                Log::warning('Apify token connection exception, moving to next token', ['error' => $e->getMessage()]);
            }
        }

        if (!$response || !$response->successful()) {
            return [
                'success' => false,
                'mode'    => 'error',
                'message' => 'Gagal menarik data dari seluruh API Apify dalam pool. ' . $lastError,
            ];
        }

        // Process response items
        $data = $response->json();
        $pages = is_array($data) ? $data : [];
        $pulledCount = 0;
        $platformCounts = ['youtube' => 0, 'tiktok' => 0, 'instagram' => 0, 'x' => 0];

        foreach ($pages as $page) {
            $searchTerm = $page['searchQuery']['term'] ?? '';
            $items = $page['organicResults'] ?? [];

            // Determine base platform from search term
            $basePlatform = 'x';
            if (stripos($searchTerm, 'site:youtube.com') !== false) {
                $basePlatform = 'youtube';
            } elseif (stripos($searchTerm, 'site:tiktok.com') !== false) {
                $basePlatform = 'tiktok';
            } elseif (stripos($searchTerm, 'site:instagram.com') !== false) {
                $basePlatform = 'instagram';
            }

            foreach ($items as $idx => $item) {
                $url = $item['url'] ?? '';
                $websiteTitle = $item['websiteTitle'] ?? '';
                $title = $item['title'] ?? '';
                $desc = $item['description'] ?? '';

                if (empty($title) && empty($desc)) {
                    continue;
                }

                // Detect exact platform
                $platform = $basePlatform;
                if (stripos($url, 'youtube.com') !== false || stripos($url, 'youtu.be') !== false || stripos($websiteTitle, 'youtube') !== false) {
                    $platform = 'youtube';
                } elseif (stripos($url, 'tiktok.com') !== false || stripos($websiteTitle, 'tiktok') !== false) {
                    $platform = 'tiktok';
                } elseif (stripos($url, 'instagram.com') !== false || stripos($websiteTitle, 'instagram') !== false) {
                    $platform = 'instagram';
                } elseif (stripos($url, 'twitter.com') !== false || stripos($url, 'x.com') !== false) {
                    $platform = 'x';
                }


                if ($url === '') {
                    continue; // tanpa tautan sumber, hasil tidak dapat diverifikasi
                }

                // Penulis HANYA dari data nyata: @handle tertulis di judul/URL, atau nama akun di path URL.
                // Tanpa itu dipakai nama situs/judul situs apa adanya; tidak pernah mengatribusikan ke akun resmi.
                $handle = '';
                if (preg_match('/(?:instagram\.com|tiktok\.com\/@|x\.com|twitter\.com|youtube\.com\/@)\/?@?([a-zA-Z0-9_.]+)/i', $url, $uMatches)
                    && !in_array(strtolower($uMatches[1]), ['p', 'reel', 'reels', 'tv', 'explore', 'video', 'watch', 'status', 'channel', 'shorts', 'search', 'hashtag', 'i'], true)) {
                    $handle = '@' . strtolower($uMatches[1]);
                } elseif (preg_match('/\(@([a-zA-Z0-9_.]+)\)/', $title, $m)) {
                    $handle = '@' . $m[1];
                }
                $authorName = $handle !== '' ? $handle : (trim($websiteTitle) ?: (parse_url($url, PHP_URL_HOST) ?: 'Sumber tidak diketahui'));

                // Sentimen: aturan tunggal SIMOD (NewsSentiment, varian medsos).
                $content = !empty($desc) ? $title . ' — ' . $desc : $title;
                $sentiment = \App\Support\NewsSentiment::detectSocial($content);

                $postId = 'apify_' . md5($url);

                // Thumbnail hanya bila nyata (ID video YouTube); tidak ada gambar stok.
                $thumbnailUrl = null;
                if ($platform === 'youtube' && preg_match('/(?:v=|\/embed\/|\/shorts\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $ytMatches)) {
                    $thumbnailUrl = "https://img.youtube.com/vi/{$ytMatches[1]}/hqdefault.jpg";
                }

                $postedAt = null;
                if (!empty($item['date'])) {
                    try {
                        $postedAt = \Carbon\Carbon::parse($item['date']);
                    } catch (\Throwable) {
                        $postedAt = null;
                    }
                }

                SocialPost::updateOrCreate(
                    [
                        'polda_id' => $poldaId,
                        'post_id'  => $postId,
                    ],
                    [
                        'platform'       => $platform,
                        'author_name'    => mb_substr($authorName, 0, 150),
                        'author_handle'  => $handle,
                        'is_kol'         => false,
                        'kol_category'   => null,
                        'content'        => $content,
                        'post_url'       => $url,
                        'thumbnail_url'  => $thumbnailUrl,
                        // Hasil pencarian Google tidak membawa metrik keterlibatan: null = tidak diketahui.
                        'likes_count'    => null,
                        'shares_count'   => null,
                        'comments_count' => null,
                        'views_count'    => null,
                        'sentiment'      => $sentiment,
                        'virality_score' => null,
                        'posted_at'      => $postedAt,
                    ]
                );

                $pulledCount++;
                if (isset($platformCounts[$platform])) {
                    $platformCounts[$platform]++;
                }
            }
        }

        if ($setting) {
            $setting->update(['last_synced_at' => now()]);
        }

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'apify.sync',
            'subject_type' => 'social_post',
            'subject_id'   => (string) $poldaId,
            'ip_address'   => request()->ip() ?? '127.0.0.1',
            'user_agent'   => request()->userAgent() ?? 'CLI/System',
            'metadata'     => [
                'keywords'        => $keywords,
                'pulled_count'    => $pulledCount,
                'platform_counts' => $platformCounts,
                'failover_used'   => $failoverUsed,
            ],
            'created_at'   => now(),
        ]);

        $failoverMsg = $failoverUsed ? " (Otomatis beralih ke token cadangan)" : "";
        $platformBreakdown = "YouTube: {$platformCounts['youtube']}, TikTok: {$platformCounts['tiktok']}, Instagram: {$platformCounts['instagram']}, 𝕏: {$platformCounts['x']}";

        return [
            'success'   => true,
            'mode'      => 'live_apify',
            'message'   => "Sinkronisasi Apify sukses! Berhasil menarik {$pulledCount} postingan live lintas platform ({$platformBreakdown}){$failoverMsg}.",
            'new_posts' => $pulledCount,
            'platform_counts' => $platformCounts,
        ];
    }

    /**
     * Crawl specific on-demand keyword across designated platforms using Apify token pool or smart ingestion.
     */
    public function crawlKeyword(string $keyword, array $platforms, int $poldaId, int $maxItems = 20, ?int $caseId = null): array
    {
        $cleanKeyword = trim($keyword);
        if (empty($cleanKeyword)) {
            return [
                'success' => false,
                'message' => 'Kata kunci pencarian tidak boleh kosong.',
            ];
        }

        if (empty($platforms)) {
            $platforms = ['x', 'tiktok', 'instagram', 'youtube', 'facebook'];
        }

        $polda = Polda::find($poldaId) ?: Polda::first();
        $poldaId = $polda ? $polda->id : 1;

        // Ensure persistent Investigation Case exists
        $case = null;
        if ($caseId) {
            $case = SocialInvestigationCase::find($caseId);
        }
        if (!$case) {
            $case = SocialInvestigationCase::firstOrCreate(
                [
                    'polda_id' => $poldaId,
                    'title'    => $cleanKeyword,
                ],
                [
                    'keywords'    => $cleanKeyword,
                    'platforms'   => $platforms,
                    'status'      => 'active',
                    'priority'    => 'high',
                    'created_by'  => auth()->id(),
                ]
            );
        }

        if ($case && $case->status !== 'active') {
            $case->update(['status' => 'active']);
        }
        $caseId = $case ? $case->id : null;

        $setting = SocialSetting::where('polda_id', $poldaId)->first();
        
        $rawTokens = $setting?->apify_api_token 
            ?: (SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
            ?: env('APIFY_API_TOKEN', ''));
        $tokens = $this->parseTokens($rawTokens);

        $pulledCount = 0;
        $platformCounts = array_fill_keys($platforms, 0);

        // Try live scraping if token is valid
        $liveSuccess = false;
        if (!empty($tokens)) {
            $queries = [];
            foreach ($platforms as $p) {
                if ($p === 'x') {
                    $queries[] = "site:twitter.com OR site:x.com \"{$cleanKeyword}\"";
                } else {
                    $queries[] = "site:{$p}.com \"{$cleanKeyword}\"";
                }
            }

            $input = [
                'queries'          => implode("\n", $queries),
                'maxPagesPerQuery' => 1,
                'resultsPerPage'   => max(3, min(10, (int) ($maxItems / count($platforms)))),
                'countryCode'      => 'id',
                'languageCode'     => 'id',
            ];

            foreach ($tokens as $token) {
                try {
                    $res = Http::withToken($token)
                        ->timeout(25)
                        ->post("https://api.apify.com/v2/acts/apify~google-search-scraper/run-sync-get-dataset-items?token={$token}", $input);

                    if ($res->successful() && is_array($res->json())) {
                        $items = $res->json();
                        foreach ($items as $item) {
                            $url = $item['url'] ?? '';
                            $title = $item['title'] ?? '';
                            $desc = $item['description'] ?? '';
                            $content = trim($title . ' — ' . $desc);
                            
                            $platform = 'x';
                            if (stripos($url, 'youtube.com') !== false || stripos($url, 'youtu.be') !== false) {
                                $platform = 'youtube';
                            } elseif (stripos($url, 'tiktok.com') !== false) {
                                $platform = 'tiktok';
                            } elseif (stripos($url, 'instagram.com') !== false) {
                                $platform = 'instagram';
                            } elseif (stripos($url, 'facebook.com') !== false) {
                                $platform = 'facebook';
                            }

                            if (!in_array($platform, $platforms)) {
                                continue;
                            }

                            if ($url === '') {
                                continue; // tanpa tautan sumber, hasil tidak dapat diverifikasi
                            }

                            $sentiment = \App\Support\NewsSentiment::detectSocial($content);
                            $handle = '';
                            if (preg_match('/(?:instagram\.com|tiktok\.com\/@|x\.com|twitter\.com|youtube\.com\/@)\/?@?([a-zA-Z0-9_.]+)/i', $url, $uMatches)
                                && !in_array(strtolower($uMatches[1]), ['p', 'reel', 'reels', 'tv', 'explore', 'video', 'watch', 'status', 'channel', 'shorts', 'search', 'hashtag', 'i'], true)) {
                                $handle = '@' . strtolower($uMatches[1]);
                            }

                            $postId = 'ondemand_' . md5($url);

                            SocialPost::updateOrCreate(
                                [
                                    'polda_id' => $poldaId,
                                    'post_id'  => $postId,
                                ],
                                [
                                    'case_id'        => $caseId,
                                    'platform'       => $platform,
                                    // Penulis hanya dari URL; tidak diketahui → nama situs (bukan akun karangan).
                                    'author_name'    => $handle !== '' ? $handle : (parse_url($url, PHP_URL_HOST) ?: 'Sumber tidak diketahui'),
                                    'author_handle'  => $handle,
                                    'is_kol'         => false,
                                    'kol_category'   => null,
                                    'content'        => $content,
                                    'post_url'       => $url,
                                    // Metrik & waktu tidak dibawa hasil pencarian: null = tidak diketahui.
                                    'likes_count'    => null,
                                    'shares_count'   => null,
                                    'comments_count' => null,
                                    'views_count'    => null,
                                    'sentiment'      => $sentiment,
                                    'virality_score' => null,
                                    'posted_at'      => null,
                                ]
                            );

                            $pulledCount++;
                            $platformCounts[$platform] = ($platformCounts[$platform] ?? 0) + 1;
                        }

                        if ($pulledCount > 0) {
                            $liveSuccess = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('On-demand Apify crawl exception: ' . $e->getMessage());
                }
            }
        }

        // Fallback pembangkit postingan BUATAN: hanya bila diaktifkan eksplisit (simod.social_synthetic_fallback).
        // Secara bawaan nonaktif sehingga radar tidak pernah menampilkan percakapan yang tidak nyata.
        if ($pulledCount === 0 && config('simod.social_synthetic_fallback')) {
            $templates = [
                [
                    'platform'  => 'x',
                    'author'    => 'Forum Warga Cyber',
                    'handle'    => '@forum_wargacyber',
                    'text'      => "Membahas isu terkini terkait {$cleanKeyword}. Warganet berharap pihak berwenang dan kepolisian terus meningkatkan pengawasan.",
                    'sentiment' => 'netral',
                    'virality'  => 78,
                ],
                [
                    'platform'  => 'tiktok',
                    'author'    => 'Info Publik FYP',
                    'handle'    => '@infopublik.fyp',
                    'text'      => "Video viral yang menyoroti topik {$cleanKeyword}. Banyak komentar netizen yang meminta klarifikasi dan tindakan cepat.",
                    'sentiment' => 'negatif',
                    'virality'  => 85,
                ],
                [
                    'platform'  => 'instagram',
                    'author'    => 'Suara Masyarakat',
                    'handle'    => '@suaramasyarakat_id',
                    'text'      => "Apresiasi respon jajaran patroli dalam menindaklanjuti keresahan warga seputar {$cleanKeyword}.",
                    'sentiment' => 'positif',
                    'virality'  => 70,
                ],
                [
                    'platform'  => 'youtube',
                    'author'    => 'Kanal Berita Daerah',
                    'handle'    => '@kanalberitadaerah',
                    'text'      => "Liputan khusus dan analisis pakar mengenai perkembangan isu {$cleanKeyword} di masyarakat.",
                    'sentiment' => 'netral',
                    'virality'  => 65,
                ],
            ];

            foreach ($templates as $t) {
                if (!in_array($t['platform'], $platforms)) {
                    continue;
                }

                $postId = 'ondemand_mock_' . md5($cleanKeyword . $t['platform'] . $poldaId);
                SocialPost::updateOrCreate(
                    [
                        'polda_id' => $poldaId,
                        'post_id'  => $postId,
                    ],
                    [
                        'case_id'        => $caseId,
                        'platform'       => $t['platform'],
                        'author_name'    => $t['author'],
                        'author_handle'  => $t['handle'],
                        'is_kol'         => false,
                        'kol_category'   => 'Opini Publik',
                        'content'        => $t['text'] . " #" . str_replace(' ', '', ucwords($cleanKeyword)),
                        'post_url'       => "https://{$t['platform']}.com/search?q=" . urlencode($cleanKeyword),
                        'likes_count'    => rand(250, 4200),
                        'shares_count'   => rand(40, 950),
                        'comments_count' => rand(25, 510),
                        'views_count'    => rand(5000, 65000),
                        'sentiment'      => $t['sentiment'],
                        'virality_score' => $t['virality'],
                        'posted_at'      => now()->subMinutes(rand(10, 180)),
                    ]
                );

                $pulledCount++;
                $platformCounts[$t['platform']] = ($platformCounts[$t['platform']] ?? 0) + 1;
            }
        }

        if ($case) {
            $case->refreshMetrics();
        }

        AuditLog::create([
            'polda_id'     => $poldaId,
            'user_id'      => auth()->id(),
            'action'       => 'apify.keyword_crawl',
            'subject_type' => 'social_post',
            'subject_id'   => (string) $poldaId,
            'ip_address'   => request()->ip() ?? '127.0.0.1',
            'user_agent'   => request()->userAgent() ?? 'System',
            'metadata'     => [
                'keyword'         => $cleanKeyword,
                'case_id'         => $caseId,
                'platforms'       => $platforms,
                'pulled_count'    => $pulledCount,
                'platform_counts' => $platformCounts,
                'live_success'    => $liveSuccess,
            ],
            'created_at'   => now(),
        ]);

        $modeLabel = $liveSuccess ? "Live Apify Scraper" : "Multi-Platform Ingestion";
        if ($pulledCount === 0) {
            return [
                'success'         => true,
                'keyword'         => $cleanKeyword,
                'case_id'         => $case?->id,
                'case_title'      => $case?->title,
                'new_posts'       => 0,
                'platform_counts' => $platformCounts,
                'message'         => "Belum ada postingan nyata yang ditemukan untuk \"{$cleanKeyword}\". Tidak ada data buatan yang ditambahkan; periksa token Apify atau coba kata kunci lain.",
            ];
        }

        return [
            'success'         => true,
            'keyword'         => $cleanKeyword,
            'case_id'         => $case?->id,
            'case_title'      => $case?->title,
            'new_posts'       => $pulledCount,
            'platform_counts' => $platformCounts,
            'message'         => "Pelacakan topik \"{$cleanKeyword}\" sukses ({$modeLabel})! {$pulledCount} postingan baru dihimpun ke dalam berkas kasus.",
        ];
    }



}