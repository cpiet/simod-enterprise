<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Services\NewsCleanupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsCleanupController extends SystemModuleController
{
    public function index(Request $request, NewsCleanupService $service): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $days = (int) $request->query('days', config('simod.media_retention_days', 90));
        $target = $request->query('target', 'ALL'); // ALL atau kode Polda
        $targetPolda = $target !== 'ALL' ? Polda::where('code', $target)->first() : null;
        $targetId = $targetPolda?->id;

        $perPolda = Polda::where('is_active', true)->orderBy('name')->get()->map(fn ($p) => [
            'id'         => $p->id,
            'code'       => $p->code,
            'name'       => $p->name,
            'total'      => MediaArticle::where('polda_id', $p->id)->count(),
            'bookmarked' => MediaArticle::where('polda_id', $p->id)->where('is_bookmarked', true)->count(),
            'deletable'  => $service->count($p->id, $days),
        ])->values();

        $totals = [
            'total'      => MediaArticle::count(),
            'bookmarked' => MediaArticle::where('is_bookmarked', true)->count(),
            'deletable'  => $service->count(null, $days),
        ];

        // Recent cleanup audit logs
        $cleanupLogs = AuditLog::with('user')
            ->where('action', 'news.cleanup')
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'user'       => $log->user ? $log->user->name : 'Administrator',
                'target'     => $log->metadata['target'] ?? 'ALL',
                'days'       => $log->metadata['days'] ?? 90,
                'deleted'    => $log->metadata['deleted'] ?? 0,
                'time_ago'   => $log->created_at ? $log->created_at->diffForHumans() : 'Baru saja',
                'created_at' => $log->created_at ? $log->created_at->format('d M Y, H:i') : now()->format('d M Y, H:i'),
            ]);

        return Inertia::render('Settings/NewsCleanup', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'perPolda'      => $perPolda,
            'totals'        => $totals,
            'days'          => $days,
            'target'        => $targetPolda ? $target : 'ALL',
            'preview'       => $service->count($targetId, $days),
            'retentionDays' => (int) config('simod.media_retention_days'),
            'cleanupLogs'   => $cleanupLogs,
        ]);
    }

    public function purge(Request $request, NewsCleanupService $service)
    {
        $data = $request->validate([
            'days'   => 'required|integer|min:0|max:3650',
            'target' => 'required|string',
        ]);

        $polda = $data['target'] !== 'ALL' ? Polda::where('code', $data['target'])->first() : null;
        if ($data['target'] !== 'ALL' && ! $polda) {
            return back()->with('error', 'Polda target tidak ditemukan.');
        }

        $deleted = $service->purge($polda?->id, (int) $data['days']);

        $targetName = $polda ? $polda->name : 'SEMUA POLDA';
        $dayDesc = $data['days'] == 0 ? 'semua umur' : "lebih dari {$data['days']} hari";

        AuditLog::log('news.cleanup', 'media_articles', null, [
            'target'      => $data['target'],
            'target_name' => $targetName,
            'days'        => (int) $data['days'],
            'deleted'     => $deleted,
        ]);

        return back()->with('success', "✓ Berhasil membersihkan {$deleted} artikel berita ({$dayDesc}) untuk {$targetName}. Bookmark aman tersimpan.");
    }
}
