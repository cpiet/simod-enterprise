<?php

namespace App\Services;

use App\Models\MonitoredInstagramAccount;
use App\Models\SocialSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InstagramAccountMonitoringService
{
    public function sync(MonitoredInstagramAccount $account, ApifySyncService $apify): array
    {
        $lock = Cache::lock('instagram-monitor-'.$account->id, 150);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'Sinkronisasi akun ini sedang berlangsung.'];
        }

        try {
            $rawTokens = SocialSetting::where('polda_id', $account->polda_id)->value('apify_api_token')
                ?: SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
                ?: env('APIFY_API_TOKEN', '');
            $token = $apify->parseTokens($rawTokens)[0] ?? null;
            if (! $token) {
                $account->update(['sync_status' => 'error']);
                return ['success' => false, 'message' => 'Token Apify belum tersedia. Hubungi administrator.'];
            }

            $response = Http::withToken($token)->timeout(120)->post(
                'https://api.apify.com/v2/acts/apify~instagram-scraper/run-sync-get-dataset-items?timeout=100&maxTotalChargeUsd=0.03',
                ['directUrls' => ['https://www.instagram.com/'.$account->username.'/'], 'resultsType' => 'posts', 'resultsLimit' => 5, 'skipPinnedPosts' => true]
            );
            if (! $response->successful() || ! is_array($response->json()) || ! array_is_list($response->json())) {
                $account->update(['sync_status' => 'error']);
                return ['success' => false, 'message' => 'Apify tidak dapat menyelesaikan sinkronisasi. Periksa akses, saldo, dan konfigurasi Actor.'];
            }

            $posts = [];
            $displayName = null;
            foreach ($response->json() as $item) {
                if (! is_array($item) || strcasecmp((string) ($item['ownerUsername'] ?? ''), $account->username) !== 0) {
                    continue;
                }
                $url = $item['url'] ?? '';
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                $path = (string) parse_url($url, PHP_URL_PATH);
                if (! in_array($host, ['instagram.com', 'www.instagram.com'], true) || ! preg_match('~^/(p|reel|reels)/[A-Za-z0-9_-]+/?$~', $path) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                    continue;
                }
                try {
                    $date = ! empty($item['timestamp']) ? Carbon::parse($item['timestamp'])->toIso8601String() : null;
                } catch (\Throwable $e) {
                    $date = null;
                }
                $number = static fn ($v) => is_numeric($v) && $v >= 0 ? (int) $v : null;
                $posts[$url] = [
                    'url' => $url, 'caption' => (string) ($item['caption'] ?? ''), 'posted_at' => $date,
                    'likes' => $number($item['likesCount'] ?? null), 'comments' => $number($item['commentsCount'] ?? null),
                ];
                $displayName = $item['ownerFullName'] ?? $displayName;
            }

            if (! $posts) {
                $account->update(['sync_status' => 'empty']);
                return ['success' => false, 'message' => 'Tidak ada unggahan akun yang dapat diverifikasi. Data sebelumnya tetap tersedia jika ada.'];
            }
            $posts = array_values($posts);
            usort($posts, fn ($a, $b) => strcmp($b['posted_at'] ?? '', $a['posted_at'] ?? ''));
            $account->update(['display_name' => $displayName, 'posts' => $posts, 'last_synced_at' => now(), 'sync_status' => 'success']);

            return ['success' => true, 'message' => count($posts).' unggahan akun berhasil disinkronkan.'];
        } catch (\Throwable $e) {
            $account->update(['sync_status' => 'error']);
            return ['success' => false, 'message' => 'Sinkronisasi Apify gagal atau melewati batas waktu.'];
        } finally {
            $lock->release();
        }
    }
}
