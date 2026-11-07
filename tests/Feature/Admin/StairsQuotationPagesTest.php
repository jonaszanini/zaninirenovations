<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StairsMaterialType;
use App\Models\StairsQuotation;
use App\Services\StairsQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class StairsQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_hub_create_and_config_pages_load_with_info_tips(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Stairs');

        $html = $this->get(route('admin.quotations.stairs.create'))
            ->assertOk()
            ->assertSee('Step 1 — Basic information')
            ->assertSee('Live summary')
            ->assertSee('aria-label="About Stair configuration"', false)
            ->assertSee('aria-label="About Materials price (pre-tax)"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertDontSee('<p class="pq-hint">Required for a published quotation', false)
            ->getContent();

        $this->assertStringContainsString('data-qh-footer', $html);
        $this->assertStringContainsString('fa-info-circle', $html);

        $this->get(route('admin.quotations.stairs.price-configuration'))
            ->assertOk()
            ->assertSee('Stairs price configuration');
    }

    public function test_publish_edit_reopen_and_pdf_keep_the_same_total(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $materialId = StairsMaterialType::query()->orderBy('sort_order')->value('id');
        $this->assertNotNull($materialId);

        $payload = $this->publishPayload([
            'client_name' => $project->client_name,
            'material_type_id' => $materialId,
            'link_client_project_id' => $project->id,
        ]);

        $this->post(route('admin.quotations.stairs.store'), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote = StairsQuotation::query()->where('client_name', $project->client_name)->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $publishedTotal = (float) $quote->final_quote_total;
        $this->assertGreaterThan(0, $publishedTotal);
        $this->assertSame($project->id, (int) $quote->client_project_id);

        $edit = $this->get(route('admin.quotations.stairs.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($project->client_name, $edit);
        $this->assertStringContainsString('data-qh-footer', $edit);
        $this->assertStringContainsString('aria-label="About Stair configuration"', $edit);

        $this->put(route('admin.quotations.stairs.update', $quote), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.quotations.stairs.edit', $quote));

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertEqualsWithDelta($publishedTotal, (float) $quote->final_quote_total, 0.02);

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STAIRS)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);
        $this->assertEqualsWithDelta($publishedTotal, $cpq->pdfGrandTotal(), 0.02);

        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/stairs-workflow-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString(ClientProject::formatMoney($publishedTotal), $pdfText);
        $this->assertStringContainsString($project->client_name, $pdfText);
        $this->assertStringNotContainsString('Bulk discount', $pdfText);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'total_steps' => 12,
            'segments' => [[
                'stair_type' => 'standard',
                'qty' => 12,
                'open_sides' => StairsQuotationCalculator::OPEN_NONE,
            ]],
            'material_price' => '250.00',
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }
}
