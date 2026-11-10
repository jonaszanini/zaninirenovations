<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class BasementPaintManualEditTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_manual_paint_edits_survive_save_reopen_and_pdf(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'Manual Paint Edit Client',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'flooring_sf' => 400,
            'ceiling_type' => 'drywall',
            'paint_wall_sf' => 601,
            'paint_ceiling_sf' => 202,
            'paint_trim_lf' => 55,
            'drywall_wall_sf' => 640,
            'drywall_ceiling_sf' => 400,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ]);
        $enabled = array_merge(['overview'], array_keys(BasementQuotationCalculator::tradeSections()));
        $this->post(route('admin.quotations.basement.store'), array_merge($inputs, [
            'save_intent' => 'draft',
            'enabled_sections' => $enabled,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ]))->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Manual Paint Edit Client')->first();
        $this->assertNotNull($quote);

        $update = array_merge($inputs, [
            'save_intent' => 'publish',
            'enabled_sections' => $enabled,
            'paint_wall_sf' => 611,
            'paint_ceiling_sf' => 212,
            'paint_trim_lf' => 59,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $update)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $quote->refresh();

        $this->assertEqualsWithDelta(611.0, (float) ($quote->inputs['paint_wall_sf'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(212.0, (float) ($quote->inputs['paint_ceiling_sf'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(59.0, (float) ($quote->inputs['paint_trim_lf'] ?? 0), 0.01);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/\bid="br-p-wall"[^>]*value="611(\.0+)?"/', $editHtml);
        $this->assertMatchesRegularExpression('/\bid="br-p-ceil"[^>]*value="212(\.0+)?"/', $editHtml);
        $this->assertMatchesRegularExpression('/\bid="br-p-trim"[^>]*value="59(\.0+)?"/', $editHtml);

        $clientProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $clientProject->id,
        ])->assertSessionHasNoErrors();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);

        $recalc = (new BasementQuotationCalculator)->calculate($quote->inputs);
        $this->assertGreaterThan(0, collect($recalc['labor'] ?? [])->firstWhere('key', 'paint_walls')['amount'] ?? 0);
        $this->assertEqualsWithDelta(611.0, (float) ($quote->inputs['paint_wall_sf'] ?? 0), 0.01);

        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$clientProject, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/manual-paint-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString('Paint', $pdfText);
        $this->assertStringContainsString(ClientProject::formatMoney((float) $quote->final_quote_total), $pdfText);
    }
}
