<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\RssSyncLog;
use App\Models\RssSyncSetting;
use App\Services\RssSyncService;
use Illuminate\Http\JsonResponse;
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

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? $allPoldas->first();
        $poldaId = $selectedPolda ? $selectedPolda->id : null;

        // Ambil konfigurasi (spesifik polda atau global). JADWAL hanya dibaca dari global: satu mesin menyinkronkan
        // seluruh Polda, jadi mode/interval/jam per Polda tidak pernah ditegakkan penjadwal.
        $setting = RssSyncSetting::forPolda($poldaId);
        $globalSetting = RssSyncSetting::forPolda(null);
        [$tz, $tzLabel] = $this->displayTimezone($selectedPolda, $selectedCode);
        $fmt = fn ($dt) => $dt ? $dt->copy()->setTimezone($tz)->translatedFormat('d M Y, H:i') . ' ' . $tzLabel : null;
        $nextRun = app(RssSyncService::class)->nextRunAt($globalSetting);
        $lastTick = \App\Support\ScheduledTasks::lastTick();

        // Sumber Feed RSS untuk Polda ini
        $feeds = MediaFeed::withCount('articles')
            ->when($poldaId, function ($q) use ($poldaId) {
                $q->where('polda_id', $poldaId);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($feed) {
                return [
                    'id'             => $feed->id,
                    'polda_id'       => $feed->polda_id,
                    'name'           => $feed->name,
                    'url'            => $feed->url,
                    'category'       => $feed->category ?: 'Nasional & Umum',
                    'is_active'      => (bool) $feed->is_active,
                    'articles_count' => (int) $feed->articles_count,
                    'created_at'     => $feed->created_at ? $feed->created_at->format('d M Y') : '-',
                ];
            });

        $categories = $feeds->pluck('category')->filter()->unique()->values();

        // Telemetri Kapasitas & Kestabilan
        $poldaArticleCount = $poldaId ? MediaArticle::where('polda_id', $poldaId)->count() : 0;
        $poldaBookmarkedCount = $poldaId ? MediaArticle::where('polda_id', $poldaId)->where('is_bookmarked', true)->count() : 0;
        $totalArticleCount = MediaArticle::count();
        $totalFeedCount = $feeds->count();
        $activeFeedsCount = $feeds->where('is_active', true)->count();
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
                // Jadwal = pengaturan global (yang ditegakkan penjadwal).
                'sync_mode'                       => $globalSetting->sync_mode,
                'interval_minutes'                => $globalSetting->interval_minutes,
                'scheduled_times'                 => $globalSetting->scheduled_times ?: ['06:00', '12:00', '18:00'],
                'max_articles_per_feed'           => $setting->max_articles_per_feed,
                'max_retained_articles_per_polda' => $setting->max_retained_articles_per_polda,
                'auto_prune_days'                 => $setting->auto_prune_days,
                'timeout_seconds'                 => $setting->timeout_seconds,
                'is_active'                       => (bool) $globalSetting->is_active,
                'last_sync_at'                    => $fmt($setting->last_sync_at) ?? 'Belum pernah sinkron',
                'last_sync_status'                => $setting->last_sync_status ?: 'idle',
                'last_sync_message'               => $setting->last_sync_message ?: 'Menunggu jadwal sinkronisasi berikutnya.',
                'last_articles_count'             => $setting->last_articles_count,
            ],
            'globalSetting'  => [
                'last_sync_at'     => $fmt($globalSetting->last_sync_at) ?? '-',
                'last_sync_status' => $globalSetting->last_sync_status ?: 'idle',
                'last_sync_message' => $globalSetting->last_sync_message,
            ],
            // Status jadwal otomatis yang benar-benar ditegakkan (global + cron).
            'schedule'       => [
                'next_run_at'      => $fmt($nextRun),
                'scheduler_active' => $lastTick !== null && time() - $lastTick < 180,
                'last_tick_at'     => $lastTick ? $fmt(\Illuminate\Support\Carbon::createFromTimestamp($lastTick)) : null,
                'timezone_label'   => $tzLabel,
                'can_edit'         => (bool) $request->user()?->isSuperAdmin(),
            ],
            'feeds'          => $feeds,
            'feedCategories' => $categories,
            'feedsTelemetry' => [
                'total_feeds'    => $totalFeedCount,
                'active_feeds'   => $activeFeedsCount,
                'inactive_feeds' => $totalFeedCount - $activeFeedsCount,
            ],
            'telemetry'      => [
                'is_locked'             => $isLocked,
                'polda_articles'        => $poldaArticleCount,
                'polda_bookmarked'      => $poldaBookmarkedCount,
                'total_articles'        => $totalArticleCount,
                'active_feeds'          => $activeFeedsCount,
                'memory_limit'          => ini_get('memory_limit') ?: '512M',
                'max_execution_time'    => (int) ini_get('max_execution_time'),
            ],
            'recentLogs'     => $logs,
        ]);
    }

    /**
     * Zona waktu tampilan: lingkup nasional → WIB; Polda → zona Polda. Waktu tersimpan dalam UTC.
     *
     * @return array{0: string, 1: string}
     */
    private function displayTimezone(?Polda $polda, ?string $code): array
    {
        $tz = ($this->aggregatesNational((string) $code) || ! $polda || in_array($polda->code, ['ALL', 'DIVHUMAS'], true))
            ? 'Asia/Jakarta'
            : ($polda->timezone ?: 'Asia/Makassar');

        return [$tz, match ($tz) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Jayapura' => 'WIT',
            default => 'WITA',
        }];
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

        // Jadwal (mode/interval/jam/aktif) hanya ditegakkan dari pengaturan GLOBAL karena satu mesin menyinkronkan
        // seluruh Polda. Karena berdampak nasional, hanya Super Admin yang dapat mengubahnya.
        $scheduleNote = '';
        $global = RssSyncSetting::forPolda(null);
        $schedule = [
            'sync_mode'        => $validated['sync_mode'],
            'interval_minutes' => $validated['interval_minutes'],
            'scheduled_times'  => $validated['scheduled_times'] ?? ['06:00', '12:00', '18:00'],
            'is_active'        => $validated['is_active'],
        ];
        $scheduleChanged = $global->sync_mode !== $schedule['sync_mode']
            || (int) $global->interval_minutes !== (int) $schedule['interval_minutes']
            || array_values((array) $global->scheduled_times) !== array_values($schedule['scheduled_times'])
            || (bool) $global->is_active !== (bool) $schedule['is_active'];
        if ($scheduleChanged) {
            if ($request->user()?->isSuperAdmin()) {
                $global->update($schedule + ['updated_by' => auth()->id()]);
                $scheduleNote = ' Jadwal sinkron nasional diperbarui.';
            } else {
                $scheduleNote = ' Jadwal sinkron nasional tidak diubah (hanya Super Admin).';
            }
        }

        // Batas & retensi (bukan jadwal) tetap dapat disimpan per Polda atau untuk semua Polda.
        $dataToSave = [
            'max_articles_per_feed'           => $validated['max_articles_per_feed'],
            'max_retained_articles_per_polda' => $validated['max_retained_articles_per_polda'],
            'auto_prune_days'                 => $validated['auto_prune_days'],
            'timeout_seconds'                 => $validated['timeout_seconds'],
            'updated_by'                      => auth()->id(),
        ];

        if ($applyToAll) {
            // Terapkan batas ke setting global dan seluruh Polda yang ada
            RssSyncSetting::forPolda(null)->update($dataToSave);
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

        return back()->with('success', "Konfigurasi otomasi sinkron RSS berhasil disimpan untuk {$targetDesc}.{$scheduleNote}");
    }

    /**
     * Tombol Uji Coba Sinkronisasi Manual (On-Demand) dengan Keamanan Penuh.
     */
    public function triggerSync(Request $request, RssSyncService $syncService): RedirectResponse
    {
        $targetScope = $request->input('scope', 'polda'); // 'polda' or 'all'
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && $user->polda_id) {
            // Personel kewilayahan: selalu Polda sendiri, tidak boleh sinkron lintas Polda.
            $targetScope = 'polda';
            $polda = Polda::find($user->polda_id);
        } else {
            $poldaCode = strtoupper((string) $request->input('polda', 'SULBAR'));
            $polda = Polda::where('code', $poldaCode)->first();
        }

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

    /**
     * Tambah Sumber RSS Baru untuk Polda Aktif.
     */
    public function storeFeed(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'polda_id' => 'nullable|exists:poldas,id',
            'name'     => 'required|string|max:100',
            'url'      => ['required', 'url', 'max:255', fn ($attr, $value, $fail) => \App\Support\SafeUrl::isPublicHttp($value, false) ? null : $fail('URL feed harus http/https ke alamat publik.')],
            'category' => 'nullable|string|max:50',
        ]);

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $targetPoldaId = $validated['polda_id'] ?? $poldaId;

        $feed = MediaFeed::updateOrCreate(
            ['polda_id' => $targetPoldaId, 'url' => $validated['url']],
            [
                'name'      => $validated['name'],
                'category'  => $validated['category'] ?: 'Nasional & Umum',
                'is_active' => true,
            ]
        );

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'CREATE_RSS_FEED',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => "Sumber RSS baru ditambahkan: {$feed->name} ({$feed->url}) untuk Polda ID {$targetPoldaId}.",
        ]);

        return back()->with('success', "✓ Sumber RSS [{$feed->name}] berhasil ditambahkan!");
    }

    /**
     * Toggle status aktif / nonaktif Sumber RSS.
     */
    public function toggleFeed(Request $request, $id): RedirectResponse
    {
        $feed = MediaFeed::findOrFail($id);
        $this->authorizeTenantRecord($request, $feed);

        $feed->is_active = !$feed->is_active;
        $feed->save();

        $status = $feed->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'TOGGLE_RSS_FEED',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => "Sumber RSS [{$feed->name}] {$status}.",
        ]);

        return back()->with('success', "✓ Sumber RSS [{$feed->name}] berhasil {$status}.");
    }

    /**
     * Hapus Sumber RSS.
     */
    public function deleteFeed(Request $request, $id): RedirectResponse
    {
        $feed = MediaFeed::findOrFail($id);
        $this->authorizeTenantRecord($request, $feed);
        $feedName = $feed->name;
        $feed->delete();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'DELETE_RSS_FEED',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => "Sumber RSS [{$feedName}] dihapus oleh admin.",
        ]);

        return back()->with('success', "✓ Sumber RSS [{$feedName}] berhasil dihapus.");
    }

    /**
     * Uji kelayakan URL feed RSS secara realtime (latensi, HTTP status, dan validasi XML).
     */
    public function testFeed(Request $request): JsonResponse
    {
        $url = $request->input('url');
        if (!filter_var($url, FILTER_VALIDATE_URL) || ! \App\Support\SafeUrl::isPublicHttp($url)) {
            return response()->json(['valid' => false, 'message' => 'URL tidak valid atau tidak diizinkan (hanya http/https ke alamat publik).'], 422);
        }

        $start = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SIMOD-Enterprise-Validator/2.0');
        curl_setopt_array($ch, \App\Support\SafeUrl::curlOptions());
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        // Redirect ke alamat internal dianggap gagal (pencegahan SSRF lewat pengalihan).
        if ($effective !== '' && $effective !== $url && ! \App\Support\SafeUrl::isPublicHttp($effective)) {
            $body = null;
            $httpCode = 0;
        }

        $latency = round((microtime(true) - $start) * 1000);

        if ($httpCode >= 200 && $httpCode < 400 && !empty($body)) {
            $isXml = str_contains($body, '<rss') || str_contains($body, '<feed') || str_contains($body, '<channel');
            preg_match_all('/<(item|entry)[\s>]/i', $body, $matches);
            $itemsCount = count($matches[0] ?? []);

            return response()->json([
                'valid'       => true,
                'http_code'   => $httpCode,
                'latency_ms'  => $latency,
                'items_count' => $itemsCount,
                'is_xml'      => $isXml,
                'message'     => $isXml
                    ? "✓ URL Feed RSS Aktif & Valid! Terdeteksi {$itemsCount} artikel ({$latency}ms)."
                    : "Peringatan: URL merespons HTTP {$httpCode} tetapi bukan format RSS/Atom standar.",
            ]);
        }

        return response()->json([
            'valid'      => false,
            'http_code'  => $httpCode ?: 0,
            'latency_ms' => $latency,
            'message'    => "Gagal menghubungi URL feed (HTTP {$httpCode}, {$latency}ms). Pastikan server portal dapat diakses publik.",
        ], 400);
    }
}
