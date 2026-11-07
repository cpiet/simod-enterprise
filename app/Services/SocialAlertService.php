<?php

namespace App\Services;

use App\Models\MediaTopic;
use App\Models\MonitoredSocialAccount;
use App\Models\SocialAlert;
use App\Models\SocialCommentDigest;
use App\Models\User;
use App\Support\InstagramHighlights;
use Carbon\Carbon;

/**
 * Pembuat peringatan otomatis social listening. Aturan tertulis (doc 36), dari data nyata:
 *  - Unggahan BARU akun pantauan (belum ada di snapshot sebelumnya, terbit ≤ NEW_POST_HOURS jam):
 *      kamtibmas  → keterangan memuat kata kunci kamtibmas/Polri (daftar sama dengan penanda "menonjol")
 *      topik      → keterangan memuat kata kunci Topik Atensi aktif Polda itu
 *      lonjakan   → keterlibatan ≥ 2× median unggahan akun itu sendiri
 *  - komentar_negatif → ringkasan komentar ≥ NEG_MIN_COMMENTS komentar dan ≥ NEG_SHARE bernada negatif.
 * Satu peringatan per (Polda, tipe, unggahan): tidak berulang saat sinkron ulang.
 */
class SocialAlertService
{
    public const NEW_POST_HOURS = 72;
    public const NEG_MIN_COMMENTS = 10;
    public const NEG_SHARE = 0.4;

    /**
     * @param  array<int, string>  $previousUrls URL unggahan pada snapshot sebelum sinkron
     * @return int jumlah peringatan baru
     */
    public function fromSync(MonitoredSocialAccount $account, array $previousUrls): int
    {
        $previous = array_flip($previousUrls);
        $posts = InstagramHighlights::annotate($account->posts ?? []);
        $cutoff = now()->subHours(self::NEW_POST_HOURS);
        $topics = MediaTopic::where('polda_id', $account->polda_id)->where('status', 'active')->get()
            ->map(fn ($t) => ['title' => $t->title, 'keywords' => $t->getKeywordsArray()])
            ->filter(fn ($t) => $t['keywords'])->values();
        $who = $this->who($account);
        $created = 0;

        foreach ($posts as $post) {
            if (isset($previous[$post['url']])) {
                continue;
            }
            $posted = ! empty($post['posted_at']) ? Carbon::parse($post['posted_at']) : null;
            if (! $posted || $posted->lt($cutoff)) {
                continue; // hanya unggahan baru, bukan arsip lama yang baru pertama kali diambil
            }
            $snippet = mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $post['caption'])), 0, 160, '…');
            $h = $post['highlight'] ?? null;

            if (! empty($h['keywords'])) {
                $created += $this->make($account, 'kamtibmas', 'tinggi', "{$who} menyebut: " . implode(', ', $h['keywords']), $snippet, $post['url']);
            }
            $lower = mb_strtolower((string) $post['caption']);
            foreach ($topics as $topic) {
                $hits = array_values(array_filter($topic['keywords'], fn ($k) => mb_strlen($k) >= 3 && str_contains($lower, $k)));
                if ($hits) {
                    $created += $this->make($account, 'topik', 'tinggi', "{$who} menyinggung Topik Atensi \"{$topic['title']}\"", 'Kata kunci: ' . implode(', ', array_slice($hits, 0, 3)) . ". {$snippet}", $post['url']);
                    break;
                }
            }
            if (! empty($h['ratio'])) {
                $created += $this->make($account, 'lonjakan', 'sedang', "Unggahan {$who} melonjak (" . str_replace('.', ',', (string) $h['ratio']) . '× median akun)', $snippet, $post['url']);
            }
        }

        return $created;
    }

    public function fromDigest(SocialCommentDigest $digest): int
    {
        if ($digest->total < self::NEG_MIN_COMMENTS || $digest->negatif / max(1, $digest->total) < self::NEG_SHARE) {
            return 0;
        }
        $account = $digest->account;
        $pct = (int) round($digest->negatif / $digest->total * 100);

        return $this->make($account, 'komentar_negatif', 'tinggi',
            "Komentar negatif mendominasi ({$pct}%) pada unggahan " . ($account ? $this->who($account) : 'akun pantauan'),
            "{$digest->negatif} dari {$digest->total} komentar asli bernada negatif. " . mb_strimwidth((string) $digest->post_caption, 0, 120, '…'),
            $digest->post_url, $digest->polda_id, $digest->platform);
    }

    /** @return array{unread: int, items: array<int, array<string, mixed>>} */
    public function forUser(User $user, int $poldaId, int $limit = 15): array
    {
        $base = SocialAlert::where('polda_id', $poldaId);
        $seen = $user->alerts_seen_at;

        return [
            'unread' => (clone $base)->when($seen, fn ($q) => $q->where('created_at', '>', $seen))->count(),
            'items' => (clone $base)->latest('id')->limit($limit)->get()->map(fn (SocialAlert $a) => [
                'id' => $a->id, 'type' => $a->type, 'severity' => $a->severity, 'platform' => $a->platform,
                'title' => $a->title, 'body' => $a->body, 'post_url' => $a->post_url,
                'created_at' => $a->created_at?->toIso8601String(),
                'unread' => ! $seen || $a->created_at > $seen,
            ])->all(),
        ];
    }

    private function who(MonitoredSocialAccount $a): string
    {
        $label = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X', 'youtube' => 'YouTube', 'facebook' => 'Facebook'][$a->platform] ?? $a->platform;

        return ($a->display_name ?: $a->username) . " ({$label})";
    }

    private function make(?MonitoredSocialAccount $account, string $type, string $severity, string $title, string $body, string $url, ?int $poldaId = null, ?string $platform = null): int
    {
        $alert = SocialAlert::firstOrCreate(
            ['polda_id' => $poldaId ?? $account->polda_id, 'type' => $type, 'post_url' => $url],
            ['account_id' => $account?->id, 'platform' => $platform ?? $account?->platform, 'severity' => $severity, 'title' => mb_substr($title, 0, 200), 'body' => $body]
        );

        return $alert->wasRecentlyCreated ? 1 : 0;
    }
}
