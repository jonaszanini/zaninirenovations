<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementCeilingAreaTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_form_has_one_ceiling_area_and_hides_drywall_duplicate(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="br-ceil-sf"', $html);
        $this->assertStringContainsString('name="ceiling_sf"', $html);
        $this->assertStringContainsString('data-br-split-ceiling="0"', $html);
        $this->assertMatchesRegularExpression(
            '/data-br-split-ceiling-field[^>]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('One ceiling area for this quote', $html);
        $this->assertStringNotContainsString('data-br-legacy-ceiling-notice', $html);
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-dw-ceil")(?=[^>]*\bdisabled\b)/',
            $html
        );
    }

    public function test_unified_ceiling_persists_on_save_reload_and_preview(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload([
            'client_name' => 'Ceiling Unify Client',
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 3767.65,
        ]);
        unset($payload['drywall_ceiling_sf']);

        $store = $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Ceiling Unify Client')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $this->assertEqualsWithDelta(1500.0, (float) ($quote->inputs['ceiling_sf'] ?? 0), 0.01);
        $this->assertFalse(\App\Services\BasementCeilingQuantities::usesSplit($quote->inputs ?? []));
        $this->assertEqualsWithDelta(1500.0, (float) ($quote->calculation['scope']['drywall_ceiling_sf'] ?? 0), 0.01);

        $edit = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-ceil-sf")(?=[^>]*\bvalue="1500)/',
            $edit
        );
        $this->assertMatchesRegularExpression(
            '/data-br-split-ceiling-field[^>]*\bhidden\b/',
            $edit
        );

        $preview = $this->post(route('admin.quotations.basement.preview'), $payload)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(1500.0, (float) ($preview['scope']['drywall_ceiling_sf'] ?? 0), 0.01);
        $hang = collect($preview['labor'] ?? [])->firstWhere('key', 'dw_hang');
        $this->assertIsArray($hang);
        $this->assertStringContainsString('5,268', $hang['detail']);
    }

    public function test_legacy_split_quote_keeps_saved_drywall_ceiling_and_shows_the_field(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 3767.65,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Split Ceiling',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'version' => 1,
        ]);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-br-split-ceiling="1"', $html);
        $this->assertTrue((bool) preg_match('/<div class="br-field--wide"[^>]*data-br-split-ceiling-field[^>]*>/', $html, $match));
        $this->assertStringNotContainsString('hidden', $match[0]);
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-dw-ceil")(?=[^>]*\bvalue="118)/',
            $html
        );
        $this->assertStringContainsString('Saved hang area for this quote', $html);
        $this->assertStringContainsString('keep that hang', $html);

        $recalc = (new BasementQuotationCalculator)->calculate($quote->inputs);
        $this->assertEqualsWithDelta(118.0, (float) $recalc['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertEqualsWithDelta(
            (float) $quote->final_quote_total,
            (float) $recalc['summary']['final_quote_total'],
            0.05
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input(?=[^>]*\bid="br-dw-ceil")(?=[^>]*\bdisabled\b)/',
            $html
        );
    }

    public function test_layout_import_fills_ceiling_area_then_preview_save_and_reload(): void
    {
        $this->actingAsAdmin();

        $project = \App\Models\RoomAreaProject::create([
            'name' => 'Ceiling import project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = \App\Models\RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Ceiling rec',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ]],
                'objects' => [],
            ],
        ]);

        $opt = collect($this->get(route('admin.utilities.room-area.layouts', ['q' => 'Ceiling rec']))
            ->assertOk()
            ->json())->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);
        $proposed = \App\Services\BasementRoomAreaImportMapper::proposedQuantities($opt, 8);
        $this->assertGreaterThan(0, (float) $proposed['ceiling_sf']);
        $this->assertEqualsWithDelta((float) $proposed['area_sqft'], (float) $proposed['ceiling_sf'], 0.05);

        $payload = $this->publishPayload([
            'client_name' => 'Ceiling Import Persist',
            'area_sqft' => $proposed['area_sqft'],
            'ceiling_type' => 'drywall',
            'ceiling_sf' => $proposed['ceiling_sf'],
            'drywall_wall_sf' => $proposed['drywall_wall_sf'] ?? 640,
            'paint_ceiling_sf' => $proposed['paint_ceiling_sf'],
            'flooring_sf' => $proposed['flooring_sf'],
            'room_area_import_id' => 'room:'.$room->id,
            'room_area_import_label' => 'Ceiling rec',
        ]);
        unset($payload['drywall_ceiling_sf']);

        $preview = $this->post(route('admin.quotations.basement.preview'), $payload)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta((float) $proposed['ceiling_sf'], (float) ($preview['scope']['drywall_ceiling_sf'] ?? 0), 0.05);
        $hang = collect($preview['labor'] ?? [])->firstWhere('key', 'dw_hang');
        $this->assertIsArray($hang);
        $this->assertStringNotContainsString('118', $hang['detail']);

        $store = $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Ceiling Import Persist')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $this->assertEqualsWithDelta((float) $proposed['ceiling_sf'], (float) ($quote->inputs['ceiling_sf'] ?? 0), 0.05);
        $this->assertEqualsWithDelta(
            (float) $proposed['ceiling_sf'],
            (float) ($quote->calculation['scope']['drywall_ceiling_sf'] ?? 0),
            0.05
        );

        $edit = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertTrue(
            (bool) preg_match(
                '/<input(?=[^>]*\bid="br-ceil-sf")(?=[^>]*\bvalue="'.preg_quote((string) (0 + $proposed['ceiling_sf']), '/').')/',
                $edit
            ),
            'edit form should show imported ceiling area'
        );
        $this->assertMatchesRegularExpression(
            '/data-br-split-ceiling-field[^>]*\bhidden\b/',
            $edit
        );
    }

    public function test_legacy_drop_skip_and_ceiling_off_preview_and_resave_keep_totals(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'drop' => [
                'client_name' => 'Legacy Drop Split',
                'ceiling_type' => 'drop',
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
            ],
            'none' => [
                'client_name' => 'Legacy Skip Split',
                'ceiling_type' => 'none',
                'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
            ],
            'off' => [
                'client_name' => 'Legacy Ceiling Off Split',
                'ceiling_type' => 'drywall',
                'enabled_sections' => array_values(array_filter(
                    BasementQuotationCalculator::presetEnabledSections('full'),
                    fn ($key) => $key !== 'ceiling'
                )),
            ],
        ];

        foreach ($cases as $label => $case) {
            $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
                'area_sqft' => 1500,
                'ceiling_height_ft' => 8,
                'ceiling_type' => $case['ceiling_type'],
                'ceiling_sf' => 1500,
                'drywall_ceiling_sf' => 118,
                'drywall_wall_sf' => 3767.65,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
                'enabled_sections' => $case['enabled_sections'],
                'flooring_type' => 'none',
                'include_demolition' => false,
                'insulate_walls' => false,
                'underlayment' => false,
            ]);
            $calc = (new BasementQuotationCalculator)->calculate($inputs);
            $this->assertEqualsWithDelta(118.0, (float) $calc['scope']['drywall_ceiling_sf'], 0.01, $label);
            $storedTotal = (float) $calc['summary']['final_quote_total'];
            $quote = BasementQuotation::create([
                'quote_number' => BasementQuotation::generateNumber(),
                'client_name' => $case['client_name'],
                'is_draft' => false,
                'inputs' => $inputs,
                'calculation' => $calc,
                'final_quote_total' => $storedTotal,
                'version' => 1,
            ]);

            $html = $this->get(route('admin.quotations.basement.edit', $quote))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString('data-br-split-ceiling="1"', $html, $label);
            $this->assertStringContainsString('keep that hang', $html, $label);
            $this->assertStringContainsString('data-br-legacy-ceiling-notice', $html, $label);
            $this->assertStringContainsString('Saved hang 118 sq ft stays on preview and resave', $html, $label);

            $preview = $this->post(route('admin.quotations.basement.preview'), array_merge($inputs, [
                'client_name' => $case['client_name'],
            ]))->assertOk()->json();
            $this->assertEqualsWithDelta(118.0, (float) ($preview['scope']['drywall_ceiling_sf'] ?? 0), 0.01, $label);
            $this->assertEqualsWithDelta($storedTotal, (float) ($preview['summary']['final_quote_total'] ?? 0), 0.05, $label);

            $this->put(route('admin.quotations.basement.update', $quote), $this->publishPayload(array_merge($inputs, [
                'client_name' => $case['client_name'],
            ])))->assertRedirect(route('admin.quotations.basement.edit', $quote));
            $quote->refresh();
            $this->assertEqualsWithDelta($storedTotal, (float) $quote->final_quote_total, 0.05, $label);
            $this->assertEqualsWithDelta(118.0, (float) ($quote->calculation['scope']['drywall_ceiling_sf'] ?? 0), 0.01, $label);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'client_name' => 'Ceiling Unify Client',
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
            'ceiling_type' => 'drywall',
        ], $overrides);
    }
}
