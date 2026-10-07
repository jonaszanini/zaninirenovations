<?php

namespace Tests\Feature\Admin;

use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationCutListImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_is_redirected_from_search_and_import(): void
    {
        $this->get(route('admin.utilities.cut-list.quotations.search'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.utilities.cut-list.import', ['id' => 'low_deck:1']))
            ->assertRedirect(route('login'));
        $this->get(route('admin.utilities.lumber-board'))
            ->assertRedirect(route('login'));
    }

    public function test_search_returns_recent_quotations_matching_client_or_number(): void
    {
        $this->actingAsAdmin();
        $older = $this->seedDeckQuote();
        LowDeckQuotation::query()->whereKey($older->id)->update([
            'updated_at' => now()->subMinute(),
        ]);
        LowDeckQuotation::query()->create([
            'quote_number' => 'LDQ-2026-1111',
            'client_name' => 'Other Person',
            'width_ft' => 8,
            'length_ft' => 8,
            'calculation' => ['packing' => []],
        ]);

        $this->getJson(route('admin.utilities.cut-list.quotations.search', ['q' => 'Pat']))
            ->assertOk()
            ->assertJsonFragment(['quote_number' => 'LDQ-2026-9001'])
            ->assertJsonMissing(['quote_number' => 'LDQ-2026-1111']);

        $recent = $this->getJson(route('admin.utilities.cut-list.quotations.search'));
        $recent->assertOk();
        $ids = collect($recent->json())->pluck('quote_number')->all();
        $this->assertContains('LDQ-2026-9001', $ids);
        $this->assertContains('LDQ-2026-1111', $ids);
        $this->assertSame('LDQ-2026-1111', $recent->json('0.quote_number'));
    }

    public function test_import_returns_lumber_parts_from_seeded_deck_quotation(): void
    {
        $this->actingAsAdmin();
        $quote = $this->seedDeckQuote();

        $response = $this->getJson(route('admin.utilities.cut-list.import', [
            'id' => 'low_deck:'.$quote->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('type', 'low_deck')
            ->assertJsonPath('quote_number', 'LDQ-2026-9001')
            ->assertJsonPath('has_parts', true);

        $lumber = $response->json('lumber');
        $this->assertNotEmpty($lumber);
        $joists = collect($lumber)->firstWhere('material_key', '2x6');
        $this->assertNotNull($joists);
        $this->assertNotEmpty($joists['pieces']);
        $this->assertSame(2, (int) collect($joists['pieces'])->sum('qty'));
        $this->assertTrue(collect($joists['stocks'])->contains(fn ($s) => (float) $s['lengthVal'] === 12.0));
    }

    public function test_import_returns_millwork_sheet_parts(): void
    {
        $this->actingAsAdmin();
        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-2026-9001',
            'client_name' => 'Millwork Client',
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'edge_banding' => false,
                    'finish' => 'none',
                    'fastener' => 'screws',
                    'sets' => [
                        ['qty' => 2, 'length' => 24, 'depth' => 12, 'unit' => 'in'],
                    ],
                ]],
            ],
            'calculation' => [
                'sheet' => [
                    'name' => '3/4 plywood',
                    'width_in' => 48,
                    'length_in' => 96,
                    'thickness_in' => 0.75,
                    'unit_price' => 72,
                ],
            ],
            'final_quote_total' => 0,
        ]);

        $response = $this->getJson(route('admin.utilities.cut-list.import', [
            'id' => 'millwork:'.$quote->id,
        ]));

        $response->assertOk()->assertJsonPath('has_parts', true);
        $sheets = $response->json('sheets');
        $this->assertNotEmpty($sheets);
        $parts = $sheets[0]['parts'] ?? [];
        $this->assertNotEmpty($parts);
        $this->assertGreaterThanOrEqual(2, (int) collect($parts)->sum('qty'));
    }

    public function test_lumber_page_renders_import_picker_and_offcut_toggle(): void
    {
        $this->actingAsAdmin();
        $this->get(route('admin.utilities.lumber-board'))
            ->assertOk()
            ->assertSee('Import from quotation', false)
            ->assertSee('Use offcuts first', false)
            ->assertSee('Save leftovers to inventory', false)
            ->assertDontSee('data-cp-money-entry="direct"', false);

        $js = file_get_contents(public_path('js/admin-utilities-lumber-board.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('cp-money-input', $js);
        $this->assertStringNotContainsString('data-cp-money-entry="direct"', $js);
    }

    private function seedDeckQuote(): LowDeckQuotation
    {
        return LowDeckQuotation::query()->create([
            'quote_number' => 'LDQ-2026-9001',
            'client_name' => 'Pat Deck',
            'width_ft' => 12,
            'length_ft' => 16,
            'calculation' => [
                'packing' => [
                    'joists' => [
                        'bins' => [[
                            'key' => 'joist_2x6_12',
                            'length_ft' => 12,
                            'price' => 14.5,
                            'pieces_ft' => [10, 8],
                            'waste_ft' => 0,
                        ]],
                    ],
                    'boards' => ['bins' => []],
                    'posts' => ['bins' => []],
                ],
            ],
        ]);
    }
}
