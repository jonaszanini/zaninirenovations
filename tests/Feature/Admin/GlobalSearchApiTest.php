<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
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

    public function test_search_api_returns_directory_client_by_name(): void
    {
        $this->actingAsAdmin();

        $client = Client::query()->create([
            'name' => 'Luccas',
            'email' => 'luccas@example.test',
            'phone' => '6475550100',
        ]);

        $this->get(route('admin.search.api', ['q' => 'Luccas']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'clients',
                'title' => 'Luccas',
            ])
            ->assertJsonFragment([
                'url' => route('admin.clients.edit', $client),
            ]);
    }

    public function test_search_api_finds_project_number_quote_number_and_invoice_number(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Basement finish',
            'client_name' => 'Ada Client',
        ]);

        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'invoice_number' => 'INV-2026-4411',
        ]);

        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Basement quotation',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-8822',
            'source_type' => ClientProjectQuotation::SOURCE_MANUAL,
            'items' => [],
        ]);

        $this->get(route('admin.search.api', ['q' => $project->referenceCode()]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Basement finish']);

        $this->get(route('admin.search.api', ['q' => 'QUO-2026-8822']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'QUO-2026-8822']);

        $this->get(route('admin.search.api', ['q' => 'INV-2026-4411']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'INV-2026-4411']);

        $this->assertNotNull($quotation->id);
        $this->assertNotNull($invoice->id);
    }
}
