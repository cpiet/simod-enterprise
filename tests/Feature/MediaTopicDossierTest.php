<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\User;
use Database\Seeders\EnterpriseDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaTopicDossierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EnterpriseDatabaseSeeder::class);
    }

    public function test_media_intelligence_loads_with_topics_prop(): void
    {
        $res = $this->get('/media-intelligence?polda=ALL');
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('MediaIntelligence/Index')
            ->has('topics')
        );
    }

    public function test_can_create_new_media_topic(): void
    {
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();
        $user = User::first();

        $res = $this->actingAs($user)->post('/media-intelligence/topics', [
            'polda_code'  => 'DIVHUMAS',
            'title'       => 'Kasus Sindikat Judol Transnasional',
            'keywords'    => 'judol, judi online, kamboja, sindikat, bareskrim',
            'description' => 'Atensi Pimpinan: Pantau rilis sitaan aset dan koordinasi interpol.',
            'priority'    => 'urgent',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('media_topics', [
            'polda_id' => $divhumas->id,
            'title'    => 'Kasus Sindikat Judol Transnasional',
            'priority' => 'urgent',
        ]);
    }

    public function test_can_update_media_topic(): void
    {
        $topic = MediaTopic::first();
        $user = User::first();

        $res = $this->actingAs($user)->post("/media-intelligence/topics/{$topic->id}/update", [
            'title'       => 'Judul Topik Diperbarui',
            'keywords'    => 'update, kata, kunci',
            'description' => 'Catatan revisi',
            'priority'    => 'high',
            'status'      => 'active',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('media_topics', [
            'id'    => $topic->id,
            'title' => 'Judul Topik Diperbarui',
        ]);
    }

    public function test_can_delete_media_topic(): void
    {
        $topic = MediaTopic::first();
        $user = User::first();

        $res = $this->actingAs($user)->delete("/media-intelligence/topics/{$topic->id}");

        $res->assertRedirect();
        $this->assertDatabaseMissing('media_topics', ['id' => $topic->id]);
    }

    public function test_can_toggle_article_topic_tagging(): void
    {
        $topic = MediaTopic::first();
        $article = MediaArticle::first() ?? MediaArticle::create([
            'polda_id'    => $topic->polda_id,
            'title'       => 'Berita Uji Tag Kasus',
            'source_name' => 'ANTARA',
            'category'    => 'HUKUM',
            'url'         => 'https://example.com/test-tag',
            'summary'     => 'Cuplikan berita uji tag kasus.',
            'pub_date'    => now(),
            'sentiment'   => 'netral',
        ]);
        $user = User::first();

        // 1. Tag
        $res = $this->actingAs($user)->postJson("/media-intelligence/articles/{$article->id}/toggle-topic", [
            'topic_id' => $topic->id,
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true, 'is_tagged' => true]);
        $this->assertDatabaseHas('media_article_topic', [
            'media_article_id' => $article->id,
            'media_topic_id'   => $topic->id,
        ]);

        // 2. Untag
        $res2 = $this->actingAs($user)->postJson("/media-intelligence/articles/{$article->id}/toggle-topic", [
            'topic_id' => $topic->id,
        ]);

        $res2->assertOk();
        $res2->assertJson(['success' => true, 'is_tagged' => false]);
        $this->assertDatabaseMissing('media_article_topic', [
            'media_article_id' => $article->id,
            'media_topic_id'   => $topic->id,
        ]);
    }

    public function test_analyze_topic_ai_with_gemini_mock_returns_structured_analysis(): void
    {
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();
        $user = User::first();

        $topic = MediaTopic::create([
            'polda_id'    => $divhumas->id,
            'title'       => 'Pemberantasan Narkoba Mandailing Natal',
            'keywords'    => 'narkoba, ganja, mandailing natal, bnn',
            'description' => 'Atensi Kadiv Humas',
            'priority'    => 'high',
            'status'      => 'active',
        ]);

        MediaArticle::create([
            'polda_id'    => $divhumas->id,
            'title'       => 'BNNP Musnahkan 5 Hektare Ladang Ganja di Mandailing Natal',
            'source_name' => 'ANTARA',
            'category'    => 'HUKUM',
            'url'         => 'https://example.com/ganja',
            'summary'     => 'Pemusnahan ladang ganja di Mandailing Natal seluas 5 hektare.',
            'pub_date'    => now(),
            'sentiment'   => 'positif',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'case_title'        => $topic->title,
                                        'executive_summary' => 'Pemusnahan ladang ganja berhasil dilaksanakan oleh tim gabungan.',
                                        'risk_assessment'   => 'Risiko residu peredaran gelap jika jalur logistik tidak ditutup.',
                                        'sentiment_analysis' => [
                                            'positif'            => 80,
                                            'netral'             => 15,
                                            'negatif'            => 5,
                                            'dominant_sentiment' => 'positif',
                                            'framing_media'      => 'Apresiasi atas ketegasan Polri-BNN.',
                                        ],
                                        'talking_points'    => ['Polri berkomitmen memberantas peredaran gelap narkotika.'],
                                        'action_recommendations' => [
                                            [
                                                'timeframe' => '0 - 2 Jam',
                                                'action'    => 'Siaran pers resmi',
                                                'pic'       => 'Divhumas',
                                            ],
                                        ],
                                        'whatsapp_script'   => 'Kepada Yth. Kadiv Humas, Laporan Pemusnahan Ladang Ganja Mandailing Natal selesai.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Mock API Key in environment
        config(['services.gemini.api_key' => 'AIzaSyTestKeyDossier_12345']);

        $res = $this->actingAs($user)->postJson("/media-intelligence/topics/{$topic->id}/analyze-ai");

        $res->assertOk();
        $res->assertJson(['success' => true]);
        $res->assertJsonStructure([
            'success',
            'topic' => ['id', 'title', 'priority'],
            'analysis' => [
                'case_title',
                'executive_summary',
                'risk_assessment',
                'sentiment_analysis',
                'talking_points',
                'action_recommendations',
                'whatsapp_script',
            ],
        ]);
    }
}
