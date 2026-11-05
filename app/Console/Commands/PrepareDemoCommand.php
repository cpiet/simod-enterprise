<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Polda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyiapkan instalasi untuk DEMO Divhumas + Polda percontohan (Sulbar) dengan data nyata saja.
 *
 *  - Hanya Divhumas dan Sulbar yang aktif; Polda lain dinonaktifkan (data intinya tidak dihapus).
 *  - Seluruh DATA CONTOH/operasional dibersihkan: isu, tugas, publikasi, siaran pers, bukti, peringatan dini, topik atensi,
 *    laporan AI, postingan & kasus medsos, riwayat chat SADEWA, serta berita non-bookmark (diisi ulang oleh sinkron RSS).
 *  - DIPERTAHANKAN: akun, Polda, Polres, feed RSS, pengaturan, menu, hak akses, berita yang di-bookmark, log audit.
 *
 * Aman secara bawaan: tanpa --force hanya melaporkan apa yang AKAN dihapus (dry-run).
 */
class PrepareDemoCommand extends Command
{
    protected $signature = 'simod:demo-prepare
        {--force : Jalankan penghapusan (tanpa ini hanya dry-run)}
        {--keep-articles : Jangan hapus berita yang sudah ada}
        {--sync : Setelah selesai, sinkron RSS Divhumas dan Sulbar}';

    protected $description = 'Bersihkan data contoh dan siapkan demo Divhumas + Polda Sulbar dengan data nyata (dry-run kecuali --force)';

    /** Urutan: tabel anak lebih dulu. */
    private const PURGE_TABLES = [
        'issue_status_history' => 'Riwayat status laporan',
        'media_files'          => 'Foto/berkas bukti',
        'task_updates'         => 'Pembaruan tugas',
        'tasks'                => 'Tugas disposisi',
        'publications'         => 'Publikasi',
        'press_releases'       => 'Siaran pers',
        'issues'               => 'Laporan/isu',
        'early_warnings'       => 'Peringatan dini (EWS)',
        'media_article_topic'  => 'Tag topik pada berita',
        'media_topics'         => 'Topik atensi',
        'media_intelligence_reports'  => 'Laporan analisis AI media',
        'social_intelligence_reports' => 'Laporan analisis AI medsos',
        'social_posts'         => 'Postingan medsos',
        'social_investigation_cases' => 'Kasus pelacakan medsos',
        'sadewa_conversations' => 'Riwayat chat SADEWA',
        'ai_usage_logs'        => 'Catatan pemakaian AI',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $keepArticles = (bool) $this->option('keep-articles');

        $this->info($force ? 'MENYIAPKAN DEMO (data akan dihapus)' : 'DRY-RUN: tidak ada yang dihapus. Tambahkan --force untuk menjalankan.');

        $plan = [];
        foreach (self::PURGE_TABLES as $table => $label) {
            if (Schema::hasTable($table)) {
                $plan[$table] = ['label' => $label, 'count' => DB::table($table)->count()];
            }
        }
        if (! $keepArticles && Schema::hasTable('media_articles')) {
            $plan['media_articles'] = ['label' => 'Berita non-bookmark (akan diisi ulang oleh sinkron RSS)', 'count' => DB::table('media_articles')->where('is_bookmarked', false)->count()];
        }

        $this->table(['Tabel', 'Isi', 'Baris'], collect($plan)->map(fn ($p, $t) => [$t, $p['label'], number_format($p['count'])])->values()->all());

        $toDeactivate = Polda::whereNotIn('code', ['DIVHUMAS', 'SULBAR'])->where('is_active', true)->pluck('code')->all();
        $toActivate = Polda::whereIn('code', ['DIVHUMAS', 'SULBAR'])->where('is_active', false)->pluck('code')->all();
        $this->line('Dinonaktifkan: ' . ($toDeactivate ? implode(', ', $toDeactivate) : '-') . ' | Diaktifkan: ' . ($toActivate ? implode(', ', $toActivate) : '-'));
        $this->line('Dipertahankan: akun, Polda, Polres, feed RSS, pengaturan, menu, hak akses, berita ber-bookmark, log audit.');

        if (! $force) {
            return self::SUCCESS;
        }

        $deleted = [];
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($plan, &$deleted) {
                foreach ($plan as $table => $p) {
                    $q = DB::table($table);
                    if ($table === 'media_articles') {
                        $q->where('is_bookmarked', false);
                    }
                    $deleted[$table] = $q->delete();
                }

                Polda::whereIn('code', ['DIVHUMAS', 'SULBAR'])->update(['is_active' => true]);
                Polda::whereNotIn('code', ['DIVHUMAS', 'SULBAR'])->update(['is_active' => false]);

                // Waktu "sinkron terakhir" bawaan seeder adalah angka karangan: kosongkan agar status kesegaran jujur.
                if (Schema::hasTable('social_settings')) {
                    DB::table('social_settings')->update(['last_synced_at' => null]);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        AuditLog::log('demo.prepare', 'system', null, [
            'summary' => 'Persiapan demo: ' . array_sum($deleted) . ' baris data contoh dibersihkan; hanya Divhumas dan Sulbar yang aktif.',
            'deleted' => $deleted,
        ]);

        $this->info(number_format(array_sum($deleted)) . ' baris dibersihkan.');

        if ($this->option('sync')) {
            foreach (['ALL', 'SULBAR'] as $code) {
                $this->info("Sinkron RSS {$code}...");
                Artisan::call('rss:sync', ['--polda' => $code, '--force' => true]);
                $this->line(trim(Artisan::output()));
            }
        } else {
            $this->line('Berikutnya: php artisan rss:sync --polda=ALL --force && php artisan rss:sync --polda=SULBAR --force (atau pakai opsi --sync).');
        }

        return self::SUCCESS;
    }
}
