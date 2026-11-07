<?php

namespace App\Services;

use App\Models\MonitoredSocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Pantauan unggahan publik akun tertentu (tokoh/KOL) lintas platform lewat Actor Apify, dengan failover token
 * (ApifyClient). Manual (tanpa jadwal). Hanya unggahan MILIK akun yang diminta dan bertautan valid platform itu yang
 * disimpan; metrik yang tidak tersedia tetap null. Kegagalan mempertahankan snapshot sukses terakhir.
 *
 * Format keluaran tiap Actor diverifikasi dengan pengambilan nyata (doc 35).
 */
class SocialAccountMonitoringService
{
    public const POSTS_LIMIT = 12;

    /** Batas biaya per run (beberapa Actor mensyaratkan minimal US$0,50); biaya nyata ±US$0,01–0,05 untuk 12 item. */
    public const MAX_CHARGE_USD = 0.5;

    /** Pola username per platform (tanpa @). */
    public const USERNAME_RULES = [
        'instagram' => '/^[a-z0-9._]{1,30}$/',
        'tiktok' => '/^[a-z0-9._]{2,24}$/',
        'x' => '/^[A-Za-z0-9_]{1,15}$/',
        'youtube' => '/^[A-Za-z0-9._-]{3,30}$/',
        'facebook' => '/^[A-Za-z0-9.\-]{2,80}$/',
    ];

    public function __construct(private ApifyClient $apify, private SocialAlertService $alerts) {}

    /** Bentuk baku username untuk disimpan (IG & TikTok tidak peka huruf besar/kecil). */
    public static function normalizeUsername(string $platform, string $raw): string
    {
        $u = ltrim(trim($raw), '@');
        // Terima tautan profil yang ditempel utuh.
        if (preg_match('~(?:instagram\.com/|tiktok\.com/@|x\.com/|twitter\.com/|youtube\.com/@|facebook\.com/)@?([A-Za-z0-9._\-]+)~i', $u, $m)) {
            $u = $m[1];
        }

        return in_array($platform, ['instagram', 'tiktok'], true) ? strtolower($u) : $u;
    }

    private function fail(MonitoredSocialAccount $account, string $status, string $message): array
    {
        $account->update(['sync_status' => $status, 'last_error' => $message]);

        return ['success' => false, 'message' => $message];
    }

    public function sync(MonitoredSocialAccount $account): array
    {
        $lock = Cache::lock('social-monitor-' . $account->id, 200);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'Sinkronisasi akun ini sedang berlangsung.'];
        }

        try {
            [$actor, $input] = $this->request($account);
            $run = $this->apify->run($actor, $input, $account->polda_id, self::POSTS_LIMIT, self::MAX_CHARGE_USD);
            if (! $run['success']) {
                return $this->fail($account, 'error', $run['message']);
            }

            [$posts, $profile, $problem] = $this->normalize($account, $run['items']);
            if (! $posts) {
                return $this->fail($account, 'empty', $problem ?? 'Tidak ada unggahan akun yang dapat diverifikasi. Data sebelumnya tetap tersedia jika ada.');
            }
            usort($posts, fn ($a, $b) => strcmp($b['posted_at'] ?? '', $a['posted_at'] ?? ''));
            $previousUrls = array_column($account->posts ?? [], 'url');
            $account->update([
                'display_name' => $profile['name'] ?? $account->display_name,
                'profile' => array_filter(['followers' => $profile['followers'] ?? null, 'verified' => $profile['verified'] ?? null], fn ($v) => $v !== null) ?: null,
                'posts' => array_slice($posts, 0, self::POSTS_LIMIT),
                'last_synced_at' => now(),
                'sync_status' => 'success',
                'last_error' => null,
            ]);

            $newAlerts = $this->alerts->fromSync($account->fresh(), $previousUrls);

            return ['success' => true, 'new_alerts' => $newAlerts, 'message' => count($posts) . ' unggahan ' . $this->label($account) . ' berhasil disinkronkan.' . ($newAlerts ? " {$newAlerts} peringatan baru." : '')];
        } catch (\Throwable $e) {
            report($e);

            return $this->fail($account, 'error', 'Sinkronisasi gagal. Coba lagi; bila berulang, hubungi administrator.');
        } finally {
            $lock->release();
        }
    }

    private function label(MonitoredSocialAccount $a): string
    {
        return ($a->platform === 'facebook' ? '' : '@') . $a->username;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function request(MonitoredSocialAccount $a): array
    {
        $u = $a->username;

        return match ($a->platform) {
            'tiktok' => ['clockworks~tiktok-scraper', ['profiles' => [$u], 'resultsPerPage' => self::POSTS_LIMIT, 'profileScrapeSections' => ['videos'],
                'shouldDownloadVideos' => false, 'shouldDownloadCovers' => false, 'shouldDownloadSubtitles' => false, 'shouldDownloadSlideshowImages' => false]],
            'youtube' => ['streamers~youtube-scraper', ['startUrls' => [['url' => "https://www.youtube.com/@{$u}/videos"]], 'maxResults' => self::POSTS_LIMIT,
                'maxResultsShorts' => 0, 'maxResultStreams' => 0]],
            'x' => ['apidojo~tweet-scraper', ['twitterHandles' => [$u], 'maxItems' => self::POSTS_LIMIT, 'sort' => 'Latest']],
            'facebook' => ['apify~facebook-posts-scraper', ['startUrls' => [['url' => "https://www.facebook.com/{$u}"]], 'resultsLimit' => self::POSTS_LIMIT]],
            default => ['apify~instagram-scraper', ['directUrls' => ["https://www.instagram.com/{$u}/"], 'resultsType' => 'posts',
                'resultsLimit' => self::POSTS_LIMIT, 'skipPinnedPosts' => true]],
        };
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: ?string} [unggahan, profil, alasan bila kosong]
     */
    private function normalize(MonitoredSocialAccount $a, array $items): array
    {
        $posts = [];
        $profile = [];
        $problem = null;
        $num = static fn ($v) => is_numeric($v) && $v >= 0 ? (int) $v : null;
        $date = static function ($v): ?string {
            try {
                return $v ? Carbon::parse($v)->toIso8601String() : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $same = fn (?string $x) => $x !== null && strcasecmp(ltrim($x, '@'), $a->username) === 0;

        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $post = null;
            switch ($a->platform) {
                case 'instagram':
                    if (! empty($it['error']) && empty($it['url'])) {
                        $problem = 'Akun tidak ditemukan, privat, atau dibatasi Instagram. Periksa penulisan username.';
                        break;
                    }
                    if (! $same($it['ownerUsername'] ?? null) || ! $this->validUrl($it['url'] ?? '', ['instagram.com'], '~^/(p|reel|reels)/[A-Za-z0-9_-]+/?$~')) {
                        break;
                    }
                    $type = strtolower((string) ($it['type'] ?? ''));
                    $post = [
                        'url' => $it['url'], 'caption' => $it['caption'] ?? '', 'posted_at' => $date($it['timestamp'] ?? null),
                        'likes' => $num($it['likesCount'] ?? null), 'comments' => $num($it['commentsCount'] ?? null),
                        'views' => $num($it['videoViewCount'] ?? $it['videoPlayCount'] ?? null), 'shares' => null,
                        'type' => in_array($type, ['image', 'video', 'sidecar'], true) ? $type : null,
                        'hashtags' => (array) ($it['hashtags'] ?? []), 'thumb' => $this->safeImage($it['displayUrl'] ?? null, ['cdninstagram.com', 'fbcdn.net']),
                    ];
                    $profile['name'] ??= $it['ownerFullName'] ?? null;
                    break;

                case 'tiktok':
                    if (! empty($it['note']) && empty($it['webVideoUrl'])) {
                        $problem = str_contains(strtolower($it['note']), 'private')
                            ? 'Akun TikTok ini privat; unggahannya tidak dapat diambil.'
                            : 'Akun TikTok tidak memiliki video publik atau tidak ditemukan. Periksa penulisan username.';
                        $profile['name'] ??= $it['authorMeta']['nickName'] ?? null;
                        break;
                    }
                    if (! $same($it['authorMeta']['name'] ?? null) || ! $this->validUrl($it['webVideoUrl'] ?? '', ['tiktok.com'], '~^/@[^/]+/(video|photo)/\d+~')) {
                        break;
                    }
                    $post = [
                        'url' => $it['webVideoUrl'], 'caption' => $it['text'] ?? '', 'posted_at' => $date($it['createTimeISO'] ?? null),
                        'likes' => $num($it['diggCount'] ?? null), 'comments' => $num($it['commentCount'] ?? null),
                        'views' => $num($it['playCount'] ?? null), 'shares' => $num($it['shareCount'] ?? null),
                        'type' => ! empty($it['isSlideshow']) ? 'sidecar' : 'video',
                        'hashtags' => array_column((array) ($it['hashtags'] ?? []), 'name'),
                        'thumb' => $this->safeImage($it['videoMeta']['coverUrl'] ?? null, ['tiktokcdn.com', 'tiktokcdn-us.com', 'tiktokcdn-eu.com']),
                    ];
                    $profile['name'] ??= $it['authorMeta']['nickName'] ?? null;
                    $profile['followers'] ??= $num($it['authorMeta']['fans'] ?? null);
                    $profile['verified'] ??= isset($it['authorMeta']['verified']) ? (bool) $it['authorMeta']['verified'] : null;
                    break;

                case 'youtube':
                    if (! empty($it['error']) || (! empty($it['note']) && empty($it['id']))) {
                        $problem = 'Kanal YouTube tidak ditemukan atau tidak memiliki video publik. Periksa handle kanal (tanpa @).';
                        break;
                    }
                    if (! $same($it['channelUsername'] ?? null) || ($it['type'] ?? 'video') !== 'video' || ! $this->validUrl($it['url'] ?? '', ['youtube.com'], '~^/watch$~')) {
                        break;
                    }
                    $post = [
                        'url' => $it['url'], 'caption' => trim(($it['title'] ?? '') . "\n" . ($it['text'] ?? '')), 'posted_at' => $date($it['date'] ?? null),
                        'likes' => $num($it['likes'] ?? null), 'comments' => $num($it['commentsCount'] ?? null),
                        'views' => $num($it['viewCount'] ?? null), 'shares' => null, 'type' => 'video',
                        'hashtags' => array_map(fn ($h) => ltrim((string) $h, '#'), array_filter((array) ($it['hashtags'] ?? []), 'is_string')),
                        'thumb' => $this->safeImage($it['thumbnailUrl'] ?? null, ['ytimg.com']),
                    ];
                    $profile['name'] ??= $it['channelName'] ?? null;
                    $profile['followers'] ??= $num($it['numberOfSubscribers'] ?? null);
                    $profile['verified'] ??= isset($it['isChannelVerified']) ? (bool) $it['isChannelVerified'] : null;
                    break;

                case 'x':
                    if (($it['type'] ?? 'tweet') !== 'tweet' || ! empty($it['isRetweet'])) {
                        break; // retweet bukan konten milik akun
                    }
                    if (! $same($it['author']['userName'] ?? null) || ! $this->validUrl($it['url'] ?? '', ['x.com', 'twitter.com'], '~^/[A-Za-z0-9_]+/status/\d+~')) {
                        break;
                    }
                    $media = $it['media'][0] ?? ($it['extendedEntities']['media'][0] ?? null);
                    $post = [
                        'url' => $it['url'], 'caption' => $it['fullText'] ?? $it['text'] ?? '', 'posted_at' => $date($it['createdAt'] ?? null),
                        'likes' => $num($it['likeCount'] ?? null), 'comments' => $num($it['replyCount'] ?? null),
                        'views' => $num($it['viewCount'] ?? null), 'shares' => $num($it['retweetCount'] ?? null),
                        'type' => ! empty($it['videos']) ? 'video' : ($media ? 'image' : 'text'),
                        'hashtags' => array_column((array) ($it['entities']['hashtags'] ?? []), 'text'),
                        // `media` dapat berupa daftar URL (string) atau objek media (media_url_https).
                        'thumb' => $this->safeImage(is_string($media) ? $media : (is_array($media) ? ($media['media_url_https'] ?? null) : null), ['twimg.com']),
                    ];
                    $profile['name'] ??= $it['author']['name'] ?? null;
                    $profile['followers'] ??= $num($it['author']['followers'] ?? null);
                    $profile['verified'] ??= isset($it['author']['isBlueVerified']) ? (bool) $it['author']['isBlueVerified'] : null;
                    break;

                case 'facebook':
                    if (! empty($it['error'])) {
                        $problem = 'Halaman Facebook tidak publik atau tidak ditemukan. Hanya Halaman (Page) publik yang dapat dipantau; profil pribadi tidak.';
                        break;
                    }
                    if (! $this->validUrl($it['url'] ?? '', ['facebook.com'], '~^/.+~')) {
                        break;
                    }
                    $thumb = $it['media'][0]['thumbnail'] ?? ($it['media'][0]['photo_image']['uri'] ?? null);
                    $post = [
                        'url' => $it['url'], 'caption' => $it['text'] ?? ($it['previewTitle'] ?? ''), 'posted_at' => $date($it['time'] ?? null),
                        'likes' => $num($it['likes'] ?? null), 'comments' => $num($it['comments'] ?? null),
                        'views' => ! empty($it['isVideo']) ? $num($it['videoPostViewCount'] ?? null) : null, 'shares' => $num($it['shares'] ?? null),
                        'type' => ! empty($it['isVideo']) ? 'video' : ($thumb ? 'image' : 'text'),
                        'hashtags' => [], 'thumb' => $this->safeImage($thumb, ['fbcdn.net']),
                    ];
                    $profile['name'] ??= $it['user']['name'] ?? ($it['pageName'] ?? null);
                    break;
            }

            if ($post) {
                $post['caption'] = mb_substr((string) $post['caption'], 0, 1200);
                $post['hashtags'] = array_values(array_slice(array_filter(array_map(fn ($h) => is_string($h) ? mb_substr($h, 0, 60) : null, $post['hashtags'])), 0, 8));
                $posts[$post['url']] = $post;
            }
        }

        return [array_values($posts), $profile, $problem];
    }

    /** URL https dengan host platform yang benar dan pola path yang diharapkan. */
    private function validUrl(string $url, array $hosts, string $pathPattern): bool
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = preg_replace('/^(www|m|mobile)\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return in_array($host, $hosts, true) && preg_match($pathPattern, (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    /** Gambar hanya dari CDN resmi platform (https); selain itu null (UI menampilkan ikon). */
    private function safeImage(?string $url, array $hostSuffixes): ?string
    {
        if (! $url || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($hostSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return $url;
            }
        }

        return null;
    }
}
