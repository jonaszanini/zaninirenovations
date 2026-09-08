<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectTabCountsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_show_page_hides_tab_counts_when_empty(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/data-tab="quotation"[^>]*>\s*Quotation\s*<span class="cp-tab-count">/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-tab="contract"[^>]*>\s*Contract\s*<span class="cp-tab-count">/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-tab="invoice"[^>]*>\s*Invoice\s*<span class="cp-tab-count">/s',
            $html
        );
    }

    public function test_show_page_badges_quotation_contract_and_invoice_counts(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Quote A',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-8001',
            'items' => [['description' => 'Labour', 'amount' => 100, 'apply_hst' => true]],
        ]);
        ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Quote B',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => false,
            'quotation_number' => 'QUO-2026-8002',
            'items' => [['description' => 'Labour', 'amount' => 50, 'apply_hst' => true]],
        ]);

        $project->contract()->create([
            'html_content' => '<p>Interior renovation scope.</p>',
            'version' => 1,
        ]);

        ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
        ]);
        ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
        ]);
        ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-tab="quotation"[^>]*>\s*Quotation\s*<span class="cp-tab-count">2<\/span>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-tab="contract"[^>]*>\s*Contract\s*<span class="cp-tab-count">1<\/span>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-tab="invoice"[^>]*>\s*Invoice\s*<span class="cp-tab-count">3<\/span>/s',
            $html
        );
    }

    public function test_empty_contract_body_does_not_show_a_badge(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();
        $project->contract()->create([
            'html_content' => '',
            'version' => 1,
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/data-tab="contract"[^>]*>\s*Contract\s*<span class="cp-tab-count">/s',
            $html
        );
    }
}
