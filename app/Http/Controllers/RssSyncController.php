<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\RssSyncLog;
use App\Models\RssSyncSetting;
use App\Services\RssSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RssSyncController extends Controller
{
    /**
     * Tampilan Konfigurasi Otomasi Sinkron RSS & Telemetri Stabilitas.
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::where('is_active', true)->orderBy('name')->get();
        if ($allPoldas->isEmpty()) {
            $allPoldas = Polda::all();
        }

        $selectedCode = strtoupper((string) $request->query('polda', 'SULBAR'));
        $selectedPolda = $allPoldas->firstWhere('code', $selectedCode) ?: $allPoldas->first();
        $poldaId = $selectedPolda ? $selectedPolda->id : null;

        // Ambil konfigurasi (spesifik polda atau global)
        $setting = RssSyncSetting::forPolda($poldaId);
        $globalSetting = RssSyncSetting::forPolda(null);

        // Telemetri Kapasitas & Kestabilan
        $poldaArticleCount = $poldaId ? MediaArticle::where('polda_id', $poldaId)->count() : 0;
        $poldaBookmarkedCount = $poldaId ? MediaArticle::where('polda_id', $poldaId)->where('is_bookmarked', true)->count() : 0;
        $totalArticleCount = MediaArticle::count();
        $totalFeedCount = $poldaId ? MediaFeed::where('polda_id', $poldaId)->where('is_active', true)->count() : MediaFeed::where('is_active', true)->count();
        $isLocked = Cache::has('rss_sync_engine_lock') || ($poldaId && Cache::has('rss_sync_polda_lock_' . $poldaId));

        // 15 Log Telemetri Terakhir
        $logs = RssSyncLog::with('polda')
            ->when($poldaId, function ($q) use ($poldaId) {
                $q->where(function ($sub) use ($poldaId) {
                    $sub->where('polda_id', $poldaId)->orWhereNull('polda_id');
                });
            })
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function ($log) {
                return [
                    'id'               => $log->id,
                    'polda_name'       => $log->polda?->name ?: 'Seluruh Polda (Konsolidasi)',
                    'sync_type'        => $log->sync_type,
                    'status'           => $log->status,
                    'feeds_processed'  => $log->feeds_processed,
                    'articles_fetched' => $log->articles_fetched,
                    'articles_stored'  => $log->articles_stored,
                    'articles_pruned'  => $log->articles_pruned,
                    'duration_ms'      => $log->duration_ms,
                    'memory_usage_mb'  => (float) $log->memory_usage_mb,
                    'message'          => $log->message,
                    'created_at'       => $log->created_at ? $log->created_at->format('d M Y H:i:s') : '-',
                    'time_ago'         => $log->created_at ? $log->created_at->diffForHumans() : '-',
                ];
            });

        return Inertia::render('Settings/RssSync', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'setting'        => [
                'id'                              => $setting->id,
                'sync_mode'                       => $setting->sync_mode,
                'interval_minutes'                => $setting->interval_minutes,
                'scheduled_times'                 => $setting->scheduled_times ?: ['06:00', '12:00', '18:00'],
                'max_articles_per_feed'           => $setting->max_articles_per_feed,
                'max_retained_articles_per_polda' => $setting->max_retained_articles_per_polda,
                'auto_prune_days'                 => $setting->auto_prune_days,
                'timeout_seconds'                 => $setting->timeout_seconds,
                'is_active'                       => (bool) $setting->is_active,
                'last_sync_at'                    => $setting->last_sync_at ? $setting->last_sync_at->format('d M Y, H:i:s') . ' WITA' : 'Belum pernah sinkron',
                'last_sync_status'                => $setting->last_sync_status ?: 'idle',
                'last_sync_message'               => $setting->last_sync_message ?: 'Menunggu jadwal sinkronisasi berikutnya.',
                'last_articles_count'             => $setting->last_articles_count,
            ],
            'globalSetting'  => [
                'last_sync_at'     => $globalSetting->last_sync_at ? $globalSetting->last_sync_at->format('d M Y, H:i:s') . ' WITA' : '-',
                'last_sync_status' => $globalSetting->last_sync_status ?: 'idle',
            ],
            'telemetry'      => [
                'is_locked'             => $isLocked,
                'polda_articles'        => $poldaArticleCount,
                'polda_bookmarked'      => $poldaBookmarkedCount,
                'total_articles'        => $totalArticleCount,
                'active_feeds'          => $totalFeedCount,
                'memory_limit'          => ini_get('memory_limit') ?: '512M',
                'max_execution_time'    => (int) ini_get('max_execution_time'),
            ],
            'recentLogs'     => $logs,
        ]);
    }

    /**
     * Simpan Perubahan Pengaturan Otomasi Sinkronisasi.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'polda_id'                        => 'nullable|exists:poldas,id',
            'sync_mode'                       => 'required|in:manual,interval,scheduled_times',
            'interval_minutes'                => 'required|integer|min:30|max:2880',
            'scheduled_times'                 => 'nullable|array',
            'scheduled_times.*'               => ['string', 'date_format:H:i'],
            'max_articles_per_feed'           => 'required|integer|min:5|max:100',
            'max_retained_articles_per_polda' => 'required|integer|min:200|max:5000',
            'auto_prune_days'                 => 'required|integer|min:7|max:365',
            'timeout_seconds'                 => 'required|integer|min:3|max:30',
            'is_active'                       => 'required|boolean',
            'apply_to_all_poldas'             => 'nullable|boolean',
        ]);

        $poldaId = $validated['polda_id'] ?? null;
        $applyToAll = ! empty($validated['apply_to_all_poldas']);

        $dataToSave = [
            'sync_mode'                       => $validated['sync_mode'],
            'interval_minutes'                => $validated['interval_minutes'],
            'scheduled_times'                 => $validated['scheduled_times'] ?? ['06:00', '12:00', '18:00'],
            'max_articles_per_feed'           => $validated['max_articles_per_feed'],
            'max_retained_articles_per_polda' => $validated['max_retained_articles_per_polda'],
            'auto_prune_days'                 => $validated['auto_prune_days'],
            'timeout_seconds'                 => $validated['timeout_seconds'],
            'is_active'                       => $validated['is_active'],
            'updated_by'                      => auth()->id(),
        ];

        if ($applyToAll) {
            // Terapkan ke setting global dan seluruh Polda yang ada
            RssSyncSetting::updateOrCreate(['polda_id' => null], $dataToSave);
            $allPoldas = Polda::all();
            foreach ($allPoldas as $p) {
                RssSyncSetting::updateOrCreate(['polda_id' => $p->id], $dataToSave);
            }
            $targetDesc = 'seluruh jajaran Polda';
        } else {
            RssSyncSetting::updateOrCreate(['polda_id' => $poldaId], $dataToSave);
            $targetDesc = $poldaId ? 'Polda terpilih' : 'Pengaturan Global';
        }

        // Catat ke Audit Log
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'UPDATE_RSS_SYNC_SETTINGS',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => "Konfigurasi otomasi sinkron RSS diperbarui untuk {$targetDesc}. Mode: {$validated['sync_mode']}.",
        ]);

        return back()->with('success', "Konfigurasi otomasi sinkron RSS berhasil disimpan untuk {$targetDesc}!");
    }

    /**
     * Tombol Uji Coba Sinkronisasi Manual (On-Demand) dengan Keamanan Penuh.
     */
    public function triggerSync(Request $request, RssSyncService $syncService): RedirectResponse
    {
        $targetScope = $request->input('scope', 'polda'); // 'polda' or 'all'
        $poldaCode = strtoupper((string) $request->input('polda', 'SULBAR'));
        $polda = Polda::where('code', $poldaCode)->first();

        if ($targetScope === 'all' || ! $polda) {
            $res = $syncService->syncAll('manual_ui');
            if (! $res['success']) {
                return back()->with('error', $res['message']);
            }
            return back()->with('success', "Uji sinkron multi-polda selesai: {$res['articles_saved']} berita disimpan, {$res['pruned_count']} dipangkas ({$res['duration_ms']}ms).");
        }

        $res = $syncService->syncPolda($polda, 'manual_ui');
        if (! $res['success']) {
            return back()->with('error', $res['message']);
        }

        return back()->with('success', "Uji sinkron {$polda->name} selesai: {$res['articles_saved']} berita disimpan, {$res['pruned_count']} dipangkas ({$res['duration_ms']}ms).");
    }
}
