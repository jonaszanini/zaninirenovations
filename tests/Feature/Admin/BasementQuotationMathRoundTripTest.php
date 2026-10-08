<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementQuotationMathRoundTripTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_preview_subtracts_openings_and_uses_sheet_size(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'drywall_wall_sf' => 400,
            'drywall_ceiling_sf' => 0,
            'drywall_door_openings' => 1,
            'drywall_window_openings' => 1,
            'drywall_sheet_size' => '4x12',
            'material_waste_percent' => 10,
            'include_demolition' => 0,
            'insulate_walls' => 0,
            'flooring_type' => 'none',
            'include_cleaning' => 0,
            'include_touchups' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ];

        $this->post(route('admin.quotations.basement.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('scope.drywall_opening_sf', 33)
            ->assertJsonPath('scope.drywall_wall_net_sf', 367)
            ->assertJsonPath('scope.drywall_sheet_size', '4x12')
            ->assertJsonPath('scope.drywall_sheet_sf', 48);

        $calc = (new BasementQuotationCalculator)->calculate($payload);
        $sheets = collect($calc['materials'])->firstWhere('key', 'drywall_sheet_4x12');
        $this->assertNotNull($sheets);
        $this->assertSame(9.0, (float) $sheets['qty']);
    }

    public function test_save_and_edit_round_trip_keeps_new_takeoff_fields(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Opening Sheet Client',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'room_count' => 2,
            'partition_lf' => 24,
            'stud_spacing_in' => 16,
            'drywall_wall_sf' => 400,
            'drywall_ceiling_sf' => 0,
            'drywall_door_openings' => 2,
            'drywall_window_openings' => 1,
            'drywall_sheet_size' => '4x12',
            'material_waste_percent' => 10,
            'include_building_permit' => 1,
            'building_permit_amount' => '175.00',
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $store = $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Opening Sheet Client')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertSame('4x12', $inputs['drywall_sheet_size']);
        $this->assertSame(16, (int) $inputs['stud_spacing_in']);
        $this->assertSame(2, (int) $inputs['drywall_door_openings']);
        $this->assertSame(1, (int) $inputs['drywall_window_openings']);
        $this->assertTrue((bool) $inputs['include_building_permit']);
        $this->assertEqualsWithDelta(175.0, (float) $inputs['building_permit_amount'], 0.01);

        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $this->assertEqualsWithDelta(54.0, (float) ($calc['scope']['drywall_opening_sf'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(346.0, (float) ($calc['scope']['drywall_wall_net_sf'] ?? 0), 0.01);
        $this->assertSame('4x12', $calc['scope']['drywall_sheet_size'] ?? null);
        $this->assertNotNull(collect($calc['materials'] ?? [])->firstWhere('key', 'drywall_sheet_4x12'));
        $this->assertNotNull(collect($calc['materials'] ?? [])->firstWhere('key', 'building_permit'));

        $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('name="drywall_sheet_size"', false)
            ->assertSee('value="4x12"', false)
            ->assertSee('name="stud_spacing_in"', false)
            ->assertSee('name="drywall_door_openings"', false)
            ->assertSee('name="building_permit_amount"', false);

        $updated = array_merge($payload, [
            'drywall_sheet_size' => '4x8',
            'stud_spacing_in' => 24,
            'drywall_opening_sf' => 20,
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $updated)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $this->assertSame('4x8', $inputs['drywall_sheet_size']);
        $this->assertSame(24, (int) $inputs['stud_spacing_in']);
        $this->assertEqualsWithDelta(20.0, (float) $inputs['drywall_opening_sf'], 0.01);
        $this->assertEqualsWithDelta(20.0, (float) ($calc['scope']['drywall_opening_sf'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(380.0, (float) ($calc['scope']['drywall_wall_net_sf'] ?? 0), 0.01);
        $this->assertNotNull(collect($calc['materials'] ?? [])->firstWhere('key', 'drywall_sheet'));
        $this->assertNull(collect($calc['materials'] ?? [])->firstWhere('key', 'drywall_sheet_4x12'));
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
    }

    public function test_legacy_quote_without_new_keys_still_opens(): void
    {
        $this->actingAsAdmin();

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'owner_hourly_rate' => 80,
        ]);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Basement',
            'is_draft' => false,
            'inputs' => [
                'area_sqft' => 400,
                'ceiling_height_ft' => 8,
                'owner_hourly_rate' => 80,
            ],
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'version' => 1,
        ]);

        $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('Legacy Basement')
            ->assertSee('4×8 (32 sq ft)', false)
            ->assertSee('16" o.c.', false);
    }

    public function test_pdf_lines_plus_hst_match_wizard_grand_total_with_permit_split(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Permit Split Client',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'room_count' => 2,
            'partition_lf' => 24,
            'stud_spacing_in' => 16,
            'drywall_wall_sf' => 400,
            'drywall_ceiling_sf' => 0,
            'drywall_door_openings' => 2,
            'drywall_window_openings' => 1,
            'drywall_sheet_size' => '4x12',
            'material_waste_percent' => 10,
            'include_building_permit' => 1,
            'building_permit_amount' => '175.00',
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Permit Split Client')->first();
        $this->assertNotNull($quote);

        $project = \App\Models\ClientProject::factory()->create();
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $quote->refresh();

        $labour = $quote->labourPreTaxAmountForProjectQuotation();
        $materials = $quote->materialsPreTaxAmountForProjectQuotation();
        $permit = $quote->permitPreTaxAmountForProjectQuotation();
        $this->assertGreaterThan(0, $labour);
        $this->assertGreaterThan(0, $materials);
        $this->assertEqualsWithDelta(175.0, $permit, 0.01);

        $summary = is_array($quote->calculation['summary'] ?? null) ? $quote->calculation['summary'] : [];
        $this->assertEqualsWithDelta((float) ($summary['permit_pre_hst'] ?? 0), $permit, 0.01);
        $this->assertEqualsWithDelta((float) ($summary['materials_pre_hst'] ?? 0), $materials, 0.01);

        $lineSubtotal = round($labour + $materials + $permit, 2);
        $hst = round(
            round($labour * BasementQuotation::HST_RATE, 2)
            + round($materials * BasementQuotation::HST_RATE, 2)
            + round($permit * BasementQuotation::HST_RATE, 2),
            2
        );
        $this->assertEqualsWithDelta((float) $quote->final_quote_total, $lineSubtotal + $hst, 0.01);

        $cpq = \App\Models\ClientProjectQuotation::query()
            ->where('source_type', \App\Models\ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);

        $descriptions = collect($cpq->pdfLineItems())->pluck('description')->all();
        $this->assertContains('Basement renovation (labour)', $descriptions);
        $this->assertContains('Basement renovation materials', $descriptions);
        $this->assertContains('Building permit (optional, extra charge)', $descriptions);
        $this->assertNotContains('Basement renovation (labor)', $descriptions);

        $permitLine = collect($cpq->pdfLineItems())->firstWhere('description', 'Building permit (optional, extra charge)');
        $this->assertEqualsWithDelta(175.0, (float) ($permitLine['amount'] ?? 0), 0.01);

        $materialsLine = collect($cpq->pdfLineItems())->firstWhere('description', 'Basement renovation materials');
        $this->assertEqualsWithDelta($materials, (float) ($materialsLine['amount'] ?? 0), 0.01);

        $this->assertEqualsWithDelta((float) $quote->final_quote_total, $cpq->pdfGrandTotal(), 0.01);
        $this->assertEqualsWithDelta(
            $cpq->pdfSubtotal() + $cpq->pdfHst(),
            (float) $quote->final_quote_total,
            0.01
        );

        $scope = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('<table', $scope);
        $this->assertStringContainsString('Building permit (optional, extra charge)', $scope);
        $this->assertDoesNotMatchRegularExpression('/licen[cs]e|licensed|WSIB|one-person|\\$3M/i', $scope.$this->materialsLabels($quote));
        $this->assertStringNotContainsString('vapor', strtolower($scope.$this->materialsLabels($quote)));
    }

    private function materialsLabels(BasementQuotation $quote): string
    {
        $calc = is_array($quote->calculation) ? $quote->calculation : [];

        return collect($calc['labor'] ?? [])->pluck('label')->implode(' ')
            .' '.collect($calc['materials'] ?? [])->pluck('label')->implode(' ');
    }
}
