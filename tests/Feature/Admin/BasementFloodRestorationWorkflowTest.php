<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use App\Services\BasementRoomAreaImportMapper;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class BasementFloodRestorationWorkflowTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_create_with_flood_preset_query_and_price_configuration_render_flood_controls(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create', ['scope_preset' => 'flood']))
            ->assertOk()
            ->assertSee('Flood Restoration')
            ->assertSee('1 of 8')
            ->assertSee('8 of 8')
            ->assertDontSee('1 of 14')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="scope_preset"[^>]*value="flood"[^>]*\bchecked\b/s',
            $html
        );
        $this->assertSame(12, preg_match_all('/data-br-enabled-section="[^"]+"/', $html));

        $configHtml = $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->getContent();

        foreach ([
            'vapor_barrier_patch',
            'casing_legs',
            'toilet_wax_ring',
            'drywall_flood_joint_lf_per_hr',
            'vapor_barrier_patch_lf_per_hr',
            'casing_legs_opening_hours',
        ] as $key) {
            $this->assertStringContainsString($key, $configHtml, 'Price configuration must show '.$key);
        }
    }

    public function test_draft_save_reopen_edit_publish_preview_and_pdf_for_flood_restoration(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $floodSections = BasementQuotationCalculator::presetEnabledSections('flood');

        $draftPayload = [
            'save_intent' => 'draft',
            'client_name' => $project->client_name,
            'scope_preset' => 'flood',
            'enabled_sections' => $floodSections,
            'perimeter_lf' => 120,
            'partition_lf' => 40,
            'flood_cut_height_in' => 48,
            'flood_partition_both_faces' => '0',
            'flood_casing_mode' => 'full',
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'flooring_waste_percent' => 7,
            'insulate_walls' => '1',
            'ceiling_type' => 'none',
            'casing_openings' => 3,
            'include_shoe' => '1',
            'include_cleaning' => '1',
            'include_touchups' => '1',
            'payment_installments' => 1,
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'trim_material_lf_v2' => '1',
            'corner_bead_by_height' => '1',
            'drywall_boxing_v2' => '1',
        ];

        $this->post(route('admin.quotations.basement.store'), $draftPayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->latest('id')->firstOrFail();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertFalse($quote->supportsClientIntake());
        $this->assertSame('flood', $quote->inputs['scope_preset'] ?? null);
        $this->assertEquals(120.0, (float) ($quote->inputs['perimeter_lf'] ?? 0));
        $this->assertEquals(40.0, (float) ($quote->inputs['partition_lf'] ?? 0));
        $this->assertEquals(48.0, (float) ($quote->inputs['flood_cut_height_in'] ?? 0));
        $this->assertFalse((bool) ($quote->inputs['flood_partition_both_faces'] ?? true));
        $this->assertSame('full', $quote->inputs['flood_casing_mode'] ?? null);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/id="br-perimeter-lf"[^>]*value="120"/', $editHtml);
        $this->assertMatchesRegularExpression('/id="br-partition"[^>]*value="40"/', $editHtml);
        $this->assertMatchesRegularExpression('/id="br-flood-cut-height"[^>]*value="48"/', $editHtml);
        $this->assertMatchesRegularExpression('/name="flood_casing_mode"[^>]*value="full"[^>]*\bchecked\b/s', $editHtml);
        $this->assertSame(
            1,
            preg_match('/<input[^>]*id="br-flood-both-faces"[^>]*>/s', $editHtml, $bothMatch)
        );
        $this->assertDoesNotMatchRegularExpression('/\bchecked\b/i', $bothMatch[0]);

        $publishPayload = array_merge($draftPayload, [
            'save_intent' => 'publish',
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => '1',
            'flood_casing_mode' => 'legs',
            'include_cleaning' => '1',
            'include_touchups' => '1',
            'payment_installments' => 1,
        ]);

        $this->put(route('admin.quotations.basement.update', $quote), $publishPayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertSame('Flood restoration', $quote->jobLabelForPdf());

        $scopeHtml = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('Tape &amp; blend horizontal flood cut joint', $scopeHtml);
        $this->assertStringContainsString('Door casing legs (reuse head)', $scopeHtml);

        $resolvedScope = BasementClientScope::resolveCopy($quote->inputs, $quote->calculation);
        $this->assertStringContainsString(
            'Water extraction, drying, moisture readings and mould remediation are completed by others before we start.',
            $resolvedScope['assumptions']
        );

        // Link to ClientProject and verify real PDF output contains Flood restoration & assumption
        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->firstOrFail();

        $pdfResponse = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]))
            ->assertOk();
        $pdfBytes = $pdfResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);

        $pdfPath = storage_path('app/flood-restoration-test-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdfBytes);
        try {
            $pdfText = $this->extractPdfText($pdfPath);
            $plainPdf = preg_replace('/\s+/', '', str_replace("\0", '', $pdfText)) ?? $pdfText;
            $this->assertStringContainsString('Flood restoration', $pdfText);
            $this->assertStringContainsString(
                'Waterextraction,drying,moisturereadingsandmouldremediationarecompletedbyothersbeforewestart.',
                $plainPdf
            );
        } finally {
            @unlink($pdfPath);
        }

        // Preview JSON returns expected flood scope and generated assumptions
        $preview = $this->post(route('admin.quotations.basement.preview'), $publishPayload)
            ->assertOk()
            ->json();

        $this->assertEquals(400.0, (float) ($preview['scope']['drywall_wall_net_sf'] ?? -1));
        $this->assertEquals(240.0, (float) ($preview['scope']['insulation_wall_sf'] ?? -1));
        $this->assertEquals(200.0, (float) ($preview['scope']['baseboard_lf'] ?? -1));
        $this->assertEquals(1600.0, (float) ($preview['scope']['paint_wall_sf'] ?? -1));
        $this->assertEquals(400.0, (float) ($preview['scope']['paint_primer_sf'] ?? -1));
        $this->assertSame('Flood restoration', $preview['scope']['finish_label'] ?? null);
        $this->assertStringContainsString(
            'Water extraction, drying, moisture readings and mould remediation are completed by others before we start.',
            (string) ($preview['client_scope']['generated']['assumptions'] ?? '')
        );
    }

    public function test_room_area_layouts_endpoint_and_mapper_populate_flood_perimeter_and_partition(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Flood layout project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Flood Basement',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 600,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 30 * $cm, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p1', 'start' => ['x' => 15 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 15 * $cm, 'y' => 10 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Flood Basement',
                        'walls' => ['w1', 'w2', 'w3', 'w4', 'p1'],
                    ]],
                ],
            ],
        ]);

        $payload = $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Flood Basement']))
            ->assertOk()
            ->json();

        $opt = collect($payload)->firstWhere('id', 'room:'.$room->id);
        $this->assertIsArray($opt);

        $proposed = BasementRoomAreaImportMapper::proposedQuantities($opt, 8, 'none', [
            'scope_preset' => 'flood',
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => true,
        ]);

        $this->assertGreaterThan(0, (float) $proposed['perimeter_lf']);
        $this->assertGreaterThan(0, (float) $proposed['partition_lf']);
        $expectedRunLf = round((float) $proposed['perimeter_lf'] + (2.0 * (float) $proposed['partition_lf']), 2);
        $this->assertEqualsWithDelta(round((float) $proposed['perimeter_lf'] * 2.0, 2), (float) $proposed['insulation_wall_sf'], 0.05);
        $this->assertEqualsWithDelta(round($expectedRunLf * 2.0, 2), (float) $proposed['drywall_wall_sf'], 0.05);
        $this->assertEqualsWithDelta(round($expectedRunLf * 8.0, 2), (float) $proposed['paint_wall_sf'], 0.05);
    }

    public function test_flood_preset_checks_final_cleaning_and_touchups_without_changing_saved_quotes_or_other_presets(): void
    {
        $this->actingAsAdmin();

        $floodHtml = $this->get(route('admin.quotations.basement.create', ['scope_preset' => 'flood']))
            ->assertOk()
            ->assertSee('Final cleaning')
            ->assertSee('Touch-ups / punch list')
            ->assertSee('Final Details')
            ->getContent();
        $this->assertCheckboxChecked($floodHtml, 'include_cleaning', true);
        $this->assertCheckboxChecked($floodHtml, 'include_touchups', true);
        $this->assertCheckboxChecked($floodHtml, 'enabled_sections[]', true, 'finishing');

        $fullHtml = $this->get(route('admin.quotations.basement.create', ['scope_preset' => 'full']))
            ->assertOk()
            ->getContent();
        $this->assertCheckboxChecked($fullHtml, 'include_cleaning', true);
        $this->assertCheckboxChecked($fullHtml, 'include_touchups', true);

        $wallHtml = $this->get(route('admin.quotations.basement.create', ['scope_preset' => 'wall']))
            ->assertOk()
            ->getContent();
        $this->assertCheckboxChecked($wallHtml, 'include_cleaning', false);
        $this->assertCheckboxChecked($wallHtml, 'include_touchups', false);

        $this->post(route('admin.quotations.basement.store'), [
            'save_intent' => 'draft',
            'client_name' => 'Saved Flood Cleaning Off',
            'scope_preset' => 'flood',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('flood'),
            'area_sqft' => 100,
            'ceiling_height_ft' => 8,
            'ceiling_type' => 'none',
            'flooring_type' => 'lvp',
            'include_cleaning' => '0',
            'include_touchups' => '0',
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
            'payment_installments' => 1,
            'owner_hourly_rate' => 85,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Saved Flood Cleaning Off')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->inputs['include_cleaning']);
        $this->assertFalse((bool) $quote->inputs['include_touchups']);

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertCheckboxChecked($editHtml, 'include_cleaning', false);
        $this->assertCheckboxChecked($editHtml, 'include_touchups', false);
    }

    public function test_flood_bathroom_options_persist_reopen_and_change_the_client_pdf_totals(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $sections = array_values(array_unique(array_merge(
            BasementQuotationCalculator::presetEnabledSections('flood'),
            ['bathroom']
        )));
        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Flood Bath Options',
            'scope_preset' => 'flood',
            'enabled_sections' => $sections,
            'perimeter_lf' => 120,
            'partition_lf' => 40,
            'flood_cut_height_in' => 24,
            'flood_partition_both_faces' => '1',
            'flood_casing_mode' => 'legs',
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'flooring_waste_percent' => 7,
            'include_cleaning' => '1',
            'include_touchups' => '1',
            'payment_installments' => 1,
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'include_helper' => '0',
            'trim_material_lf_v2' => '1',
            'corner_bead_by_height' => '1',
            'drywall_boxing_v2' => '1',
            'flood_bath_shower' => '1',
            'flood_bath_shower_material' => '425.50',
            'flood_bath_shower_hours' => '7.5',
            'flood_bath_flooring' => '0',
            'flood_bath_drywall' => '0',
            'flood_bath_vanity' => '0',
            'flood_bath_toilet' => '0',
        ];

        $this->post(route('admin.quotations.basement.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $quote = BasementQuotation::query()->where('client_name', 'Flood Bath Options')->firstOrFail();
        $this->assertTrue((bool) ($quote->inputs['flood_bath_shower'] ?? false));
        $this->assertEquals(425.50, (float) $quote->inputs['flood_bath_shower_material']);
        $this->assertEquals(7.5, (float) $quote->inputs['flood_bath_shower_hours']);
        $this->assertFalse((bool) ($quote->inputs['flood_bath_flooring'] ?? true));

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertCheckboxChecked($editHtml, 'flood_bath_shower', true);
        $this->assertCheckboxChecked($editHtml, 'flood_bath_flooring', false);
        $this->assertStringContainsString('value="425.50"', $editHtml);
        $this->assertStringContainsString('value="7.5"', $editHtml);
        $this->assertStringContainsString('Shower kit or shower base with walls', $editHtml);
        $this->assertStringContainsString('data-br-hide-on-flood', $editHtml);

        $preview = $this->post(route('admin.quotations.basement.preview'), $payload)->assertOk()->json();
        $labor = collect($preview['labor'] ?? [])->firstWhere('key', 'flood_bath_shower');
        $material = collect($preview['materials'] ?? [])->firstWhere('key', 'flood_bath_shower');
        $this->assertNotNull($labor);
        $this->assertEquals(637.50, (float) $labor['amount']);
        $this->assertEquals(425.50, (float) $material['amount']);
        $this->assertStringContainsString('Bathroom flood restoration', (string) ($preview['client_scope']['included'] ?? ''));
        $this->assertStringContainsString('Shower kit or shower base with walls', (string) ($preview['client_scope']['included'] ?? ''));

        $this->post(route('admin.quotations.basement.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $quote->id)
            ->firstOrFail();
        $pdfResponse = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]))->assertOk();
        $pdfPath = storage_path('app/flood-bath-options-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdfResponse->getContent());
        try {
            $pdfText = preg_replace('/\s+/', ' ', $this->extractPdfText($pdfPath)) ?? '';
            $this->assertStringContainsString('Basement renovation (labour)', $pdfText);
            $this->assertStringContainsString('Basement renovation materials', $pdfText);
            $materials = number_format((float) $quote->fresh()->calculation['summary']['materials_pre_hst'], 2, '.', ',');
            $this->assertStringContainsString($materials, $pdfText);
            $this->assertStringContainsString('Bathroom flood restoration', $pdfText);
            $this->assertStringContainsString('Shower kit or shower base with walls', $pdfText);
        } finally {
            @unlink($pdfPath);
        }
    }

    private function assertCheckboxChecked(string $html, string $name, bool $checked, ?string $value = null): void
    {
        preg_match_all('/<input\b[^>]*type="checkbox"[^>]*>/s', $html, $tags);
        $match = null;
        foreach ($tags[0] as $tag) {
            if (! preg_match('/\bname="'.preg_quote($name, '/').'"/', $tag)) {
                continue;
            }
            if ($value !== null && ! preg_match('/\bvalue="'.preg_quote($value, '/').'"/', $tag)) {
                continue;
            }
            $match = $tag;
            break;
        }
        $this->assertNotNull($match, 'Missing checkbox '.$name.($value ? '='.$value : ''));
        if ($checked) {
            $this->assertMatchesRegularExpression('/\bchecked\b/', $match);
        } else {
            $this->assertDoesNotMatchRegularExpression('/\bchecked\b/', $match);
        }
    }
}
