<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MonitoredSocialAccount;
use App\Models\Polda;
use App\Models\User;
use App\Services\SituationMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Peta Situasi Command Center: 38 provinsi, pemberitaan 7 hari, pencocokan kata utuh + pengecualian, status tertulis,
 * berita per wilayah dengan tautan; serta sorotan isu aktif & unggahan bermetrik nyata.
 */
class SituationMapTest extends TestCase
{
    use RefreshDatabase;

    private Polda $divhumas;
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->divhumas = Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divisi Humas Polri', 'zone' => 'WIB', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function article(string $title, string $sentiment = 'netral', int $daysAgo = 1): void
    {
        $this->n++;
        MediaArticle::create(['polda_id' => $this->divhumas->id, 'title' => $title, 'summary' => '-', 'source_name' => 'Portal', 'category' => 'Umum',
            'url' => 'https://berita.test/' . $this->n, 'pub_date' => now()->subDays($daysAgo), 'sentiment' => $sentiment]);
    }

    private function region(array $map, string $code): array
    {
        return collect($map['regions'])->firstWhere('code', $code);
    }

    public function test_matches_whole_words_with_exclusions_and_only_last_seven_days(): void
    {
        $this->article('Banjir di Pekanbaru, Riau');                 // RIAU
        $this->article('Polisi Kepulauan Riau ungkap penyelundupan');  // KEPRI saja, bukan RIAU
        $this->article('Kericuhan di Maluku Utara');                   // MALUT saja
        $this->article('Festival budaya di Papua Barat Daya');         // PAPBD saja
        $this->article('Situasi Riau dua minggu lalu', 'negatif', 14); // di luar 7 hari
        $this->article('Kapolri resmikan program nasional');           // tidak menyebut wilayah

        $map = app(SituationMapService::class)->national([$this->divhumas->id]);
        $this->assertCount(38, $map['regions']);
        $this->assertSame(1, $this->region($map, 'RIAU')['articles_count']);
        $this->assertSame(1, $this->region($map, 'KEPRI')['articles_count']);
        $this->assertSame(0, $this->region($map, 'MALUKU')['articles_count']);
        $this->assertSame(1, $this->region($map, 'MALUT')['articles_count']);
        $this->assertSame(0, $this->region($map, 'PAPUA')['articles_count']);
        $this->assertSame(0, $this->region($map, 'PAPBAR')['articles_count']);
        $this->assertSame(1, $this->region($map, 'PAPBD')['articles_count']);
        $this->assertSame(5, $map['total_articles']);
        $this->assertSame(1, $map['unmatched']);
    }

    public function test_status_rules_and_articles_with_links(): void
    {
        foreach (['negatif', 'negatif', 'negatif', 'netral'] as $s) {
            $this->article('Bentrok warga di Makassar', $s);           // 3/4 negatif → ATENSI
        }
        $this->article('Demo ricuh di Medan', 'negatif');             // 1 negatif → WASPADA
        $this->article('Polisi bantu korban banjir di Surabaya', 'positif'); // KONDUSIF

        $map = app(SituationMapService::class)->national([$this->divhumas->id]);
        $this->assertSame('ATENSI', $this->region($map, 'SULSEL')['status']);
        $this->assertSame('WASPADA', $this->region($map, 'SUMUT')['status']);
        $this->assertSame('KONDUSIF', $this->region($map, 'JATIM')['status']);
        $this->assertSame('BELUM ADA DATA', $this->region($map, 'ACEH')['status']);
        $sulsel = $this->region($map, 'SULSEL');
        $this->assertCount(4, $sulsel['articles']);
        $this->assertStringStartsWith('https://berita.test/', $sulsel['articles'][0]['url']);
        $this->assertSame('"Sulawesi Selatan"', $sulsel['search_q']);
        $this->assertStringContainsString('3 dari 4 berita bernada negatif', $sulsel['reason']);
        $this->assertArrayHasKey('ATENSI', $map['definitions']);
    }

    public function test_command_center_highlights_active_issues_and_real_metric_posts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin_mabes', 'polda_id' => null]);
        Issue::create(['polda_id' => $this->divhumas->id, 'title' => 'Isu selesai', 'summary' => 's', 'category' => 'Umum', 'priority' => 'critical', 'status' => 'selesai']);
        Issue::create(['polda_id' => $this->divhumas->id, 'title' => 'Isu sedang', 'summary' => 's', 'category' => 'Umum', 'priority' => 'medium', 'status' => 'investigasi']);
        Issue::create(['polda_id' => $this->divhumas->id, 'title' => 'Isu kritis', 'summary' => 's', 'category' => 'Umum', 'priority' => 'critical', 'status' => 'monitoring']);
        MonitoredSocialAccount::create(['polda_id' => $this->divhumas->id, 'platform' => 'x', 'username' => 'DivHumas_Polri', 'display_name' => 'Humas Polri', 'posts' => [
            ['url' => 'https://x.com/DivHumas_Polri/status/1', 'caption' => 'Ramai', 'posted_at' => now()->subDay()->toIso8601String(), 'likes' => 300, 'comments' => 50, 'views' => 9000, 'shares' => 10],
            ['url' => 'https://x.com/DivHumas_Polri/status/2', 'caption' => 'Sepi', 'posted_at' => now()->subDay()->toIso8601String(), 'likes' => 3, 'comments' => 0, 'views' => 90, 'shares' => 0],
            ['url' => 'https://x.com/DivHumas_Polri/status/3', 'caption' => 'Lama', 'posted_at' => now()->subDays(20)->toIso8601String(), 'likes' => 9999, 'comments' => 0, 'views' => 1, 'shares' => 0],
        ]]);

        $this->actingAs($admin)->withSession(['active_polda_code' => 'ALL'])->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('topIssues', fn ($rows) => collect($rows)->pluck('title')->all() === ['Isu kritis', 'Isu sedang'])
            ->where('topViralSocialPosts', fn ($rows) => collect($rows)->pluck('url')->all() === ['https://x.com/DivHumas_Polri/status/1', 'https://x.com/DivHumas_Polri/status/2'])
            ->where('situationMap.mode', 'national'));
    }
}
