<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Command Center tidak boleh menampilkan angka karangan: matriks Satwil, tren sentimen, indeks citra, dan KPI
 * seluruhnya dihitung dari data nyata dan bernilai null/"belum ada data" bila datanya memang belum ada.
 */
class CommandCenterRealDataTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private Polda $divhumas;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code, string $name, bool $active = true) => Polda::create(['code' => $code, 'name' => $name, 'region' => $name, 'timezone' => 'Asia/Makassar', 'is_active' => $active]);
        $this->sulbar = $mk('SULBAR', 'Polda Sulbar');
        $this->gorontalo = $mk('GORONTALO', 'Polda Gorontalo');
        $this->divhumas = $mk('DIVHUMAS', 'Divhumas Polri');
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'username' => 'adm_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);
    }

    private function leader(Polda $polda): User
    {
        return User::create(['polda_id' => $polda->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);
    }

    private function issue(Polda $polda, string $status, string $priority = 'medium', ?Polres $polres = null, string $sentiment = 'netral'): Issue
    {
        $this->seq++;

        return Issue::create([
            'polda_id' => $polda->id, 'polres_id' => $polres?->id, 'title' => "Laporan uji {$this->seq}", 'summary' => 'Ringkasan.',
            'category' => 'Kriminalitas', 'priority' => $priority, 'status' => $status, 'sentiment' => $sentiment,
        ]);
    }

    private function nationalPage(): \Illuminate\Testing\TestResponse
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/tenant/switch', ['code' => 'ALL']);

        return $this->get('/');
    }

    public function test_empty_system_shows_no_invented_numbers(): void
    {
        $this->nationalPage()->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('kpi.task_completion_rate', null)
            ->where('publicIndex.score', null)
            ->where('publicIndex.change', null)
            ->where('publicIndex.status', null)
            ->where('overallSituation.text', 'BELUM ADA LAPORAN LAPANGAN')
            ->where('avgPositivePct', null)
            ->has('sentiment7Days', 7)
            ->where('sentiment7Days', fn ($days) => collect($days)->every(fn ($d) => $d['total'] === 0 && $d['positif'] === null && $d['negatif'] === null)));
    }

    public function test_national_matrix_has_one_row_per_active_polda_with_status_from_active_reports(): void
    {
        config(['simod.national_aggregate' => true]);
        Polda::create(['code' => 'SULSEL', 'name' => 'Polda Sulsel', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => false]);
        $this->issue($this->sulbar, 'investigasi', 'critical');      // aktif + prioritas  -> ATENSI
        $this->issue($this->gorontalo, 'monitoring', 'medium');      // aktif biasa         -> WASPADA
        $this->issue($this->gorontalo, 'selesai', 'high');           // selesai tidak dihitung
        $this->issue($this->divhumas, 'selesai', 'critical');        // semua selesai       -> KONDUSIF
        $this->issue($this->divhumas, 'arsip', 'high');              // arsip tidak aktif

        $this->nationalPage()->assertInertia(fn (Assert $page) => $page
            ->has('satwilMatrix', 3)
            ->where('situationMap.mode', 'national')
            ->where('satwilMatrix', function ($rows) {
                $by = collect($rows)->keyBy('code');

                return $by['SULBAR']['status'] === 'ATENSI' && $by['SULBAR']['priority_count'] === 1
                    && $by['GORONTALO']['status'] === 'WASPADA' && $by['GORONTALO']['issues_count'] === 1 && $by['GORONTALO']['total_issues'] === 2
                    && $by['DIVHUMAS']['status'] === 'KONDUSIF' && $by['DIVHUMAS']['issues_count'] === 0
                    && $by['DIVHUMAS']['switch_code'] === 'ALL' && $by['SULBAR']['switch_code'] === 'SULBAR'
                    && ! $by->has('SULSEL')                                   // Polda nonaktif tidak tampil
                    && collect($rows)->pluck('status')->all() === ['ATENSI', 'WASPADA', 'KONDUSIF']; // urut tingkat urgensi
            }));
    }

    public function test_a_satwil_without_any_report_is_labelled_no_data_not_conducive(): void
    {
        $this->nationalPage()->assertInertia(fn (Assert $page) => $page
            ->where('satwilMatrix', fn ($rows) => collect($rows)->every(fn ($r) => $r['status'] === 'BELUM ADA DATA' && $r['score'] === null && $r['issues_count'] === 0)));
    }

    public function test_regional_matrix_rows_per_polres_with_media_mentions_and_polda_level_reports(): void
    {
        $mamuju = Polres::create(['polda_id' => $this->sulbar->id, 'code' => 'MMJ', 'name' => 'Polresta Mamuju', 'is_active' => true]);
        $majene = Polres::create(['polda_id' => $this->sulbar->id, 'code' => 'MJN', 'name' => 'Polres Majene', 'is_active' => true]);
        Polres::create(['polda_id' => $this->sulbar->id, 'code' => 'XX', 'name' => 'Polres Ab', 'is_active' => true]); // nama terlalu singkat
        $this->issue($this->sulbar, 'investigasi', 'high', $mamuju, 'positif');
        $this->issue($this->sulbar, 'selesai', 'low', $majene, 'positif');
        $this->issue($this->sulbar, 'monitoring', 'medium', null);   // tingkat Polda (tanpa Satwil)

        foreach ([['Bentrok warga di Mamuju', 'negatif'], ['Polisi ungkap kasus di Mamuju', 'positif'], ['Harga cabai di Majene', 'netral']] as $i => [$title, $sent]) {
            MediaArticle::create(['polda_id' => $this->sulbar->id, 'title' => $title, 'source_name' => 'P', 'category' => 'Umum', 'url' => 'https://t.test/' . $i, 'summary' => 's', 'pub_date' => now()->subDay(), 'sentiment' => $sent]);
        }
        MediaArticle::create(['polda_id' => $this->sulbar->id, 'title' => 'Berita Mamuju dua minggu lalu', 'source_name' => 'P', 'category' => 'Umum', 'url' => 'https://t.test/old', 'summary' => 's', 'pub_date' => now()->subDays(14), 'sentiment' => 'negatif']);

        $this->actingAs($this->leader($this->sulbar))->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('situationMap.mode', 'regional')
            ->has('situationMap.regions', 3)
            ->where('situationMap.regions', fn ($rows) => collect($rows)->firstWhere('name', 'Polresta Mamuju')['articles'][0]['url'] !== null
                && count(collect($rows)->firstWhere('name', 'Polresta Mamuju')['articles']) === 2
                && collect($rows)->firstWhere('name', 'Polresta Mamuju')['search_q'] === '"Mamuju"')
            ->has('satwilMatrix', 4)
            ->where('satwilMatrix', function ($rows) {
                $by = collect($rows)->keyBy('name');

                return $by['Polresta Mamuju']['status'] === 'ATENSI'
                    && $by['Polresta Mamuju']['media_7d'] === 2 && $by['Polresta Mamuju']['media_negative_7d'] === 1
                    && $by['Polresta Mamuju']['score'] === '100%'
                    && $by['Polres Majene']['status'] === 'KONDUSIF' && $by['Polres Majene']['media_7d'] === 1
                    && $by['Polres Ab']['media_7d'] === null
                    && $by['Tingkat Polda (tanpa Satwil)']['status'] === 'WASPADA'
                    && $by['Tingkat Polda (tanpa Satwil)']['level'] === 'polda_unit';
            }));
    }

    public function test_public_index_and_change_are_computed_from_real_windows(): void
    {
        // 7 hari terakhir: 3 positif, 1 negatif -> 75%. 7 hari sebelumnya: 1 positif, 1 negatif -> 50%. Selisih +25 poin.
        foreach ([['positif', 1], ['positif', 2], ['positif', 3], ['negatif', 2], ['positif', 9], ['negatif', 10]] as $i => [$sent, $daysAgo]) {
            MediaArticle::create(['polda_id' => $this->sulbar->id, 'title' => "Berita indeks {$i}", 'source_name' => 'P', 'category' => 'Umum', 'url' => 'https://i.test/' . $i, 'summary' => 's', 'pub_date' => now()->subDays($daysAgo), 'sentiment' => $sent]);
        }
        SocialPost::create(['polda_id' => $this->sulbar->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'a', 'content' => 'c', 'post_url' => 'https://x.com/1', 'sentiment' => 'netral', 'posted_at' => now()]);

        $this->actingAs($this->leader($this->sulbar))->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('publicIndex.score', 75)
            ->where('publicIndex.change', '+25 poin')
            ->where('publicIndex.trend', 'up')
            ->where('publicIndex.status', 'Positif')
            ->where('publicIndex.sample', 4)
            ->where('avgPositivePct', 60)); // 3 positif dari 5 data (termasuk 1 netral medsos)
    }

    public function test_active_report_counts_exclude_drafts_done_and_archived(): void
    {
        $this->issue($this->sulbar, 'draft', 'critical');
        $this->issue($this->sulbar, 'resolved', 'critical');
        $this->issue($this->sulbar, 'arsip', 'critical');
        $this->issue($this->sulbar, 'in_progress', 'critical');   // status lama = tahap aktif

        $this->actingAs($this->leader($this->sulbar))->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('kpi.priority_issues', 1)
            ->where('kpi.field_reports', 4)
            ->where('overallSituation.color', 'rose'));
    }
}
