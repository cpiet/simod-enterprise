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
            'code'       => $p->code,
            'name'       => $p->name,
            'total'      => MediaArticle::where('polda_id', $p->id)->count(),
            'bookmarked' => MediaArticle::where('polda_id', $p->id)->where('is_bookmarked', true)->count(),
            'deletable'  => $service->count($p->id, $days),
        ])->values();

        return Inertia::render('Settings/NewsCleanup', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'perPolda'      => $perPolda,
            'days'          => $days,
            'target'        => $targetPolda ? $target : 'ALL',
            'preview'       => $service->count($targetId, $days),
            'retentionDays' => (int) config('simod.media_retention_days'),
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
            return back()->with('error', 'Polda tidak ditemukan.');
        }

        $deleted = $service->purge($polda?->id, (int) $data['days']);

        AuditLog::log('news.cleanup', 'media_articles', null, [
            'target'  => $data['target'],
            'days'    => (int) $data['days'],
            'deleted' => $deleted,
        ]);

        return back()->with('success', "{$deleted} berita non-bookmark berhasil dibersihkan.");
    }
}
