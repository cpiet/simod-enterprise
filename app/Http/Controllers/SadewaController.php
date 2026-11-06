<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaIntelligenceReport;
use App\Models\RolePermission;
use App\Models\SadewaConversation;
use App\Models\SocialIntelligenceReport;
use App\Models\SocialPost;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SADEWA AI: asisten percakapan lintas sumber (Media Online, Media Sosial, Isu Internal).
 *
 * Prinsip keamanan:
 * - Tenant selalu dari resolveActiveTenant(); klien hanya boleh meminta cakupan yang diizinkan perannya.
 * - Setiap sumber data hanya dimasukkan ke prompt bila pengguna berhak atas modulnya (RBAC).
 * - Riwayat bersifat privat per akun (hanya pemilik yang dapat membaca/menghapus).
 */
class SadewaController extends Controller
{
    /** Kunci lingkup => [label, kunci modul RBAC yang disyaratkan]. */
    private const SCOPES = [
        'media'  => ['Media Online', 'media_intelligence_view'],
        'social' => ['Media Sosial', 'social_intelligence_view'],
        'issues' => ['Isu Internal', 'issues_view'],
    ];

    private const MAX_CONVERSATIONS = 30;

    /** Lingkup yang boleh dipakai pengguna ini. */
    private function allowedScopes(Request $request): array
    {
        $user = $request->user();
        $allowed = [];
        foreach (self::SCOPES as $key => [$label, $module]) {
            if ($user->isSuperAdmin() || RolePermission::isGranted((string) ($user->role ?? 'operator'), $module, $user->polda_id)) {
                $allowed[$key] = $label;
            }
        }

        return $allowed;
    }

    /** Status awal untuk panel: lingkup yang tersedia dan angka nyata data terkini. */
    public function meta(Request $request): JsonResponse
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);
        $allowed = $this->allowedScopes($request);
        $since = now()->subDay();

        $stats = [];
        if (isset($allowed['media'])) {
            $stats['portals'] = MediaFeed::where('polda_id', $poldaId)->where('is_active', true)->count();
            $stats['articles_24h'] = MediaArticle::where('polda_id', $poldaId)->where('pub_date', '>=', $since)->count();
        }
        if (isset($allowed['social'])) {
            $stats['posts_24h'] = SocialPost::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
                ->where('posted_at', '>=', $since)->count();
        }
        if (isset($allowed['issues'])) {
            $stats['active_issues'] = Issue::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
                ->whereIn('status', array_merge(...array_map(fn ($g) => Issue::STATUS_GROUPS[$g], ['pending', 'active', 'clarified'])))
                ->count();
        }

        return response()->json([
            'scopes'   => collect($allowed)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'stats'    => $stats,
            'tenant'   => $selectedPolda?->name,
            'user'     => ['name' => $request->user()->name],
            'usage'    => app(\App\Services\AiQuota::class)->snapshot($request->user(), $selectedPolda?->id),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $rows = SadewaConversation::where('user_id', $request->user()->id)
            ->latest('updated_at')->limit(self::MAX_CONVERSATIONS)->get();

        return response()->json([
            'data' => $rows->map(function ($c) {
                $msgs = $c->messages ?? [];
                $last = collect($msgs)->reverse()->firstWhere('role', 'assistant');

                return [
                    'id'         => $c->id,
                    'title'      => $c->title,
                    'scope'      => $c->scope,
                    'snippet'    => $last ? Str::limit(trim(preg_replace('/[#*`_~]+/', '', $last['text'] ?? '')), 90) : '',
                    'count'      => count($msgs),
                    'updated_at' => $c->updated_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $c = SadewaConversation::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json(['id' => $c->id, 'title' => $c->title, 'scope' => $c->scope, 'messages' => $c->messages ?? []]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        SadewaConversation::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        SadewaConversation::where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true]);
    }

    public function chat(Request $request, GeminiService $gemini): JsonResponse
    {
        $request->validate([
            'message'         => 'required|string|max:1000',
            'scope'           => 'nullable|string|in:all,media,social,issues',
            'conversation_id' => 'nullable|integer',
            'subject'         => 'nullable|string|max:240',
        ]);

        $user = $request->user();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $this->requestedPoldaOverride($request, $selectedPolda) ?? \App\Models\Polda::first();
        $isNational = $this->aggregatesNational($selectedCode);

        $allowed = $this->allowedScopes($request);
        if (empty($allowed)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: peran Anda tidak memiliki hak atas sumber data SADEWA.'], 403);
        }

        $requested = $request->input('scope', 'all');
        if ($requested !== 'all' && !isset($allowed[$requested])) {
            return response()->json(['success' => false, 'message' => 'Lingkup data tersebut tidak tersedia untuk peran Anda.'], 403);
        }
        $active = $requested === 'all' ? array_keys($allowed) : [$requested];

        $conversation = null;
        if ($request->filled('conversation_id')) {
            $conversation = SadewaConversation::where('user_id', $user->id)->findOrFail((int) $request->input('conversation_id'));
        }
        $history = collect($conversation?->messages ?? [])
            ->map(fn ($m) => ['role' => $m['role'] ?? 'user', 'content' => $m['text'] ?? ''])->all();

        $quota = app(\App\Services\AiQuota::class);
        if ($denied = $quota->denial($user, $selectedPolda?->id)) {
            return response()->json(['success' => false, 'status' => 'ai_limited', 'message' => $denied, 'usage' => $quota->snapshot($user, $selectedPolda?->id)], 429);
        }

        [$context, $sourceMap] = $this->buildContext($active, $selectedPolda, $isNational);
        $scopeLabel = implode(' + ', array_map(fn ($k) => $allowed[$k], $active));

        $result = $gemini->chatSadewa(
            $selectedPolda,
            (string) $request->input('message'),
            $history,
            $context,
            $scopeLabel,
            $request->input('subject')
        );

        $quota->finish($user, $selectedPolda?->id, 'chat', $result);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Gagal memproses pesan AI', 'usage' => $quota->snapshot($user, $selectedPolda?->id)], 422);
        }

        $reply = $result['reply'];
        $sources = [];
        $fromWeb = false;

        if (str_contains($reply, $gemini->webSearchMarker())) {
            // Data SIMOD tidak memuat jawaban. Cari di internet hanya dengan pertanyaan pengguna (tanpa data internal).
            $fallback = 'Data SIMOD pada lingkup ' . $scopeLabel . ' belum memuat jawaban untuk pertanyaan ini.';
            if (! \App\Models\AiSetting::webSearchEnabled($selectedPolda?->id)) {
                $reply = $fallback . ' Pencarian internet SADEWA sedang dinonaktifkan administrator.';
            } elseif ($denied = $quota->denial($user, $selectedPolda?->id)) {
                $reply = $fallback . ' Pencarian internet tidak dijalankan: ' . $denied;
            } else {
                $previousQuestions = collect($history)->where('role', 'user')->pluck('content')->all();
                $web = $gemini->searchWebSadewa($selectedPolda, (string) $request->input('message'), $previousQuestions);
                $quota->finish($user, $selectedPolda?->id, 'chat_web', $web);
                if ($web['success']) {
                    $reply = $web['reply'];
                    $sources = $web['sources'];
                    $fromWeb = true;
                } else {
                    $reply = $fallback . ' Pencarian internet gagal: ' . ($web['message'] ?? 'coba lagi nanti.');
                }
            }
        } else {
            // Hanya sumber yang benar-benar dirujuk dalam jawaban yang dikirim ke klien.
            preg_match_all('/\[([MSIE]\d{1,3})\]/', $reply, $m);
            $sources = collect(array_unique($m[1]))
                ->filter(fn ($code) => isset($sourceMap[$code]))
                ->map(fn ($code) => ['code' => $code] + $sourceMap[$code])->values()->all();
        }

        $now = now()->toIso8601String();
        $messages = $conversation?->messages ?? [];
        $messages[] = ['role' => 'user', 'text' => (string) $request->input('message'), 'time' => $now];
        $messages[] = ['role' => 'assistant', 'text' => $reply, 'time' => $now, 'sources' => $sources, 'from_web' => $fromWeb];
        $messages = array_slice($messages, -SadewaConversation::MAX_MESSAGES);

        if ($conversation) {
            $conversation->update(['messages' => $messages, 'scope' => $requested]);
        } else {
            $conversation = SadewaConversation::create([
                'user_id'  => $user->id,
                'polda_id' => $selectedPolda?->id,
                'title'    => Str::limit((string) $request->input('message'), 60),
                'scope'    => $requested,
                'messages' => $messages,
            ]);
            // Batasi jumlah riwayat per akun.
            $stale = SadewaConversation::where('user_id', $user->id)->latest('updated_at')->skip(self::MAX_CONVERSATIONS)->take(100)->pluck('id');
            if ($stale->isNotEmpty()) {
                SadewaConversation::whereIn('id', $stale)->delete();
            }
        }

        return response()->json([
            'success'         => true,
            'reply'           => $reply,
            'sources'         => $sources,
            'from_web'        => $fromWeb,
            'conversation_id' => $conversation->id,
            'title'           => $conversation->title,
            'time'            => $now,
            'usage'           => $quota->snapshot($user, $selectedPolda?->id),
        ]);
    }

    /**
     * Susun konteks data terkurasi + peta kode rujukan -> sumber.
     *
     * @return array{0: string, 1: array<string, array>}
     */
    private function buildContext(array $scopes, $polda, bool $isNational): array
    {
        $blocks = [];
        $map = [];
        $poldaId = $polda?->id;
        $trim = fn (?string $t, int $n) => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $t)), $n);

        if (in_array('media', $scopes, true)) {
            $lines = [];
            $report = MediaIntelligenceReport::where('polda_id', $poldaId)->latest()->first();
            if ($report) {
                $lines[] = 'Ringkasan eksekutif radar media: ' . $trim($report->executive_brief, 600);
                $lines[] = 'Atensi utama: ' . $trim($report->top_concern, 300);
                if (!empty($report->sentiment_national)) {
                    $lines[] = 'Sentimen nasional: ' . $trim(json_encode($report->sentiment_national, JSON_UNESCAPED_UNICODE), 200);
                }
            }
            $articles = MediaArticle::where('polda_id', $poldaId)->latest('pub_date')->latest('id')->limit(30)->get();
            foreach ($articles as $i => $a) {
                $code = 'M' . ($i + 1);
                $lines[] = "[{$code}] {$a->source_name} | " . ($a->sentiment ?: 'netral') . ' | ' . ($a->pub_date?->format('d M H:i') ?? '-') . ' | ' . $trim($a->title, 160) . ' - ' . $trim($a->summary, 200);
                $map[$code] = ['type' => 'media', 'title' => $trim($a->title, 120), 'label' => $a->source_name, 'url' => $a->url, 'external' => true];
            }
            $blocks[] = "== MEDIA ONLINE ==\n" . ($lines ? implode("\n", $lines) : 'Belum ada data artikel.');
        }

        if (in_array('social', $scopes, true)) {
            $lines = [];
            $report = SocialIntelligenceReport::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))->latest()->first();
            if ($report) {
                $lines[] = 'Ringkasan eksekutif medsos: ' . $trim($report->executive_brief, 600);
                $lines[] = 'Atensi utama: ' . $trim($report->top_concern, 300);
            }
            $posts = SocialPost::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
                ->where('posted_at', '>=', now()->subDays(7))
                ->orderByDesc('virality_score')->limit(20)->get();
            foreach ($posts as $i => $p) {
                $code = 'S' . ($i + 1);
                $lines[] = "[{$code}] {$p->platform} @{$p->author_handle}" . ($p->is_kol ? ' (tokoh/KOL)' : '') . " | {$p->sentiment} | viralitas {$p->virality_score} | suka {$p->likes_count}, bagi {$p->shares_count} | " . $trim($p->content, 220);
                $map[$code] = ['type' => 'social', 'title' => $trim($p->content, 110), 'label' => $p->platform . ' @' . $p->author_handle, 'url' => $p->post_url, 'external' => true];
            }
            $blocks[] = "== MEDIA SOSIAL ==\n" . ($lines ? implode("\n", $lines) : 'Belum ada data media sosial.');
        }

        if (in_array('issues', $scopes, true)) {
            $lines = [];
            $open = array_merge(...array_map(fn ($g) => Issue::STATUS_GROUPS[$g], ['pending', 'active', 'clarified']));
            $issues = Issue::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
                ->whereIn('status', $open)->latest()->limit(15)->get();
            foreach ($issues as $i => $it) {
                $code = 'I' . ($i + 1);
                $lines[] = "[{$code}] {$it->category} | prioritas {$it->priority} | status {$it->status} | " . $trim($it->title, 150) . ' - ' . $trim($it->summary, 180);
                $map[$code] = ['type' => 'issue', 'title' => $trim($it->title, 120), 'label' => 'Isu ' . ($it->report_code ?: '#' . $it->id), 'url' => '/issues/' . $it->id, 'external' => false];
            }
            $warnings = EarlyWarning::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
                ->where('status', 'active')->latest()->limit(6)->get();
            foreach ($warnings as $i => $w) {
                $code = 'E' . ($i + 1);
                $lines[] = "[{$code}] Peringatan dini level {$w->level} | " . $trim($w->title, 140) . ' - ' . $trim($w->facts, 180);
                $map[$code] = ['type' => 'ews', 'title' => $trim($w->title, 120), 'label' => 'Peringatan dini', 'url' => null, 'external' => false];
            }
            $blocks[] = "== ISU INTERNAL & PERINGATAN DINI ==\n" . ($lines ? implode("\n", $lines) : 'Belum ada isu aktif.');
        }

        return [implode("\n\n", $blocks), $map];
    }
}
