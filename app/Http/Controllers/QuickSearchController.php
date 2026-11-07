<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MonitoredSocialAccount;
use App\Models\RolePermission;
use App\Models\SocialPost;
use App\Models\Task;
use App\Support\FastTime;
use App\Support\NewsSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian Cepat (Ctrl+K): satu kueri ke berita, laporan isu, tugas, unggahan medsos, dan akun pantauan.
 * - Selalu terkunci tenant aktif (resolveActiveTenant / aggregatesNational), sama dengan modul asalnya.
 * - Hanya kelompok yang modulnya diizinkan untuk peran pengguna yang dicari (setara middleware `module:`).
 * - Maks. LIMIT hasil per kelompok; menu/halaman dicocokkan di browser dari navigasi yang sudah diizinkan.
 */
class QuickSearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'groups' => []]);
        }

        $user = $request->user();
        [$selectedCode, , $poldaId] = $this->resolveActiveTenant($request);
        $national = $this->aggregatesNational($selectedCode);
        $scope = fn ($query) => $query->when(! $national, fn ($w) => $w->where('polda_id', $poldaId));
        $can = fn (string ...$keys) => $user->isSuperAdmin()
            || collect($keys)->contains(fn ($k) => RolePermission::isGranted((string) $user->role, $k, $user->polda_id));
        $like = NewsSearch::like($q);
        $match = fn ($query, array $cols) => $query->where(function ($w) use ($cols, $like) {
            foreach ($cols as $c) {
                $w->orWhereRaw("{$c} LIKE ? ESCAPE '!'", [$like]);
            }
        });

        $groups = [];

        if ($can('media_intelligence_view')) {
            $items = $match($scope(MediaArticle::query()), ['title', 'summary'])
                ->orderByDesc('pub_date')->limit(self::LIMIT)->get(['id', 'title', 'source_name', 'url', 'sentiment', 'pub_date'])
                ->map(fn ($a) => ['title' => $a->title, 'meta' => trim(strtoupper((string) $a->source_name) . ' • ' . FastTime::ago($a->getRawOriginal('pub_date')) . ' • ' . ($a->sentiment ?: 'netral'), ' •'),
                    'url' => $a->url, 'external' => true]);
            $groups[] = ['key' => 'media', 'label' => 'Berita', 'more' => '/media-intelligence?q=' . rawurlencode($q), 'items' => $items->values()];
        }

        if ($can('issues_view')) {
            $items = $match($scope(Issue::query()), ['title', 'summary', 'report_code'])
                ->latest('id')->limit(self::LIMIT)->get(['id', 'title', 'report_code', 'status', 'priority', 'created_at'])
                ->map(fn ($i) => ['title' => $i->title, 'meta' => trim(($i->report_code ? $i->report_code . ' • ' : '') . $i->status . ' • ' . $i->priority),
                    'url' => '/issues/' . $i->id, 'external' => false]);
            $groups[] = ['key' => 'issues', 'label' => 'Laporan isu', 'more' => '/issues?q=' . rawurlencode($q), 'items' => $items->values()];
        }

        if ($can('tasks_execute', 'tasks_disposition')) {
            $items = $match($scope(Task::query()), ['title', 'instructions', 'assigned_unit'])
                ->latest('id')->limit(self::LIMIT)->get(['id', 'title', 'status', 'assigned_unit', 'issue_id'])
                ->map(fn ($t) => ['title' => $t->title, 'meta' => trim($t->status . ($t->assigned_unit ? ' • ' . $t->assigned_unit : '')),
                    'url' => $t->issue_id ? '/issues/' . $t->issue_id : '/tasks', 'external' => false]);
            $groups[] = ['key' => 'tasks', 'label' => 'Tugas', 'more' => '/tasks', 'items' => $items->values()];
        }

        if ($can('social_intelligence_view')) {
            $accounts = $scope(MonitoredSocialAccount::query())
                ->where(fn ($w) => $w->whereRaw("username LIKE ? ESCAPE '!'", [$like])->orWhereRaw("display_name LIKE ? ESCAPE '!'", [$like]))
                ->limit(self::LIMIT)->get(['id', 'platform', 'username', 'display_name'])
                ->map(fn ($a) => ['title' => ($a->display_name ?: $a->username), 'meta' => $a->platform . ' • ' . $a->username, 'url' => '/social-intelligence?tab=kol', 'external' => false]);
            if ($accounts->isNotEmpty()) {
                $groups[] = ['key' => 'accounts', 'label' => 'Akun pantauan', 'more' => '/social-intelligence?tab=kol', 'items' => $accounts->values()];
            }
            $posts = $match($scope(SocialPost::query()), ['content', 'author_name', 'author_handle'])
                ->latest('posted_at')->limit(self::LIMIT)->get(['id', 'platform', 'content', 'author_name', 'author_handle', 'post_url', 'posted_at'])
                ->map(fn ($p) => ['title' => mb_strimwidth((string) $p->content, 0, 140, '…'), 'meta' => $p->platform . ' • ' . ($p->author_handle ?: $p->author_name),
                    'url' => $p->post_url, 'external' => true]);
            $groups[] = ['key' => 'social', 'label' => 'Unggahan medsos', 'more' => '/social-intelligence?q=' . rawurlencode($q), 'items' => $posts->values()];
        }

        return response()->json(['query' => $q, 'groups' => array_values(array_filter($groups, fn ($g) => count($g['items']) > 0))]);
    }
}
