<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class BasementDemoChoiceTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_publish_without_demolition_choice_is_rejected(): void
    {
        $this->actingAsAdmin();

        $payload = $this->basePayload([
            'client_name' => 'Demo Unselected Client',
        ]);
        unset($payload['include_demolition']);

        $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), $payload)
            ->assertRedirect(route('admin.quotations.basement.create'))
            ->assertSessionHasErrors('calculation');

        $this->assertNull(BasementQuotation::query()->where('client_name', 'Demo Unselected Client')->first());
    }

    public function test_draft_without_demolition_choice_is_allowed(): void
    {
        $this->actingAsAdmin();

        $payload = $this->basePayload([
            'save_intent' => 'draft',
            'client_name' => 'Demo Draft Client',
        ]);
        unset($payload['include_demolition']);

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Demo Draft Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertArrayNotHasKey('include_demolition', $quote->inputs ?? []);
    }

    public function test_no_zeroes_charges_and_generated_scope_but_keeps_type_inputs(): void
    {
        $this->actingAsAdmin();

        $payload = $this->basePayload([
            'client_name' => 'Demo No Client',
            'include_demolition' => 0,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_bin_qty' => 1,
        ]);

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Demo No Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) ($quote->inputs['include_demolition'] ?? true));
        $this->assertTrue((bool) ($quote->inputs['demo_include_flooring'] ?? false));
        $this->assertEqualsWithDelta(400.0, (float) ($quote->inputs['demo_flooring_sf'] ?? 0), 0.01);
        $this->assertSame(1, (int) ($quote->inputs['demo_bin_qty'] ?? 0));

        $keys = collect($quote->calculation['labor'] ?? [])->pluck('key')->all();
        $this->assertNotContains('demo_floor', $keys);
        $this->assertNotContains('demo_bins', $keys);

        $copy = BasementClientScope::generatedCopy($quote->inputs ?? []);
        $this->assertStringContainsString(
            BasementQuotationCalculator::SECTIONS['demolition'],
            $copy['not_included']
        );
        $this->assertStringNotContainsString('Remove existing flooring', $quote->scopeDescriptionForPdf());
        $this->assertStringNotContainsString('Demolition', $quote->scopeDescriptionForPdf());

        $before = (float) $quote->final_quote_total;
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $quote->refresh();
        $this->assertEqualsWithDelta($before, (float) $quote->final_quote_total, 0.01);
        $this->assertTrue((bool) ($quote->inputs['demo_include_flooring'] ?? false));
        $this->assertEqualsWithDelta(400.0, (float) ($quote->inputs['demo_flooring_sf'] ?? 0), 0.01);
    }

    public function test_yes_requires_positive_quantity_and_charges(): void
    {
        $this->actingAsAdmin();

        $emptyYes = $this->basePayload([
            'client_name' => 'Demo Empty Yes',
            'include_demolition' => 1,
            'demo_include_flooring' => 0,
            'demo_include_studs' => 0,
            'demo_include_drywall' => 0,
            'demo_include_baseboard' => 0,
            'demo_bin_qty' => 0,
        ]);
        $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), $emptyYes)
            ->assertRedirect(route('admin.quotations.basement.create'))
            ->assertSessionHasErrors('calculation');

        $payload = $this->basePayload([
            'client_name' => 'Demo Yes Client',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 0,
            'demo_bin_qty' => 1,
        ]);
        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Demo Yes Client')->first();
        $this->assertNotNull($quote);
        $keys = collect($quote->calculation['labor'] ?? [])->pluck('key')->all();
        $this->assertContains('demo_floor', $keys);
        $this->assertContains('demo_bins', $keys);
        $this->assertStringContainsString('Demolition', $quote->scopeDescriptionForPdf());
        $this->assertStringContainsString('Remove existing flooring', $quote->scopeDescriptionForPdf());
    }

    public function test_old_quote_keeps_saved_yes_intent_and_total_on_plain_resave(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 400,
            'include_demolition' => true,
            'demo_include_flooring' => true,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => false,
            'demo_bin_qty' => 1,
            'owner_hourly_rate' => 80,
            'insulate_walls' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $total = (float) $calc['summary']['final_quote_total'];
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Demo Legacy Yes',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $this->put(route('admin.quotations.basement.update', $quote), $this->basePayload([
            'client_name' => 'Demo Legacy Yes',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 0,
            'demo_bin_qty' => 1,
        ]))->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertTrue((bool) ($quote->inputs['include_demolition'] ?? false));
        $this->assertEqualsWithDelta($total, (float) $quote->final_quote_total, 0.01);
        $this->assertContains('demo_floor', collect($quote->calculation['labor'] ?? [])->pluck('key')->all());
    }

    public function test_publish_yes_rejects_checked_zero_drywall_while_flooring_is_positive(): void
    {
        $this->actingAsAdmin();

        $payload = $this->basePayload([
            'client_name' => 'Demo Zero Drywall',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 1,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
        ]);

        $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), $payload)
            ->assertRedirect(route('admin.quotations.basement.create'))
            ->assertSessionHasErrors('demo_drywall_sf');

        $this->assertNull(BasementQuotation::query()->where('client_name', 'Demo Zero Drywall')->first());
    }

    public function test_draft_allows_yes_with_checked_zero_drywall(): void
    {
        $this->actingAsAdmin();

        $payload = $this->basePayload([
            'save_intent' => 'draft',
            'client_name' => 'Demo Draft Zero Drywall',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 1,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
        ]);

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Demo Draft Zero Drywall')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertTrue((bool) ($quote->inputs['demo_include_drywall'] ?? false));
        $this->assertEqualsWithDelta(0.0, (float) ($quote->inputs['demo_drywall_sf'] ?? 1), 0.01);
    }

    public function test_published_historical_zero_drywall_line_survives_plain_resave(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 400,
            'include_demolition' => true,
            'demo_include_flooring' => true,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => true,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
            'owner_hourly_rate' => 80,
            'insulate_walls' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $total = (float) $calc['summary']['final_quote_total'];
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Demo Grandfather Drywall',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $total,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        $this->put(route('admin.quotations.basement.update', $quote), $this->basePayload([
            'client_name' => 'Demo Grandfather Drywall',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 1,
            'demo_drywall_sf' => 0,
            'demo_bin_qty' => 1,
        ]))->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertEqualsWithDelta($total, (float) $quote->final_quote_total, 0.01);
        $this->assertTrue((bool) ($quote->inputs['demo_include_drywall'] ?? false));
    }

    public function test_yes_and_no_stored_totals_match_calculator_and_project_pdf(): void
    {
        $this->actingAsAdmin();

        $noPayload = $this->basePayload([
            'client_name' => 'Demo Totals No',
            'include_demolition' => 0,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 0,
            'demo_bin_qty' => 1,
        ]);
        $yesPayload = $this->basePayload([
            'client_name' => 'Demo Totals Yes',
            'include_demolition' => 1,
            'demo_include_flooring' => 1,
            'demo_flooring_sf' => 400,
            'demo_include_drywall' => 0,
            'demo_bin_qty' => 1,
        ]);

        $this->post(route('admin.quotations.basement.store'), $noPayload)->assertSessionHasNoErrors();
        $this->post(route('admin.quotations.basement.store'), $yesPayload)->assertSessionHasNoErrors();

        $no = BasementQuotation::query()->where('client_name', 'Demo Totals No')->first();
        $yes = BasementQuotation::query()->where('client_name', 'Demo Totals Yes')->first();
        $this->assertNotNull($no);
        $this->assertNotNull($yes);

        $calc = new BasementQuotationCalculator;
        $noExpected = (float) $calc->calculate($no->inputs ?? [])['summary']['final_quote_total'];
        $yesExpected = (float) $calc->calculate($yes->inputs ?? [])['summary']['final_quote_total'];
        $this->assertEqualsWithDelta($noExpected, (float) $no->final_quote_total, 0.01);
        $this->assertEqualsWithDelta($yesExpected, (float) $yes->final_quote_total, 0.01);
        $this->assertGreaterThan($noExpected, $yesExpected);

        $project = ClientProject::factory()->create();
        foreach ([$no, $yes] as $quote) {
            $this->post(route('admin.quotations.basement.link', $quote), [
                'client_project_id' => $project->id,
            ])->assertSessionHasNoErrors();
            $cpq = ClientProjectQuotation::query()
                ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
                ->where('source_id', $quote->id)
                ->first();
            $this->assertNotNull($cpq);
            $this->assertEqualsWithDelta((float) $quote->final_quote_total, $cpq->pdfGrandTotal(), 0.02);
            $money = ClientProject::formatMoney((float) $quote->final_quote_total);
            $pdfResponse = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
            $pdfResponse->assertOk();
            $bytes = $pdfResponse->getContent();
            $this->assertStringStartsWith('%PDF', $bytes);
            $path = storage_path('app/demo-choice-'.$quote->id.'.pdf');
            file_put_contents($path, $bytes);
            $text = $this->extractPdfText($path);
            $this->assertStringContainsString($money, $text);
            if ((bool) ($quote->inputs['include_demolition'] ?? false)) {
                $this->assertStringContainsString('Remove existing flooring', $text);
            } else {
                $this->assertStringNotContainsString('Remove existing flooring', $text);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'client_name' => 'Demo Client',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'room_count' => 1,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
            'insulate_walls' => 0,
            'flooring_type' => 'none',
            'include_cleaning' => 0,
            'include_touchups' => 0,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
        ], $overrides);
    }
}
