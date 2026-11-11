<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\Material;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\RoomAreaQuoteLineInserter;
use App\Services\StandardQuotationCalculator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaQuoteLinesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_open_create_quote(): void
    {
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertRedirect();
    }

    public function test_create_quote_page_renders_for_saved_room(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee('Create quote', false)
            ->assertSee('Add priced lines', false)
            ->assertSee('cp-money-input', false)
            ->assertSee('data-cp-money-entry="direct"', false)
            ->assertSee('name="trades[]"', false)
            ->assertSee('value="flooring"', false)
            ->assertSee('Start a calculator', false)
            ->assertSee('lf perimeter', false)
            ->assertSee('Millwork', false)
            ->assertSee('Stairs', false)
            ->assertDontSee(route('admin.quotations.painting.create'), false)
            ->assertDontSee('lf walls', false);
    }

    public function test_inserts_lines_into_new_quotation_with_server_computed_qty(): void
    {
        $this->actingAsAdmin();
        $clientProject = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $room = $this->makeRoom($clientProject);
        $flooring = Material::create([
            'name' => 'Oak LVP',
            'price_cad' => 4.50,
            'unit' => 'sq ft',
            'is_active' => true,
        ]);
        $baseboard = Material::create([
            'name' => 'MDF base',
            'price_cad' => 2.00,
            'unit' => 'linear ft',
            'is_active' => true,
        ]);

        $response = $this->from(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.quote.store'), [
                'layout' => 'room:'.$room->id,
                'trades' => ['flooring', 'baseboard'],
                'waste' => ['flooring' => 10, 'baseboard' => 0],
                'unit_price' => ['flooring' => 4.50, 'baseboard' => 2.00],
                'catalog_material_id' => [
                    'flooring' => $flooring->id,
                    'baseboard' => $baseboard->id,
                ],
                'client_name' => 'Pat Owner',
                'display_title' => 'Kitchen — Room Area',
            ]);

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $response->assertRedirect(route('admin.quotations.standard.edit', $quote));

        $materials = $quote->flattenedMaterials();
        $this->assertCount(2, $materials);

        $floorLine = collect($materials)->first(fn ($row) => str_contains((string) $row['description'], 'Flooring'));
        $baseLine = collect($materials)->first(fn ($row) => str_contains((string) $row['description'], 'Baseboard'));
        $this->assertNotNull($floorLine);
        $this->assertNotNull($baseLine);
        $this->assertEquals(88.0, (float) $floorLine['quantity']);
        $this->assertEquals(30.0, (float) $baseLine['quantity']);
        $this->assertEquals(4.50, (float) $floorLine['unit_price']);
        $this->assertEquals(2.00, (float) $baseLine['unit_price']);
        $this->assertEquals(456.00, (float) $quote->materials_subtotal_before_hst);
        $this->assertEquals(59.28, (float) $quote->materials_hst);
        $this->assertEquals(515.28, (float) $quote->grand_total);
        $this->assertSame($clientProject->id, (int) $quote->client_project_id);
    }

    public function test_ignores_client_supplied_quantities(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->post(route('admin.utilities.room-area.quote.store'), [
            'layout' => 'room:'.$room->id,
            'trades' => ['flooring'],
            'waste' => ['flooring' => 0],
            'unit_price' => ['flooring' => 5],
            'qty' => ['flooring' => 9999],
            'quantity' => ['flooring' => 9999],
            'client_name' => 'Pat Owner',
            'display_title' => 'Floor only',
        ])->assertRedirect();

        $quote = StandardQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $line = $quote->flattenedMaterials()[0];
        $this->assertEquals(80.0, (float) $line['quantity']);
    }

    public function test_adding_kit_lines_to_saved_quote_keeps_existing_rows_and_adds_kit_to_total(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $works = [$this->kitchenWork([
            $this->inclHstFlooringRow(),
            $this->electricalRow(),
        ])];

        $quote = $this->createSavedQuote('SQ-KEEP-1', $works);
        $savedWork = $quote->resolvedWorks()[0];
        $savedLines = $savedWork['materials'];
        $savedTotal = (float) $quote->grand_total;
        $savedLabour = (float) $quote->labour_total;
        $this->assertTrue((bool) ($savedLines[0]['hst_includes_unit'] ?? false));
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $savedLines[1]['line_kind'] ?? null);

        $this->postKitLines($room, $quote, ['flooring'], ['flooring' => 4.50]);

        $quote->refresh();
        $afterWork = $quote->resolvedWorks()[0];
        $afterLines = $afterWork['materials'];
        $this->assertCount(count($savedLines) + 1, $afterLines);
        $this->assertSame(
            json_encode($savedLines),
            json_encode(array_slice($afterLines, 0, count($savedLines)))
        );
        $this->assertTrue((bool) ($afterLines[0]['hst_includes_unit'] ?? false));
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $afterLines[1]['line_kind'] ?? null);
        $this->assertSame(StandardQuotation::LINE_KIND_MATERIAL, $afterLines[2]['line_kind'] ?? null);
        $this->assertNull($afterLines[2]['url'] ?? null);
        $this->assertNull($afterLines[2]['image_url'] ?? null);
        $this->assertEquals(4.50, (float) $afterLines[2]['unit_price']);
        $this->assertEquals(80.0, (float) $afterLines[2]['quantity']);
        $this->assertSame($savedWork['labour_use_fixed'], $afterWork['labour_use_fixed']);
        $this->assertEquals($savedWork['labour_fixed_amount'], $afterWork['labour_fixed_amount']);
        $this->assertEquals($savedLabour, (float) $quote->labour_total);
        $this->assertSame(round($savedTotal + 406.80, 2), (float) $quote->grand_total);
        $this->assertSame($quote->editorGrandTotal(), (float) $quote->grand_total);
        $this->assertPdfClientLinesSumToSavedTotal($quote);
    }

    public function test_adding_kit_lines_matches_by_index_before_filtering_and_includes_editor_extras(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $works = [
            $this->kitchenWork([
                $this->inclHstFlooringRow(),
                $this->blankMaterialRow(),
                $this->electricalRow(),
            ]),
            [
                'title' => 'Bath',
                'materials' => [$this->hstExemptRow()],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0.0,
                'labour_fixed_hst_included' => false,
                'labour_hourly_rate' => 0.0,
                'labour_mode' => 'hours',
                'labour_quantity' => 0.0,
            ],
        ];

        $quote = $this->createSavedQuote('SQ-KEEP-2', $works, [
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'include_commute' => true,
            'commute_amount' => 100.00,
            'commute_estimated_trips' => 2,
            'include_debris_removal' => true,
            'debris_removal_amount' => 110.00,
        ]);

        $savedWorks = $quote->resolvedWorks();
        $savedJob0 = $savedWorks[0]['materials'];
        $savedJob1 = $savedWorks[1]['materials'];
        $savedTotal = (float) $quote->grand_total;
        $this->assertSame(2297.50, $savedTotal);
        $this->assertSame($quote->editorGrandTotal(), $savedTotal);

        $this->postKitLines($room, $quote, ['flooring', 'baseboard'], [
            'flooring' => 4.50,
            'baseboard' => 2.00,
        ]);

        $quote->refresh();
        $afterWorks = $quote->resolvedWorks();
        $afterJob0 = $afterWorks[0]['materials'];
        $afterJob1 = $afterWorks[1]['materials'];

        $this->assertCount(count($savedJob0) + 2, $afterJob0);
        $this->assertSame(json_encode($savedJob0), json_encode(array_slice($afterJob0, 0, count($savedJob0))));
        $this->assertSame(json_encode($savedJob1), json_encode($afterJob1));
        $this->assertSame('', trim((string) ($afterJob0[1]['short_name'] ?? '')));
        $this->assertSame('', trim((string) ($afterJob0[1]['description'] ?? '')));
        $this->assertSame(StandardQuotation::LINE_KIND_ELECTRICAL, $afterJob0[2]['line_kind'] ?? null);
        $this->assertSame(StandardQuotation::LINE_KIND_MATERIAL, $afterJob0[3]['line_kind'] ?? null);
        $this->assertSame(StandardQuotation::LINE_KIND_MATERIAL, $afterJob0[4]['line_kind'] ?? null);
        $this->assertNull($afterJob0[3]['url'] ?? null);
        $this->assertNull($afterJob0[4]['url'] ?? null);
        $this->assertEquals(4.50, (float) $afterJob0[3]['unit_price']);
        $this->assertEquals(2.00, (float) $afterJob0[4]['unit_price']);
        $this->assertEquals(80.0, (float) $afterJob0[3]['quantity']);
        $this->assertEquals(30.0, (float) $afterJob0[4]['quantity']);

        $expected = $quote->editorGrandTotal();
        $this->assertSame(2819.56, $expected);
        $this->assertSame($expected, (float) $quote->grand_total);
        $this->assertNotSame($savedTotal, (float) $quote->grand_total);
        $this->assertPdfClientLinesSumToSavedTotal($quote);
    }

    public function test_kit_add_project_sync_matches_editor_tax_splits_and_extras(): void
    {
        $this->actingAsAdmin();
        $clientProject = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $room = $this->makeRoom($clientProject);

        $works = [
            $this->kitchenWork([
                $this->inclHstFlooringRow(),
                $this->blankMaterialRow(),
                $this->electricalRow(),
            ]),
            [
                'title' => 'Bath',
                'materials' => [$this->hstExemptRow()],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0.0,
                'labour_fixed_hst_included' => false,
                'labour_hourly_rate' => 0.0,
                'labour_mode' => 'hours',
                'labour_quantity' => 0.0,
            ],
        ];

        $quote = $this->createSavedQuote('SQ-KEEP-3', $works, [
            'client_project_id' => $clientProject->id,
            'is_draft' => false,
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'include_commute' => true,
            'commute_amount' => 100.00,
            'commute_estimated_trips' => 2,
            'include_debris_removal' => true,
            'debris_removal_amount' => 110.00,
        ]);

        $this->postKitLines($room, $quote, ['flooring'], ['flooring' => 4.50]);

        $quote->refresh();
        $hub = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STANDARD)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($hub);

        $expectedItems = $quote->projectQuotationLineItems($quote->pdfSummaryLineDescription());
        $this->assertEquals($expectedItems, $hub->items);

        $taxable = collect($hub->items)->first(fn ($row) => ! empty($row['apply_hst']));
        $exempt = collect($hub->items)->first(fn ($row) => ($row['apply_hst'] ?? true) === false
            && ! ($row['hst_included'] ?? false));
        $commute = collect($hub->items)->first(fn ($row) => str_starts_with((string) ($row['description'] ?? ''), 'Commute'));
        $debris = collect($hub->items)->first(fn ($row) => str_starts_with((string) ($row['description'] ?? ''), 'Debris'));

        $this->assertNotNull($taxable);
        $this->assertNotNull($exempt);
        $this->assertSame(2146.00, (float) $taxable['amount']);
        $this->assertSame(110.00, (float) $exempt['amount']);
        $this->assertNotNull($commute);
        $this->assertSame(100.00, (float) $commute['amount']);
        $this->assertFalse((bool) ($commute['apply_hst'] ?? true));
        $this->assertNotNull($debris);
        $this->assertSame(110.00, (float) $debris['amount']);
        $this->assertFalse((bool) ($debris['apply_hst'] ?? true));
        $this->assertSame($quote->editorGrandTotal(), (float) $quote->grand_total);
        $this->assertPdfClientLinesSumToSavedTotal($quote);
    }

    public function test_kit_line_urls_are_null_not_empty_string_including_catalog(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();
        $blankCatalog = Material::create([
            'name' => 'No URL plank',
            'price_cad' => 4.50,
            'unit' => 'sq ft',
            'is_active' => true,
            'product_url' => '',
            'image_url' => '',
        ]);
        $linkedCatalog = Material::create([
            'name' => 'Linked plank',
            'price_cad' => 2.00,
            'unit' => 'linear ft',
            'is_active' => true,
            'product_url' => 'https://example.test/mdf',
            'image_url' => 'https://example.test/mdf.jpg',
        ]);

        $quote = $this->createSavedQuote('SQ-KEEP-URL', [$this->kitchenWork([$this->inclHstFlooringRow()])]);

        $this->from(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.quote.store'), [
                'layout' => 'room:'.$room->id,
                'quotation_id' => $quote->id,
                'trades' => ['flooring', 'baseboard'],
                'waste' => ['flooring' => 0, 'baseboard' => 0],
                'unit_price' => ['flooring' => 4.50, 'baseboard' => 2.00],
                'catalog_material_id' => [
                    'flooring' => $blankCatalog->id,
                    'baseboard' => $linkedCatalog->id,
                ],
                'client_name' => 'Pat Owner',
                'display_title' => 'Saved kitchen',
            ])->assertRedirect();

        $quote->refresh();
        $after = $quote->resolvedWorks()[0]['materials'];
        $this->assertCount(3, $after);

        $blankLine = $after[1];
        $linkedLine = $after[2];
        $this->assertSame($blankCatalog->id, (int) ($blankLine['catalog_material_id'] ?? 0));
        $this->assertSame($linkedCatalog->id, (int) ($linkedLine['catalog_material_id'] ?? 0));
        $this->assertFalse(array_key_exists('url', $blankLine) && $blankLine['url'] === '');
        $this->assertFalse(array_key_exists('image_url', $blankLine) && $blankLine['image_url'] === '');
        $this->assertSame('https://example.test/mdf', $linkedLine['url'] ?? null);
        $this->assertSame('https://example.test/mdf.jpg', $linkedLine['image_url'] ?? null);

        $emptyIn = [[
            'trade' => 'flooring',
            'label' => 'Flooring',
            'short_name' => 'Forced empty',
            'description' => 'Empty URL catalog line',
            'unit' => 'sq ft',
            'qty' => 1,
            'unit_price' => 1,
            'catalog_material_id' => $blankCatalog->id,
            'apply_hst' => true,
            'url' => '',
            'image_url' => '',
        ]];
        $forced = app(RoomAreaQuoteLineInserter::class)->insert(
            $emptyIn,
            $quote->fresh(),
            null,
            'Pat Owner',
            'Saved kitchen',
            'Kitchen'
        );
        $forcedRow = $forced->resolvedWorks()[0]['materials'];
        $last = $forcedRow[array_key_last($forcedRow)];
        $this->assertFalse(array_key_exists('url', $last) && $last['url'] === '', 'persistMaterial must not store empty-string URLs');
        $this->assertFalse(array_key_exists('image_url', $last) && $last['image_url'] === '', 'persistMaterial must not store empty-string image URLs');
        $this->assertPdfClientLinesSumToSavedTotal($forced);
    }

    public function test_audit_kit_rewrite_command_lists_candidates_and_does_not_write(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Hub mismatch job']);
        $damaged = $this->createSavedQuote('SQ-AUDIT-CMD', [$this->kitchenWork([
            $this->inclHstFlooringRow(),
            [
                'short_name' => '',
                'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                'url' => '',
                'image_url' => '',
                'unit_price' => 250.0,
                'quantity' => 1.0,
                'apply_hst' => true,
                'hst_includes_unit' => false,
                'catalog_material_id' => null,
                'delivery_cad' => 0.0,
                'entry_mode' => 'manual',
                'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
            ],
        ])], [
            'client_project_id' => $project->id,
            'is_draft' => false,
        ]);
        $damaged->grand_total = 1.00;
        $damaged->save();
        ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $damaged->id,
            'title' => 'Hub',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'Q-AUDIT-1',
            'version' => 1,
            'items' => [[
                'description' => 'Labour and material',
                'amount' => 50.00,
                'apply_hst' => true,
            ]],
        ]);

        $beforeQuotes = StandardQuotation::query()->orderBy('id')->get()->toArray();
        $beforeHubs = ClientProjectQuotation::query()->orderBy('id')->get()->toArray();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withoutMockingConsoleOutput();
        $exit = $this->artisan('quotes:audit-kit-rewrite');
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Candidates only', $output);
        $this->assertStringContainsString('SQ-AUDIT-CMD', $output);
        $this->assertStringContainsString('Hub mismatch job', $output);
        $this->assertStringContainsString('empty_string_url', $output);
        $this->assertStringContainsString('electrical_text_as_material', $output);
        $this->assertStringContainsString('saved_total_mismatch', $output);
        $this->assertStringContainsString('hub_amount_mismatch', $output);

        $writes = array_values(array_filter(DB::getQueryLog(), static function (array $query) {
            return (bool) preg_match('/^\s*(insert|update|delete|replace|alter|drop|truncate)/i', $query['query']);
        }));
        DB::disableQueryLog();
        $this->assertSame([], $writes);
        $this->assertSame($beforeQuotes, StandardQuotation::query()->orderBy('id')->get()->toArray());
        $this->assertSame($beforeHubs, ClientProjectQuotation::query()->orderBy('id')->get()->toArray());

        $this->assertSame(0, $this->artisan('quotes:audit-kit-rewrite', ['--since' => '2099-01-01 00:00']));
        $later = Artisan::output();
        $this->assertStringContainsString('Candidates only', $later);
        $this->assertStringContainsString('No candidate Standard quotes', $later);
    }

    private function assertPdfClientLinesSumToSavedTotal(StandardQuotation $quote): void
    {
        $sum = 0.0;
        foreach ($quote->detailedPdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += (float) ($row['amount'] ?? 0);
            $sum += ClientProjectQuotation::lineHstAmount($row);
        }

        $this->assertSame(
            round((float) $quote->grand_total, 2),
            round($sum, 2),
            'PDF/client line amounts must independently sum to the saved total'
        );
    }

    /**
     * @param  list<array<string, mixed>>  $works
     * @param  array<string, mixed>  $extra
     */
    private function createSavedQuote(string $number, array $works, array $extra = []): StandardQuotation
    {
        $contingencyEnabled = (bool) ($extra['contingency_enabled'] ?? false);
        $contingencyPercent = (float) ($extra['contingency_percent'] ?? 0);
        $computed = app(StandardQuotationCalculator::class)->computeWorks(
            $works,
            $contingencyEnabled,
            $contingencyPercent,
            true,
            false,
            0
        );
        $quote = new StandardQuotation(array_merge([
            'quote_number' => $number,
            'client_name' => 'Pat Owner',
            'display_title' => 'Saved kitchen',
            'includes_material' => true,
            'includes_labour' => true,
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 400.0,
            'materials' => $works[0]['materials'],
            'works' => $works,
            'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
            'materials_hst' => $computed['materials_hst'],
            'materials_with_hst' => $computed['materials_with_hst'],
            'labour_total' => $computed['labour_total'],
            'labour_hst' => $computed['labour_hst'],
            'contingency_enabled' => $computed['contingency_enabled'],
            'contingency_percent' => $computed['contingency_percent'],
            'contingency_amount' => $computed['contingency_amount'],
            'contingency_hst' => $computed['contingency_hst'],
            'include_labour_hst' => true,
            'include_commute' => false,
            'commute_amount' => 0,
            'include_debris_removal' => false,
            'debris_removal_amount' => 0,
        ], $extra));
        $quote->grand_total = $quote->editorGrandTotal($computed);
        $quote->save();

        return $quote->fresh();
    }

    /**
     * @param  list<string>  $trades
     * @param  array<string, float>  $prices
     */
    private function postKitLines(RoomAreaRoom $room, StandardQuotation $quote, array $trades, array $prices): void
    {
        $waste = [];
        foreach ($trades as $trade) {
            $waste[$trade] = 0;
        }

        $this->from(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->post(route('admin.utilities.room-area.quote.store'), [
                'layout' => 'room:'.$room->id,
                'quotation_id' => $quote->id,
                'trades' => $trades,
                'waste' => $waste,
                'unit_price' => $prices,
                'client_name' => 'Pat Owner',
                'display_title' => 'Saved kitchen',
            ])->assertRedirect();
    }

    /**
     * @param  list<array<string, mixed>>  $materials
     * @return array<string, mixed>
     */
    private function kitchenWork(array $materials): array
    {
        return [
            'title' => 'Kitchen',
            'materials' => $materials,
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 400.0,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0.0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function inclHstFlooringRow(): array
    {
        return [
            'short_name' => 'LVP',
            'description' => 'Price incl. HST flooring',
            'url' => 'https://example.test/lvp',
            'image_url' => null,
            'unit_price' => 113.0,
            'quantity' => 10.0,
            'apply_hst' => true,
            'hst_includes_unit' => true,
            'catalog_material_id' => null,
            'delivery_cad' => 0.0,
            'entry_mode' => 'manual',
            'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
        ];
    }

    /** @return array<string, mixed> */
    private function electricalRow(): array
    {
        return [
            'short_name' => '',
            'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
            'url' => null,
            'image_url' => '',
            'unit_price' => 250.0,
            'quantity' => 1.0,
            'apply_hst' => true,
            'hst_includes_unit' => false,
            'catalog_material_id' => null,
            'delivery_cad' => 0.0,
            'entry_mode' => 'electrical',
            'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
        ];
    }

    /** @return array<string, mixed> */
    private function blankMaterialRow(): array
    {
        return [
            'short_name' => '',
            'description' => '',
            'url' => null,
            'image_url' => null,
            'unit_price' => '',
            'quantity' => '1',
            'apply_hst' => true,
            'hst_includes_unit' => false,
            'catalog_material_id' => null,
            'delivery_cad' => 0,
            'entry_mode' => 'manual',
            'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
        ];
    }

    /** @return array<string, mixed> */
    private function hstExemptRow(): array
    {
        return [
            'short_name' => 'Cash tile',
            'description' => 'HST-exempt tile',
            'url' => null,
            'image_url' => null,
            'unit_price' => 100.0,
            'quantity' => 1.0,
            'apply_hst' => false,
            'hst_includes_unit' => false,
            'catalog_material_id' => null,
            'delivery_cad' => 0.0,
            'entry_mode' => 'manual',
            'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
        ];
    }

    private function makeRoom(?ClientProject $clientProject = null): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Quote project',
            'client_project_id' => $clientProject?->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
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
                'objects' => [
                    [
                        'id' => 'obj_1',
                        'type' => 'door',
                        'subtype' => 'single',
                        'wall_id' => 'wall_p1_0_1',
                        'width' => 36,
                        'offset' => 12,
                    ],
                    [
                        'id' => 'obj_2',
                        'type' => 'window',
                        'subtype' => 'fixed',
                        'wall_id' => 'wall_p1_1_2',
                        'width' => 48,
                        'offset' => 12,
                        'height' => 36,
                    ],
                    [
                        'id' => 'obj_3',
                        'type' => 'opening',
                        'subtype' => 'archway',
                        'wall_id' => 'wall_p1_2_3',
                        'width' => 36,
                        'offset' => 12,
                    ],
                ],
            ],
        ]);
    }
}
