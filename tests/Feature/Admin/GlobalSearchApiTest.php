<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class GlobalSearchApiTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_search_api_returns_matching_projects(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen Renovation',
            'client_name' => 'John Smith',
        ]);

        $this->get(route('admin.search.api', ['q' => 'Kitchen']))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'projects')
            ->assertJsonPath('results.0.items.0.title', 'Kitchen Renovation');
    }

    public function test_search_api_returns_empty_for_short_queries(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.search.api', ['q' => 'K']))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_search_api_searches_client_name(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Deck Build',
            'client_name' => 'Johnson Family',
        ]);

        $this->get(route('admin.search.api', ['q' => 'Johnson']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Deck Build']);
    }
}
