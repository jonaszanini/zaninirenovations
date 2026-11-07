<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementDisabledTradeScopeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_edit_reopen_and_project_pdf_omit_disabled_trade_details(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload([
            'client_name' => 'Disabled Trade Scope Client',
            'work_description' => '<p>CLIENT ASKED FOR RECESSED NICHE</p>',
            'include_bathroom' => 1,
            'bath_type' => 'full',
            'electrical_lines' => [[
                'description' => 'STALE PANEL UPGRADE',
                'amount' => '1800',
                'hst_included' => '1',
            ]],
            'plumbing_lines' => [[
                'description' => 'STALE ROUGH-IN',
                'amount' => '900',
                'hst_included' => '1',
            ]],
        ]);

        $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Disabled Trade Scope Client')->first();
        $this->assertNotNull($quote);
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertFalse(BasementQuotationCalculator::sectionEnabled($inputs, 'electrical'));
        $this->assertFalse(BasementQuotationCalculator::sectionEnabled($inputs, 'plumbing'));
        $this->assertFalse(BasementQuotationCalculator::sectionEnabled($inputs, 'bathroom'));
        $this->assertSame('STALE PANEL UPGRADE', $inputs['electrical_lines'][0]['description'] ?? null);
        $this->assertSame('STALE ROUGH-IN', $inputs['plumbing_lines'][0]['description'] ?? null);

        $withoutTrades = (new BasementQuotationCalculator)->calculate(array_merge($inputs, [
            'electrical_lines' => [],
            'plumbing_lines' => [],
        ]));
        $this->assertEqualsWithDelta(
            (float) ($withoutTrades['summary']['grand_total'] ?? 0),
            (float) $quote->final_quote_total,
            0.02
        );

        $pdf = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('Not included:', $pdf);
        $this->assertStringContainsString('Electrical', $pdf);
        $this->assertStringContainsString('Plumbing', $pdf);
        $this->assertStringContainsString('Bathroom', $pdf);
        $this->assertStringNotContainsString('Bathroom: Full bath', $pdf);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $pdf);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $pdf);
        $this->assertStringContainsString('CLIENT ASKED FOR RECESSED NICHE', $pdf);

        $copy = $quote->clientScopeCopy();
        $storedAllowances = (string) (($quote->calculation['client_scope']['allowances'] ?? ''));
        $this->assertStringContainsString('Electrical', $copy['not_included']);
        $this->assertStringContainsString('Plumbing', $copy['not_included']);
        $this->assertStringContainsString('Bathroom', $copy['not_included']);
        $this->assertStringNotContainsString('Electrical work allowance', $copy['allowances']);
        $this->assertStringNotContainsString('Plumbing work allowance', $copy['allowances']);
        $this->assertStringNotContainsString('Electrical work allowance', $storedAllowances);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $copy['included'].$copy['not_included'].$copy['allowances'].$storedAllowances);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $copy['included'].$copy['not_included'].$copy['allowances'].$storedAllowances);
        $this->assertStringNotContainsString('Bathroom: Full bath', $copy['included']);

        $edit = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('STALE PANEL UPGRADE', $edit);
        $this->assertStringContainsString('STALE ROUGH-IN', $edit);
        $this->assertStringContainsString('CLIENT ASKED FOR RECESSED NICHE', $edit);

        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $reopenPdf = $quote->scopeDescriptionForPdf();
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $reopenPdf);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $reopenPdf);
        $this->assertStringContainsString('CLIENT ASKED FOR RECESSED NICHE', $reopenPdf);
        $this->assertEqualsWithDelta(
            (float) ($withoutTrades['summary']['grand_total'] ?? 0),
            (float) $quote->final_quote_total,
            0.02
        );

        $project = ClientProject::factory()->create();
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);
        $descriptions = collect($cpq->pdfLineItems())->pluck('description')->implode("\n");
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $descriptions);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $descriptions);
        $this->assertStringNotContainsString('Electrical work', $descriptions);
        $this->assertStringNotContainsString('Plumbing work', $descriptions);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', (string) $cpq->work_description);
        $this->assertStringNotContainsString('STALE ROUGH-IN', (string) $cpq->work_description);
        $this->assertStringContainsString('CLIENT ASKED FOR RECESSED NICHE', (string) $cpq->work_description);
        $this->assertStringContainsString('Not included:', (string) $cpq->work_description);
        $this->assertEqualsWithDelta((float) $quote->final_quote_total, $cpq->pdfGrandTotal(), 0.02);
    }

    public function test_enabled_electrical_and_plumbing_lines_appear_on_pdf_and_project(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload([
            'client_name' => 'Enabled Trade Scope Client',
            'enabled_sections' => ['overview', 'electrical', 'plumbing', 'finishing'],
            'include_bathroom' => 0,
            'electrical_lines' => [[
                'description' => 'Panel upgrade',
                'amount' => '1130',
                'hst_included' => '1',
            ]],
            'plumbing_lines' => [[
                'description' => 'Rough-in stack',
                'amount' => '900',
                'hst_included' => '1',
            ]],
        ]);

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Enabled Trade Scope Client')->first();
        $this->assertNotNull($quote);
        $pdf = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('Panel upgrade', $pdf);
        $this->assertStringContainsString('Rough-in stack', $pdf);
        preg_match('/Not included: ([^<]+)/', $pdf, $notIncluded);
        $this->assertStringNotContainsString('Electrical', $notIncluded[1] ?? '');
        $this->assertStringNotContainsString('Plumbing', $notIncluded[1] ?? '');

        $project = ClientProject::factory()->create();
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);
        $descriptions = collect($cpq->pdfLineItems())->pluck('description')->implode("\n");
        $this->assertStringContainsString('Panel upgrade', $descriptions);
        $this->assertStringContainsString('Rough-in stack', $descriptions);
        $this->assertEqualsWithDelta((float) $quote->final_quote_total, $cpq->pdfGrandTotal(), 0.02);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'room_count' => 2,
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'demolition', 'framing', 'drywall', 'doors_trim', 'finishing'],
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }
}
