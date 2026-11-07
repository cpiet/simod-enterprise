<?php

namespace App\Services;

use App\Models\MonitoredSocialAccount;
use App\Models\SocialCommentDigest;
use App\Support\NewsSentiment;
use Carbon\Carbon;

/**
 * Ringkasan Komentar: mengambil komentar ASLI pada satu unggahan akun pantauan (maks. COMMENTS_LIMIT), memberi
 * sentimen per komentar dengan aturan tunggal NewsSentiment::explainSocial, menghitung kata yang sering muncul,
 * lalu (opsional, atas permintaan) meminta SADEWA meringkas opini. Hanya unggahan yang ada di snapshot akun
 * pantauan yang boleh diambil komentarnya (mencegah pengambilan sembarang URL & menjaga tenant).
 */
class CommentDigestService
{
    public const COMMENTS_LIMIT = 50;

    private const STOPWORDS = ['yang', 'dan', 'di', 'ke', 'dari', 'ini', 'itu', 'untuk', 'pada', 'adalah', 'dengan', 'juga', 'aja', 'saja',
        'ada', 'tidak', 'gak', 'ga', 'nggak', 'enggak', 'sudah', 'udah', 'akan', 'bisa', 'kalau', 'kalo', 'karena', 'atau', 'tapi', 'yg',
        'kita', 'kami', 'saya', 'aku', 'kamu', 'dia', 'mereka', 'pak', 'bu', 'semoga', 'lagi', 'jadi', 'mau', 'apa', 'sama', 'nya', 'dong',
        'sih', 'kok', 'deh', 'nih', 'tuh', 'kan', 'lah', 'pun', 'para', 'oleh', 'dalam', 'dgn', 'utk', 'the', 'and', 'for', 'you', 'this',
        'that', 'are', 'with', 'http', 'https', 'com', 'www'];

    public function __construct(private ApifyClient $apify) {}

    public function fetch(MonitoredSocialAccount $account, string $postUrl): array
    {
        $post = collect($account->posts ?? [])->firstWhere('url', $postUrl);
        if (! $post) {
            return ['success' => false, 'message' => 'Unggahan tidak ditemukan pada akun pantauan ini. Sinkronkan akun terlebih dahulu.'];
        }

        [$actor, $input] = $this->request($account->platform, $postUrl);
        if (! $actor) {
            return ['success' => false, 'message' => 'Platform ini belum mendukung pengambilan komentar.'];
        }
        $run = $this->apify->run($actor, $input, $account->polda_id, self::COMMENTS_LIMIT + 5, 0.5);
        if (! $run['success']) {
            return ['success' => false, 'message' => $run['message']];
        }

        $comments = $this->normalize($account->platform, $run['items'], $postUrl);
        if (! $comments && collect($run['items'])->contains(fn ($i) => is_array($i) && ! empty($i['error']))) {
            return ['success' => false, 'message' => 'Komentar unggahan ini tidak dapat diakses (dibatasi pemilik atau tidak publik).'];
        }

        $counts = ['positif' => 0, 'netral' => 0, 'negatif' => 0];
        foreach ($comments as $c) {
            $counts[$c['sentiment']]++;
        }

        $digest = SocialCommentDigest::updateOrCreate(
            ['polda_id' => $account->polda_id, 'post_url' => $postUrl],
            [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'post_caption' => mb_substr((string) ($post['caption'] ?? ''), 0, 500),
                'comments' => $comments,
                'total' => count($comments),
                'positif' => $counts['positif'],
                'netral' => $counts['netral'],
                'negatif' => $counts['negatif'],
                'top_words' => $this->topWords($comments),
                'source_hash' => hash('sha256', json_encode(array_column($comments, 'text'))),
                'fetched_at' => now(),
            ]
        );

        return [
            'success' => true,
            'message' => $comments ? count($comments) . ' komentar diambil dan dinilai sentimennya.' : 'Unggahan ini belum memiliki komentar publik.',
            'digest' => self::present($digest),
        ];
    }

    /** @return array{0: ?string, 1: array<string, mixed>} */
    private function request(string $platform, string $url): array
    {
        $n = self::COMMENTS_LIMIT;

        return match ($platform) {
            'instagram' => ['apify~instagram-comment-scraper', ['directUrls' => [$url], 'resultsLimit' => $n]],
            'tiktok' => ['clockworks~tiktok-comments-scraper', ['postURLs' => [$url], 'commentsPerPost' => $n, 'maxRepliesPerComment' => 0]],
            'youtube' => ['streamers~youtube-comments-scraper', ['startUrls' => [['url' => $url]], 'maxComments' => $n]],
            'x' => preg_match('~/status/(\d+)~', $url, $m)
                ? ['apidojo~tweet-scraper', ['searchTerms' => ['conversation_id:' . $m[1]], 'maxItems' => $n, 'sort' => 'Latest']]
                : [null, []],
            'facebook' => ['apify~facebook-comments-scraper', ['startUrls' => [['url' => $url]], 'resultsLimit' => $n]],
            default => [null, []],
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function normalize(string $platform, array $items, string $postUrl): array
    {
        $out = [];
        $statusId = preg_match('~/status/(\d+)~', $postUrl, $m) ? $m[1] : null;
        $date = static function ($v): ?string {
            try {
                return $v ? Carbon::parse($v)->toIso8601String() : null;
            } catch (\Throwable) {
                return null;
            }
        };
        foreach ($items as $it) {
            if (! is_array($it) || ! empty($it['error'])) {
                continue;
            }
            $c = match ($platform) {
                'instagram' => ['text' => $it['text'] ?? '', 'author' => $it['ownerUsername'] ?? null, 'likes' => $it['likesCount'] ?? null, 'time' => $date($it['timestamp'] ?? null)],
                'tiktok' => ['text' => $it['text'] ?? '', 'author' => $it['uniqueId'] ?? null, 'likes' => $it['diggCount'] ?? null, 'time' => $date($it['createTimeISO'] ?? null)],
                'youtube' => ['text' => $it['comment'] ?? '', 'author' => isset($it['author']) ? ltrim((string) $it['author'], '@') : null, 'likes' => $it['voteCount'] ?? null, 'time' => null, 'time_text' => $it['publishedTimeText'] ?? null],
                'x' => ($it['id'] ?? null) === $statusId ? null : [
                    'text' => trim(preg_replace('/^(@\w+\s+)+/', '', (string) ($it['fullText'] ?? $it['text'] ?? ''))),
                    'author' => $it['author']['userName'] ?? null, 'likes' => $it['likeCount'] ?? null, 'time' => $date($it['createdAt'] ?? null),
                ],
                'facebook' => ['text' => $it['text'] ?? '', 'author' => $it['profileName'] ?? null, 'likes' => $it['likesCount'] ?? null, 'time' => $date($it['date'] ?? null)],
                default => null,
            };
            if (! $c || trim((string) $c['text']) === '') {
                continue;
            }
            $c['text'] = mb_substr(trim((string) $c['text']), 0, 600);
            $c['likes'] = is_numeric($c['likes']) ? (int) $c['likes'] : null;
            $reason = NewsSentiment::explainSocial($c['text']);
            $c['sentiment'] = $reason['sentiment'];
            $c['reason'] = $reason['reason'];
            $out[] = $c;
            if (count($out) >= self::COMMENTS_LIMIT) {
                break;
            }
        }

        return $out;
    }

    /** @return array<int, array{word: string, count: int}> */
    private function topWords(array $comments): array
    {
        $freq = [];
        foreach ($comments as $c) {
            $text = mb_strtolower(preg_replace('/https?:\/\/\S+|@\w+/u', ' ', $c['text']));
            foreach (array_unique(preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY)) as $w) {
                if (mb_strlen($w) >= 4 && ! in_array($w, self::STOPWORDS, true) && ! is_numeric($w)) {
                    $freq[$w] = ($freq[$w] ?? 0) + 1;
                }
            }
        }
        arsort($freq);

        return collect($freq)->filter(fn ($n) => $n >= 2)->take(12)->map(fn ($n, $w) => ['word' => $w, 'count' => $n])->values()->all();
    }

    /** Bentuk untuk browser. */
    public static function present(SocialCommentDigest $d): array
    {
        return [
            'id' => $d->id, 'post_url' => $d->post_url, 'platform' => $d->platform,
            'total' => $d->total, 'positif' => $d->positif, 'netral' => $d->netral, 'negatif' => $d->negatif,
            'top_words' => $d->top_words ?? [], 'comments' => $d->comments ?? [],
            'ai_summary' => $d->ai_summary, 'fetched_at' => $d->fetched_at?->toIso8601String(), 'summarized_at' => $d->summarized_at?->toIso8601String(),
        ];
    }
}
