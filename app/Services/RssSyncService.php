<?php

namespace App\Services;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\RssSyncLog;
use App\Models\RssSyncSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RssSyncService
{
    protected RssFeedService $feedService;

    public function __construct(?RssFeedService $feedService = null)
    {
        $this->feedService = $feedService ?? new RssFeedService();
    }

    /**
     * Jalankan sinkronisasi untuk seluruh Polda dengan perlindungan Mutex Lock & Auto-Cap.
     */
    public function syncAll(string $syncType = 'automatic', bool $force = false): array
    {
        $startTime = microtime(true);
        $initialMem = memory_get_usage(true);

        // 1. MUTEX ATOMIC LOCK: Cegah proses bertumpuk / overlapping
        $lock = Cache::lock('rss_sync_engine_lock', 600); // 10 menit batas aman

        if (! $force && ! $lock->get()) {
            RssSyncLog::create([
                'sync_type'       => $syncType,
                'status'          => 'skipped_locked',
                'message'         => 'Sinkronisasi dilewati karena proses sinkronisasi sebelumnya masih aktif berjalan (mencegah beban ganda).',
                'duration_ms'     => 0,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return [
                'success' => false,
                'locked'  => true,
                'message' => 'Proses sinkronisasi lain sedang berjalan. Antrean diamankan untuk stabilitas server.',
            ];
        }

        try {
            $globalSetting = RssSyncSetting::forPolda(null);
            $poldas = Polda::where('is_active', true)->get();
            if ($poldas->isEmpty()) {
                $poldas = Polda::all();
            }

            $totalFeeds = 0;
            $totalFetched = 0;
            $totalStored = 0;
            $totalPruned = 0;
            $resultsByPolda = [];

            foreach ($poldas as $polda) {
                $poldaRes = $this->syncPoldaInternal($polda, $globalSetting, $syncType);
                $totalFeeds += $poldaRes['feeds_count'];
                $totalFetched += $poldaRes['fetched'];
                $totalStored += $poldaRes['stored'];
                $totalPruned += $poldaRes['pruned'];
                $resultsByPolda[$polda->code] = $poldaRes;

                // Membersihkan memori siklus per Polda
                unset($poldaRes);
                gc_collect_cycles();
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $memoryMb = round((memory_get_usage(true) - $initialMem) / 1024 / 1024, 2);

            // Perbarui setting global
            $globalSetting->update([
                'last_sync_at'        => now(),
                'last_sync_status'    => 'success',
                'last_sync_message'   => "Sinkronisasi {$totalStored} berita baru dari {$totalFeeds} feed selesai dalam {$durationMs}ms.",
                'last_articles_count' => $totalStored,
            ]);

            // Catat log telemetri
            RssSyncLog::create([
                'sync_type'        => $syncType,
                'status'           => 'success',
                'feeds_processed'  => $totalFeeds,
                'articles_fetched' => $totalFetched,
                'articles_stored'  => $totalStored,
                'articles_pruned'  => $totalPruned,
                'duration_ms'      => $durationMs,
                'memory_usage_mb'  => max(0.1, $memoryMb),
                'message'          => "Sukses multi-polda: {$totalStored} disimpan, {$totalPruned} berita lama dipangkas.",
            ]);

            return [
                'success'       => true,
                'feeds_count'   => $totalFeeds,
                'articles_saved'=> $totalStored,
                'pruned_count'  => $totalPruned,
                'duration_ms'   => $durationMs,
                'memory_mb'     => $memoryMb,
                'by_polda'      => $resultsByPolda,
                'message'       => "Berhasil menyinkronkan {$totalStored} berita untuk seluruh jajaran Polda.",
            ];
        } catch (\Throwable $e) {
            Log::error('RssSyncService Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            RssSyncLog::create([
                'sync_type'       => $syncType,
                'status'          => 'failed',
                'duration_ms'     => (int) round((microtime(true) - $startTime) * 1000),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'message'         => 'Kesalahan sinkronisasi: ' . $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal sinkronisasi: ' . $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Jalankan sinkronisasi untuk satu Polda tertentu secara aman.
     */
    public function syncPolda(int|Polda $polda, string $syncType = 'manual_ui', bool $force = false): array
    {
        $startTime = microtime(true);
        $initialMem = memory_get_usage(true);

        if (is_int($polda)) {
            $polda = Polda::findOrFail($polda);
        }

        $lockKey = 'rss_sync_polda_lock_' . $polda->id;
        $lock = Cache::lock($lockKey, 300);

        if (! $force && ! $lock->get()) {
            return [
                'success' => false,
                'locked'  => true,
                'message' => "Sinkronisasi untuk {$polda->name} sedang berlangsung oleh proses lain.",
            ];
        }

        try {
            $setting = RssSyncSetting::forPolda($polda->id);
            $res = $this->syncPoldaInternal($polda, $setting, $syncType);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $memoryMb = round((memory_get_usage(true) - $initialMem) / 1024 / 1024, 2);

            $setting->update([
                'last_sync_at'        => now(),
                'last_sync_status'    => 'success',
                'last_sync_message'   => "Sinkronisasi {$res['stored']} berita baru dari {$res['feeds_count']} feed selesai.",
                'last_articles_count' => $res['stored'],
            ]);

            RssSyncLog::create([
                'polda_id'         => $polda->id,
                'sync_type'        => $syncType,
                'status'           => 'success',
                'feeds_processed'  => $res['feeds_count'],
                'articles_fetched' => $res['fetched'],
                'articles_stored'  => $res['stored'],
                'articles_pruned'  => $res['pruned'],
                'duration_ms'      => $durationMs,
                'memory_usage_mb'  => max(0.1, $memoryMb),
                'message'          => "Sukses sinkron {$polda->code}: {$res['stored']} disimpan, {$res['pruned']} berita lama dipangkas.",
            ]);

            return [
                'success'        => true,
                'polda'          => $polda->name,
                'feeds_count'    => $res['feeds_count'],
                'articles_saved' => $res['stored'],
                'pruned_count'   => $res['pruned'],
                'duration_ms'    => $durationMs,
                'memory_mb'      => $memoryMb,
                'message'        => "Berhasil menyinkronkan {$res['stored']} berita untuk {$polda->name}.",
            ];
        } catch (\Throwable $e) {
            Log::error("RssSyncService Polda {$polda->id} Error: " . $e->getMessage());

            RssSyncLog::create([
                'polda_id'        => $polda->id,
                'sync_type'       => $syncType,
                'status'          => 'failed',
                'duration_ms'     => (int) round((microtime(true) - $startTime) * 1000),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'message'         => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal sinkronisasi: ' . $e->getMessage(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Logika internal ekstraksi feed, upsert, dan auto-capping per Polda.
     */
    protected function syncPoldaInternal(Polda $polda, RssSyncSetting $setting, string $syncType): array
    {
        $feeds = MediaFeed::where('polda_id', $polda->id)
            ->where('is_active', true)
            ->get();

        if ($feeds->isEmpty()) {
            return [
                'feeds_count' => 0,
                'fetched'     => 0,
                'stored'      => 0,
                'pruned'      => 0,
            ];
        }

        // Ambil data agregasi RSS paralel
        $articles = $this->feedService->fetchAggregatedFeeds($feeds->toArray(), true);
        $fetchedCount = count($articles);
        $storedCount = 0;

        $maxPerFeed = $setting->max_articles_per_feed ?: 25;
        $itemsToStore = array_slice($articles, 0, min(500, $feeds->count() * $maxPerFeed));

        foreach ($itemsToStore as $item) {
            $link = trim($item['link'] ?? '');
            if ($link === '') continue;

            MediaArticle::updateOrCreate(
                ['url' => $link],
                [
                    'polda_id'    => $polda->id,
                    'feed_id'     => $item['source_id'] ?: null,
                    'title'       => $item['title'] ?? 'Berita Pantauan',
                    'source_name' => strtoupper($item['source_name'] ?? 'MEDIA SIBER'),
                    'category'    => $item['category'] ?? 'Regional Sulselbar',
                    'image_url'   => $item['image_url'] ?: null,
                    'summary'     => $item['summary'] ?? null,
                    'pub_date'    => !empty($item['pub_date']) ? date('Y-m-d H:i:s', strtotime($item['pub_date'])) : now(),
                    'sentiment'   => $item['sentiment'] ?? 'netral',
                ]
            );
            $storedCount++;
        }

        // AUTO-CAPPING & LEAN DATABASE GUARD:
        // Menjaga agar unbookmarked articles tidak melampaui batas kapasitas per Polda
        $maxRetained = $setting->max_retained_articles_per_polda ?: 1000;
        $prunedCount = $this->autoCapArticles($polda->id, $maxRetained);

        return [
            'feeds_count' => $feeds->count(),
            'fetched'     => $fetchedCount,
            'stored'      => $storedCount,
            'pruned'      => $prunedCount,
        ];
    }

    /**
     * Pangkas berita lama non-bookmark jika melebihi batas simpan per Polda.
     */
    protected function autoCapArticles(int $poldaId, int $maxAllowed): int
    {
        $currentCount = MediaArticle::where('polda_id', $poldaId)
            ->where('is_bookmarked', false)
            ->count();

        if ($currentCount <= $maxAllowed) {
            return 0;
        }

        $excess = $currentCount - $maxAllowed;

        // Ambil ID berita unbookmarked tertua yang melebihi batas
        $oldestIds = MediaArticle::where('polda_id', $poldaId)
            ->where('is_bookmarked', false)
            ->oldest('pub_date')
            ->limit($excess)
            ->pluck('id');

        return MediaArticle::whereIn('id', $oldestIds)->delete();
    }

    /**
     * Evaluasi apakah scheduler harus mengeksekusi sinkronisasi sekarang berdasarkan setting.
     */
    public function shouldRunNow(?RssSyncSetting $setting = null): bool
    {
        $setting = $setting ?? RssSyncSetting::forPolda(null);

        if (! $setting->is_active) {
            return false;
        }

        if ($setting->sync_mode === 'manual') {
            return false;
        }

        $now = Carbon::now();

        // 1. Mode Interval (misal setiap 180 menit / 3 jam)
        if ($setting->sync_mode === 'interval') {
            if (! $setting->last_sync_at) {
                return true;
            }

            $diffMinutes = $setting->last_sync_at->diffInMinutes($now);
            return $diffMinutes >= $setting->interval_minutes;
        }

        // 2. Mode Jam Spesifik Dinas (misal ["06:00", "12:00", "18:00"])
        if ($setting->sync_mode === 'scheduled_times') {
            $times = (array) ($setting->scheduled_times ?? []);
            $currentTimeStr = $now->format('H:i');

            if (in_array($currentTimeStr, $times, true)) {
                // Cegah eksekusi berulang pada menit yang sama
                if ($setting->last_sync_at && $setting->last_sync_at->isSameMinute($now)) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }
}

