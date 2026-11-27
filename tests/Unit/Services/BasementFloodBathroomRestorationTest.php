<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementFloodBathroomRestorationTest extends TestCase
{
    public function test_option_defaults_use_catalog_rates_and_a_fixed_shower(): void
    {
        $options = BasementQuotationCalculator::floodBathroomOptions();

        $this->assertSame(
            ['flooring', 'drywall', 'shower', 'vanity', 'toilet'],
            array_keys($options)
        );
        $this->assertEqualsWithDelta(140.00, $options['flooring']['material'], 0.001);
        $this->assertEqualsWithDelta(1.60, $options['flooring']['hours'], 0.001);
        $this->assertEqualsWithDelta(104.00, $options['drywall']['material'], 0.001);
        $this->assertEqualsWithDelta(9.94, $options['drywall']['hours'], 0.001);
        $this->assertEqualsWithDelta(550.00, $options['shower']['material'], 0.001);
        $this->assertEqualsWithDelta(6.00, $options['shower']['hours'], 0.001);
        $this->assertEqualsWithDelta(360.00, $options['vanity']['material'], 0.001);
        $this->assertEqualsWithDelta(4.00, $options['vanity']['hours'], 0.001);
        $this->assertEqualsWithDelta(12.00, $options['toilet']['material'], 0.001);
        $this->assertEqualsWithDelta(2.00, $options['toilet']['hours'], 0.001);
        $this->assertStringContainsString('not counted twice', $options['drywall']['info']);
        $this->assertStringNotContainsString('800', $options['shower']['info']);

        $materials = BasementSettings::defaultMaterials();
        $materials['vanity']['unit_price'] = 410;
        $materials['shower_kit']['unit_price'] = 800;
        $overlaid = BasementQuotationCalculator::floodBathroomOptions($materials);
        $this->assertEqualsWithDelta(410.00, $overlaid['vanity']['material'], 0.001);
        $this->assertEqualsWithDelta(550.00, $overlaid['shower']['material'], 0.001);
    }

    public function test_selected_options_price_owner_hours_and_a_material_lump(): void
    {
        $calculator = new BasementQuotationCalculator;
        $settings = $this->settings();
        $base = $this->floodInputs();
        $before = $calculator->calculate($base, $settings);

        $inputs = $this->floodInputs([
            'flood_bath_shower' => true,
            'flood_bath_toilet' => true,
        ]);
        $calc = $calculator->calculate($inputs, $settings);

        $labor = collect($calc['labor'])->keyBy('key');
        $materials = collect($calc['materials'])->keyBy('key');

        $this->assertEqualsWithDelta(6.0, (float) $labor['flood_bath_shower']['hours'], 0.001);
        $this->assertEqualsWithDelta(510.00, (float) $labor['flood_bath_shower']['amount'], 0.001);
        $this->assertSame('1 each', $labor['flood_bath_shower']['detail']);
        $this->assertSame('bathroom', $labor['flood_bath_shower']['section']);
        $this->assertEqualsWithDelta(550.00, (float) $materials['flood_bath_shower']['amount'], 0.001);
        $this->assertSame('each', $materials['flood_bath_shower']['unit']);

        $this->assertEqualsWithDelta(2.0, (float) $labor['flood_bath_toilet']['hours'], 0.001);
        $this->assertEqualsWithDelta(170.00, (float) $labor['flood_bath_toilet']['amount'], 0.001);
        $this->assertEqualsWithDelta(12.00, (float) $materials['flood_bath_toilet']['amount'], 0.001);
        $this->assertNull($materials->get('toilet_wax_ring'));
        $this->assertNull($materials->get('shower_kit'));

        $this->assertEqualsWithDelta(680.00, (float) $calc['summary']['labor_pre_hst'] - (float) $before['summary']['labor_pre_hst'], 0.02);
        $this->assertEqualsWithDelta(562.00, (float) $calc['summary']['materials_pre_hst'] - (float) $before['summary']['materials_pre_hst'], 0.02);
        $this->assertEqualsWithDelta(
            (float) $before['summary']['section_subtotals_pre_hst']['bathroom'] + 680 + 562,
            (float) $calc['summary']['section_subtotals_pre_hst']['bathroom'],
            0.02
        );
        $this->assertGreaterThan((float) $before['summary']['grand_total'], (float) $calc['summary']['grand_total']);
    }

    public function test_helper_rate_applies_to_flood_bathroom_hours(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->floodInputs([
            'include_helper' => true,
            'helper_hourly_rate' => 45,
            'flood_bath_vanity' => true,
            'flood_bath_vanity_hours' => 4,
            'flood_bath_vanity_material' => 0,
        ]), $this->settings());

        $row = collect($calc['labor'])->firstWhere('key', 'flood_bath_vanity');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(520.00, (float) $row['amount'], 0.001);
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'flood_bath_vanity'));
    }

    public function test_stored_amounts_win_over_a_later_catalog_change(): void
    {
        $materials = BasementSettings::defaultMaterials();
        $materials['lvp']['unit_price'] = 9.00;
        $calc = (new BasementQuotationCalculator)->calculate($this->floodInputs([
            'flood_bath_flooring' => true,
            'flood_bath_flooring_material' => 140,
            'flood_bath_flooring_hours' => 1.6,
        ]), $this->settings($materials));

        $row = collect($calc['materials'])->firstWhere('key', 'flood_bath_flooring');
        $this->assertEqualsWithDelta(140.00, (float) $row['amount'], 0.001);

        $fresh = (new BasementQuotationCalculator)->calculate($this->floodInputs([
            'flood_bath_flooring' => true,
        ]), $this->settings($materials));
        $freshRow = collect($fresh['materials'])->firstWhere('key', 'flood_bath_flooring');
        $this->assertEqualsWithDelta(360.00, (float) $freshRow['amount'], 0.001);
    }

    public function test_unchecked_or_hidden_bathroom_section_adds_no_flood_lines(): void
    {
        $calculator = new BasementQuotationCalculator;
        $settings = $this->settings();
        $off = $calculator->calculate($this->floodInputs(), $settings);
        $hidden = $calculator->calculate($this->floodInputs([
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('flood'),
            'flood_bath_shower' => true,
            'flood_bath_shower_material' => 550,
            'flood_bath_shower_hours' => 6,
        ]), $settings);

        $this->assertSame([], $this->floodLineKeys($off));
        $this->assertSame([], $this->floodLineKeys($hidden));
        $this->assertEqualsWithDelta(
            (float) $off['summary']['grand_total'],
            (float) $hidden['summary']['grand_total'],
            0.02
        );
    }

    public function test_other_presets_ignore_flood_bathroom_flags_and_keep_their_totals(): void
    {
        $calculator = new BasementQuotationCalculator;
        $settings = $this->settings();

        foreach (['full', 'bathroom', 'wall', 'custom'] as $preset) {
            $inputs = [
                'scope_preset' => $preset,
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections($preset),
                'area_sqft' => 200,
                'owner_hourly_rate' => 85,
                'include_bathroom' => true,
                'include_toilet' => true,
            ];
            $plain = $calculator->calculate($inputs, $settings);
            $flagged = $calculator->calculate($inputs + [
                'flood_bath_shower' => true,
                'flood_bath_shower_material' => 550,
                'flood_bath_shower_hours' => 6,
                'flood_bath_drywall' => true,
            ], $settings);

            $this->assertSame([], $this->floodLineKeys($flagged), $preset);
            $this->assertEqualsWithDelta(
                (float) $plain['summary']['grand_total'],
                (float) $flagged['summary']['grand_total'],
                0.02,
                $preset
            );
            $this->assertEqualsWithDelta(
                (float) $plain['summary']['labor_pre_hst'],
                (float) $flagged['summary']['labor_pre_hst'],
                0.02,
                $preset
            );
        }
    }

    public function test_client_scope_lists_chosen_flood_bathroom_items_and_omits_the_trade_when_none_are_chosen(): void
    {
        $chosen = BasementClientScope::generatedCopy($this->floodInputs([
            'flood_bath_flooring' => true,
            'flood_bath_drywall' => true,
        ]));
        $included = $this->lines($chosen['included']);
        $notIncluded = $this->lines($chosen['not_included']);

        $this->assertContains('Bathroom flood restoration', $included);
        $this->assertContains('Bathroom flooring (replace)', $included);
        $this->assertContains('Drywall and paint', $included);
        $this->assertNotContains('Shower kit or shower base with walls', $included);
        $this->assertNotContains('Bathroom', $included);
        $this->assertNotContains('Bathroom', $notIncluded);
        $this->assertNotContains('Bathroom flood restoration', $notIncluded);

        $none = BasementClientScope::generatedCopy($this->floodInputs());
        $this->assertNotContains('Bathroom flood restoration', $this->lines($none['included']));
        $this->assertContains('Bathroom', $this->lines($none['not_included']));

        $build = BasementClientScope::generatedCopy([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'bath_type' => 'half',
            'flood_bath_shower' => true,
        ]);
        $this->assertStringContainsString('Half bathroom finishing as specified on this quotation.', $build['included']);
        $this->assertStringNotContainsString('Bathroom flood restoration', $build['included']);
    }

    public function test_final_details_is_appended_to_a_flood_included_list_that_keeps_a_handwritten_note(): void
    {
        $withBathroom = array_values(array_unique(array_merge(
            BasementQuotationCalculator::presetEnabledSections('flood'),
            ['bathroom']
        )));
        $offSections = array_values(array_diff($withBathroom, ['finishing']));
        $off = $this->floodInputs([
            'enabled_sections' => $offSections,
            'include_cleaning' => false,
            'include_touchups' => false,
            'flood_bath_shower' => true,
        ]);
        $generatedOff = BasementClientScope::generatedCopy($off);
        $this->assertContains('Final Details & Cleanup', $this->lines($generatedOff['not_included']));
        $this->assertNotContains('Final Details & Cleanup', $this->lines($generatedOff['included']));

        $on = $off;
        $on['enabled_sections'] = $withBathroom;
        $on['client_scope_included'] = $generatedOff['included']."\nSite note stays.";
        $on['client_scope_not_included'] = $generatedOff['not_included'];

        $resolved = BasementClientScope::resolveCopy($on);
        $included = $this->lines($resolved['included']);
        $this->assertContains('Final Details & Cleanup', $included);
        $this->assertContains('Site note stays.', $included);
        $this->assertContains('Bathroom flood restoration', $included);
        $this->assertContains('Drywall rebuild to 24 in flood cut height, horizontal cut joint taping, finishing, and blending into existing upper walls.', $included);
        $this->assertNotContains('Final Details & Cleanup', $this->lines($resolved['not_included']));

        $custom = $on;
        $custom['client_scope_included'] = 'Custom included list';
        $custom['client_scope_not_included'] = 'Custom not included list';
        $customResolved = BasementClientScope::resolveCopy($custom);
        $this->assertSame('Custom included list', $customResolved['included']);
        $this->assertSame('Custom not included list', $customResolved['not_included']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function floodInputs(array $extra = []): array
    {
        return array_merge([
            'scope_preset' => 'flood',
            'enabled_sections' => array_values(array_unique(array_merge(
                BasementQuotationCalculator::presetEnabledSections('flood'),
                ['bathroom']
            ))),
            'perimeter_lf' => 120,
            'partition_lf' => 40,
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => true,
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'include_helper' => false,
            'contractor_fee_percent' => 10,
        ], $extra);
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $materials
     */
    private function settings(?array $materials = null): BasementSettings
    {
        $settings = new BasementSettings;
        $settings->forceFill([
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => $materials ?? BasementSettings::defaultMaterials(),
        ]);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return list<string>
     */
    private function floodLineKeys(array $calc): array
    {
        $keys = [];
        foreach (array_merge($calc['labor'] ?? [], $calc['materials'] ?? []) as $row) {
            $key = (string) ($row['key'] ?? '');
            if (str_starts_with($key, 'flood_bath_')) {
                $keys[] = $key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
