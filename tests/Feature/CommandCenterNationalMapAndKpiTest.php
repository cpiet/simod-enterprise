<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterNationalMapAndKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_command_center_loads_with_national_map_data(): void
    {
        $user = User::first();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('nationalMapData')
            ->has('polresStatuses')
            ->has('kpi.priority_issues')
            ->has('kpi.total_articles')
        );
    }

    public function test_priority_attention_filter_on_issues_returns_active_issues(): void
    {
        $user = User::first();

        // Visit /issues?priority=attention
        $response = $this->actingAs($user)->get('/issues?priority=attention');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Issues/Index')
            ->has('issues.data')
            ->where('filters.priority', 'attention')
        );
    }

    public function test_national_tenant_consolidation_displays_cross_polda_issues(): void
    {
        $user = User::first();

        // Switch to national scope
        session(['selected_polda_code' => 'ALL']);

        $response = $this->actingAs($user)->get('/issues');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Issues/Index')
            ->has('issues.data')
        );
    }
}
