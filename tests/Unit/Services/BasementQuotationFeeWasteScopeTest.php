<?php

namespace Tests\Unit\Services;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\ClientProjectQuotation;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementQuotationFeeWasteScopeTest extends TestCase
{
    public function test_legacy_quote_without_fee_key_does_not_add_ten_percent(): void
    {
        $base = $this->flooringQuote(['flooring_type' => 'lvp', 'material_waste_percent' => 10]);
        $legacy = (new BasementQuotationCalculator)->calculate($base, $this->settings());
        $withFee = (new BasementQuotationCalculator)->calculate(array_merge($base, [
            'contractor_fee_percent' => 10,
        ]), $this->settings());

        $this->assertSame(0.0, (float) $legacy['summary']['contractor_fee_pre_hst']);
        $this->assertGreaterThan(0, (float) $withFee['summary']['contractor_fee_pre_hst']);
        $this->assertEqualsWithDelta(
            (float) $legacy['summary']['grand_total'],
            (float) $legacy['summary']['labor_pre_hst']
                + (float) $legacy['summary']['materials_pre_hst']
                + (float) $legacy['summary']['permit_pre_hst']
                + (float) $legacy['summary']['hst']
                + (float) $legacy['summary']['trade_lines_tax_in'],
            0.02
        );
    }

    public function test_contractor_fee_excludes_jonas_labour_and_city_permit_includes_materials_subs_and_bcin(): void
    {
        $inputs = $this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
            'include_building_permit' => true,
            'building_permit_amount' => 200,
            'include_bcin_drawings' => true,
            'bcin_drawings_amount' => 400,
            'electrical_lines' => [[
                'description' => 'Panel and pots',
                'amount' => 1130,
                'hst_included' => true,
                'category' => 'materials',
            ]],
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $summary = $calc['summary'];

        $this->assertGreaterThan(0, (float) $summary['labor_pre_hst']);
        $this->assertGreaterThan(0, (float) $summary['materials_pre_hst']);
        $this->assertEqualsWithDelta(200.0, (float) $summary['permit_pre_hst'], 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $summary['bcin_pre_hst'], 0.01);
        $this->assertGreaterThan(0, (float) $summary['trade_lines_pre_hst']);

        $expectedBase = round(
            (float) $summary['materials_pre_hst']
            + (float) $summary['trade_lines_pre_hst']
            + (float) $summary['bcin_pre_hst'],
            2
        );
        $this->assertEqualsWithDelta($expectedBase, (float) $summary['contractor_fee_base_pre_hst'], 0.01);
        $this->assertEqualsWithDelta(round($expectedBase * 0.10, 2), (float) $summary['contractor_fee_pre_hst'], 0.01);
        $this->assertEqualsWithDelta(
            round((float) $summary['contractor_fee_pre_hst'] * BasementQuotation::HST_RATE, 2),
            (float) $summary['contractor_fee_hst'],
            0.01
        );
        $this->assertStringStartsWith('Contractor fee 10%', $summary['contractor_fee_label']);
        $this->assertStringContainsString('on CA$', $summary['contractor_fee_label']);

        $laborOnly = BasementQuotationCalculator::contractorFeeBreakdown(
            10,
            0,
            0,
            0,
            (float) $summary['labor_pre_hst']
        );
        $this->assertSame(0.0, $laborOnly['amount']);
    }

    public function test_contractor_fee_zero_and_changed_rate(): void
    {
        $base = $this->flooringQuote(['flooring_type' => 'lvp']);
        $zero = (new BasementQuotationCalculator)->calculate(array_merge($base, [
            'contractor_fee_percent' => 0,
        ]), $this->settings());
        $fifteen = (new BasementQuotationCalculator)->calculate(array_merge($base, [
            'contractor_fee_percent' => 15,
        ]), $this->settings());

        $this->assertSame(0.0, (float) $zero['summary']['contractor_fee_pre_hst']);
        $this->assertStringStartsWith('Contractor fee 15%', $fifteen['summary']['contractor_fee_label']);
        $this->assertEqualsWithDelta(
            round((float) $fifteen['summary']['contractor_fee_base_pre_hst'] * 0.15, 2),
            (float) $fifteen['summary']['contractor_fee_pre_hst'],
            0.01
        );
        $this->assertGreaterThan((float) $zero['summary']['grand_total'], (float) $fifteen['summary']['grand_total']);
    }

    public function test_change_order_fee_excludes_labour_and_permit(): void
    {
        $fee = BasementQuotationCalculator::contractorFeeForChangeOrder(
            10,
            500,
            200,
            1000,
            175,
            0
        );
        $this->assertEqualsWithDelta(700.0, $fee['base'], 0.01);
        $this->assertEqualsWithDelta(70.0, $fee['amount'], 0.01);
    }

    public function test_flooring_waste_defaults_by_type_and_legacy_keeps_global(): void
    {
        $calculator = new BasementQuotationCalculator;
        $types = [
            'lvp' => 7.0,
            'hardwood' => 8.0,
            'engineered' => 8.0,
            'laminate' => 8.0,
            'tile' => 10.0,
        ];
        foreach ($types as $type => $expected) {
            $this->assertSame($expected, BasementQuotationCalculator::flooringWasteDefault($type));
            $calc = $calculator->calculate($this->flooringQuote([
                'flooring_type' => $type,
                'flooring_waste_percent' => $expected,
                'material_waste_percent' => 10,
            ]), $this->settings());
            $this->assertEqualsWithDelta($expected, (float) $calc['summary']['flooring_waste_percent'], 0.01);
            $finish = collect($calc['materials'])->firstWhere('key', 'flooring_finish');
            $this->assertNotNull($finish);
            $this->assertEqualsWithDelta(round(200 * (1 + $expected / 100), 2), (float) $finish['qty'], 0.05);
        }

        $legacy = $calculator->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'material_waste_percent' => 10,
        ]), $this->settings());
        $this->assertEqualsWithDelta(10.0, (float) $legacy['summary']['flooring_waste_percent'], 0.01);
        $legacyFinish = collect($legacy['materials'])->firstWhere('key', 'flooring_finish');
        $this->assertEqualsWithDelta(220.0, (float) $legacyFinish['qty'], 0.05);

        $edited = $calculator->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'material_waste_percent' => 10,
            'flooring_waste_percent' => 3,
        ]), $this->settings());
        $this->assertEqualsWithDelta(3.0, (float) $edited['summary']['flooring_waste_percent'], 0.01);
    }

    public function test_drywall_and_studs_still_use_global_material_waste(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 0,
            'scope_preset' => 'wall',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
            'partition_lf' => 20,
            'stud_spacing_in' => 16,
            'material_waste_percent' => 10,
            'flooring_waste_percent' => 0,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(10.0, (float) $calc['summary']['material_waste_percent']);
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'stud_2x4'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'drywall_sheet'));
    }

    public function test_optional_extras_default_off_and_scope_copy_does_not_promise_them(): void
    {
        $defaults = BasementQuotationCalculator::defaultInputs();
        $this->assertFalse($defaults['include_building_permit']);
        $this->assertFalse($defaults['include_bcin_drawings']);

        $calc = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
        ]), $this->settings());
        $copy = $calc['client_scope']['generated'];
        $this->assertStringContainsString('optional extra-charge', strtolower($copy['not_included']));
        $this->assertStringContainsString('BCIN', $copy['not_included']);
        $this->assertStringContainsString('City building permit', $copy['not_included']);
        $this->assertStringNotContainsString('BCIN drawings as an optional extra-charge service (priced separately).', $copy['included']);
        $this->assertStringContainsString('Carpet flooring.', $copy['not_included']);
        $this->assertStringContainsString('contractor fee of 10%', strtolower($copy['assumptions']));
        $this->assertStringContainsString('change orders', strtolower($copy['assumptions']));
        $this->assertStringContainsString('municipal building-permit fee', strtolower($copy['assumptions']));
        $this->assertStringContainsString('engineer signing when that signing is required', strtolower($copy['assumptions']));
        $this->assertDoesNotMatchRegularExpression('/\blicensed\b/i', $copy['included'].$copy['not_included'].$copy['assumptions']);
        $this->assertStringContainsString('fully insured', strtolower($copy['assumptions']));
        $this->assertArrayNotHasKey('value_packages', $calc);
    }

    public function test_checked_extras_appear_in_included_copy_and_priced_lines(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'include_building_permit' => true,
            'building_permit_amount' => 175,
            'include_bcin_drawings' => true,
            'bcin_drawings_amount' => 500,
            'client_scope_included' => 'Custom included list',
        ]), $this->settings());

        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'building_permit'));
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'bcin_drawings'));
        $this->assertSame('Custom included list', $calc['client_scope']['included']);
        $this->assertStringContainsString('BCIN drawings as an optional extra-charge service', $calc['client_scope']['generated']['included']);
        $this->assertStringContainsString('City building permit as an optional extra-charge municipal fee', $calc['client_scope']['generated']['included']);
        $this->assertStringContainsString('BCIN qualified designer is not the same credential as a professional engineer', $calc['client_scope']['generated']['included']);
        $this->assertStringContainsString('engineer signing or stamping is included only when required', $calc['client_scope']['generated']['included']);
        $this->assertDoesNotMatchRegularExpression(
            '/every BCIN (designer|professional) is an engineer/i',
            $calc['client_scope']['generated']['included']
        );
    }

    public function test_city_permit_is_outside_fee_base_and_bcin_signing_service_is_inside(): void
    {
        $withoutExtras = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
        ]), $this->settings());
        $withPermitOnly = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
            'include_building_permit' => true,
            'building_permit_amount' => 200,
        ]), $this->settings());
        $withBcinOnly = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
            'include_bcin_drawings' => true,
            'bcin_drawings_amount' => 400,
        ]), $this->settings());

        $this->assertEqualsWithDelta(
            (float) $withoutExtras['summary']['contractor_fee_base_pre_hst'],
            (float) $withPermitOnly['summary']['contractor_fee_base_pre_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(
            (float) $withoutExtras['summary']['contractor_fee_pre_hst'],
            (float) $withPermitOnly['summary']['contractor_fee_pre_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(200.0, (float) $withPermitOnly['summary']['permit_pre_hst'], 0.01);
        $this->assertEqualsWithDelta(
            (float) $withoutExtras['summary']['contractor_fee_base_pre_hst'] + 400.0,
            (float) $withBcinOnly['summary']['contractor_fee_base_pre_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(40.0, round((float) $withBcinOnly['summary']['contractor_fee_pre_hst'] - (float) $withoutExtras['summary']['contractor_fee_pre_hst'], 2), 0.01);
    }

    public function test_pdf_renders_scope_without_package_columns(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->flooringQuote([
            'flooring_type' => 'lvp',
            'contractor_fee_percent' => 10,
            'client_scope_included' => 'Framing and drywall as specified.',
            'client_scope_not_included' => 'Carpet flooring.',
            'client_scope_assumptions' => BasementClientScope::assumptionsFeeSentence(10),
            'client_scope_allowances' => 'No separate cash allowances.',
        ]), $this->settings());
        $basement = new BasementQuotation([
            'inputs' => array_merge($this->flooringQuote(['contractor_fee_percent' => 10]), [
                'client_scope_included' => 'Framing and drywall as specified.',
                'client_scope_not_included' => 'Carpet flooring.',
                'client_scope_assumptions' => BasementClientScope::assumptionsFeeSentence(10),
                'client_scope_allowances' => 'No separate cash allowances.',
            ]),
            'calculation' => $calc,
        ]);
        $html = view('admin.client_projects._quotation_client_scope_pdf', [
            'quotation' => new class($basement) extends ClientProjectQuotation
            {
                public function __construct(private BasementQuotation $bq)
                {
                    parent::__construct(['source_type' => self::SOURCE_BASEMENT, 'source_id' => 1]);
                }

                public function basementSource(): ?BasementQuotation
                {
                    return $this->bq;
                }
            },
            'fmt' => fn ($n) => 'CAD$'.number_format((float) $n, 2, '.', ','),
        ])->render();

        $this->assertStringContainsString('Value', $html);
        $this->assertStringContainsString('Special terms', $html);
        $this->assertStringNotContainsString('Invest', $html);
        $this->assertStringNotContainsString('quote-invest', $html);
        $this->assertStringContainsString('Framing and drywall as specified.', $html);
        $this->assertStringContainsString('Carpet flooring.', $html);
    }

    public function test_contract_instruction_applies_fee_to_change_orders(): void
    {
        $instruction = require database_path('data/ai_instruction_contract.php');
        $this->assertStringContainsString('Contractor Fee', $instruction);
        $this->assertStringContainsString('written change order', $instruction);
        $this->assertStringContainsString('does not apply to Zanini hourly labour', $instruction);
        $this->assertStringContainsString('municipal building-permit fee', $instruction);
        $this->assertStringContainsString('professional engineer signing', $instruction);
        $this->assertStringContainsString('not the same credential as a professional engineer', $instruction);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function flooringQuote(array $overrides = []): array
    {
        return array_merge([
            'area_sqft' => 200,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'flooring_sf' => 200,
            'underlayment' => false,
            'include_demolition' => false,
            'insulate_walls' => false,
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'material_waste_percent' => 0,
            'owner_hourly_rate' => 80,
        ], $overrides);
    }

    private function settings(): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);
    }
}
