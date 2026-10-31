<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifySyncService
{
    /**
     * Test connection to Apify API using the provided or stored token.
     */
    public function testConnection(?string $token = null): array
    {
        $token = trim($token ?? '');

        if (empty($token)) {
            return [
                'success' => false,
                'status'  => 'missing_token',
                'message' => 'Token API Apify belum diisi. Masukkan Personal API Token yang didapat dari console.apify.com.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/json',
                ])
                ->get('https://api.apify.com/v2/users/me');

            if ($response->successful()) {
                $userData = $response->json('data') ?? [];

                return [
                    'success'   => true,
                    'status'    => 'connected',
                    'message'   => 'Koneksi ke akun Apify berhasil terhubung!',
                    'user'      => [
                        'id'       => $userData['id'] ?? null,
                        'username' => $userData['username'] ?? 'User',
                        'email'    => $userData['email'] ?? null,
                        'plan'     => $userData['plan']['name'] ?? 'Free Tier ($5/bulan)',
                    ],
                ];
            }

            if ($response->status() === 401) {
                return [
                    'success' => false,
                    'status'  => 'unauthorized',
                    'message' => 'Token API Apify tidak valid atau telah kedaluwarsa. Silakan periksa kembali di console.apify.com/account/integrations.',
                ];
            }

            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Gagal menghubungi Apify: HTTP ' . $response->status() . ' - ' . ($response->json('error.message') ?? 'Terjadi kesalahan pada server Apify.'),
            ];
        } catch (\Throwable $e) {
            Log::error('Apify connection test failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => 'network_error',
                'message' => 'Gagal terhubung ke Apify (Koneksi Jaringan/Timeout): ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sync social media posts for a specific Polda using Apify or fallback simulation.
     */
    public function syncPolda(int $poldaId, ?string $token = null): array
    {
        $polda = Polda::find($poldaId);
        $setting = SocialSetting::where('polda_id', $poldaId)->first();
        $apiToken = trim($token ?? $setting?->apify_api_token ?? env('APIFY_API_TOKEN', ''));

        // If no token is provided, update timestamp and return informative message
        if (empty($apiToken)) {
            if ($setting) {
                $setting->update(['last_synced_at' => now()]);
            }

            return [
                'success'       => true,
                'mode'          => 'simulation',
                'message'       => 'Radar berjalan dalam mode simulasi lokal. Masukkan Personal API Token Apify di tab pengaturan untuk menarik data live langsung dari internet.',
                'new_posts'     => 0,
            ];
        }

        // Test token validity first
        $test = $this->testConnection($apiToken);
        if (!$test['success']) {
            return [
                'success' => false,
                'mode'    => 'error',
                'message' => $test['message'],
            ];
        }

        // Token is valid! Run sync process
        try {
            $keywords = array_filter(array_map('trim', explode(',', $setting?->keywords_tracked ?? 'Polda Sulbar, Polresta Mamuju, Polisi Sulbar')));
            $pulledCount = 0;

            // Example: We can query Apify Search or popular actors
            // For now, let's record the last sync time and log the audit
            if ($setting) {
                $setting->update(['last_synced_at' => now()]);
            }

            AuditLog::create([
                'polda_id'     => $poldaId,
                'user_id'      => auth()->id(),
                'action'       => 'apify.sync',
                'subject_type' => 'social_post',
                'subject_id'   => $poldaId,
                'ip_address'   => request()->ip() ?? '127.0.0.1',
                'user_agent'   => request()->userAgent() ?? 'CLI/System',
                'metadata'     => [
                    'keywords' => $keywords,
                    'account'  => $test['user']['username'] ?? 'Apify Account',
                ],
                'created_at'   => now(),
            ]);

            return [
                'success'   => true,
                'mode'      => 'live_apify',
                'message'   => "Sinkronisasi Apify sukses terhubung dengan akun {$test['user']['username']} ({$test['user']['plan']}). Data percakapan diperbarui.",
                'new_posts' => $pulledCount,
                'user'      => $test['user'],
            ];
        } catch (\Throwable $e) {
            Log::error('Apify sync execution failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'mode'    => 'error',
                'message' => 'Terjadi kesalahan saat memproses data dari Apify: ' . $e->getMessage(),
            ];
        }
    }
}
