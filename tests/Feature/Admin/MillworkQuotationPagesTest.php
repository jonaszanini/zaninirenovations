<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\MillworkQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class MillworkQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_hub_and_wizard_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Custom Millwork');

        $this->get(route('admin.quotations.millwork.index'))
            ->assertOk()
            ->assertSee('New millwork quotation');

        $this->get(route('admin.quotations.millwork.create'))
            ->assertOk()
            ->assertSee('1 of 4')
            ->assertSee('Simple shelf')
            ->assertSee('Floating shelf')
            ->assertSee('Kallax shelf')
            ->assertSee('Cabinet')
            ->assertSee('Cabinet door only')
            ->assertSee('Bench seating')
            ->assertSee('Edge banding')
            ->assertSee('>In</option>', false)
            ->assertSee('>Ft</option>', false)
            ->assertSee('Cut plan')
            ->assertSee('Print cut list')
            ->assertDontSee('mw-cut-steps', false)
            ->assertSee('Live breakdown')
            ->assertSee('Create automatically from quotation')
            ->assertSee('Vanity')
            ->assertSee('Closet system')
            ->assertSee('Wainscoting')
            ->assertSee('Mantel')
            ->assertSee('Trim / casing / base / crown')
            ->assertSee('data-mw-show="cabinet cabinet_door vanity closet bench-drawers"', false)
            ->assertSee('data-mw-hw="cabinet-family"', false)
            ->assertSee('data-mw-mat="back"', false)
            ->assertSee('ws_ply_14_48x96', false)
            ->assertSee('data-mw-mat="edge-banding"', false)
            ->assertSee('data-mw-mat="paint"', false)
            ->assertSee('data-mw-mat="stain"', false)
            ->assertSee('id="mw-hinge"', false)
            ->assertSee('Waste % (linear)')
            ->assertSee('Delivery')
            ->assertSee('How many')
            ->assertSee('More options')
            ->assertSee('Labour')
            ->assertSee('mw-totals-bar', false)
            ->assertSee('Clear coat');

        $this->get(route('admin.quotations.millwork.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Millwork-only catalog')
            ->assertSee('Wood sheets')
            ->assertSee('mw-catalog-table')
            ->assertSee('mw-col-price')
            ->assertSee('mw-col-dims')
            ->assertSee('mw-col-thickness')
            ->assertSee('mw-col-kind')
            ->assertSee('Hinges')
            ->assertSee('Paint')
            ->assertSee('Stain')
            ->assertSee('Edge banding')
            ->assertSee('Coverage (LF)')
            ->assertSee('mw-col-coverage-lf')
            ->assertSee('Blade kerf')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Catalog</button>', false)
            ->assertSee('>Cut settings</button>', false)
            ->assertSee('Save configuration');
    }

    public function test_project_context_sits_between_price_configuration_and_next(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Need floating shelves in the mudroom.',
        ]);

        $html = $this->get(route('admin.quotations.millwork.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Need floating shelves in the mudroom.')
            ->getContent();

        $price = strpos($html, 'Price configuration');
        $context = strpos($html, 'Project Context');
        $next = strpos($html, '>Next</button>');
        $live = strpos($html, 'Live breakdown');

        $this->assertNotFalse($price);
        $this->assertNotFalse($context);
        $this->assertNotFalse($next);
        $this->assertNotFalse($live);
        $this->assertLessThan($price, $context);
        $this->assertLessThan($next, $context);
        $this->assertLessThan($live, $next);
    }

    public function test_preview_returns_cut_plan_and_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.millwork.preview'), [
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => 1,
                'sets' => [
                    ['qty' => 2, 'length' => 41, 'depth' => 35, 'unit' => 'in'],
                ],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => 0,
        ])->assertOk()
            ->assertJsonPath('cut_plan.sheet_count', 1)
            ->assertJsonPath('cut_plan.kerf_in', 0.125)
            ->assertJsonStructure([
                'summary' => ['labor_pre_hst', 'materials_pre_hst', 'grand_total'],
                'cut_plan' => ['layouts'],
            ]);
    }

    public function test_store_draft_and_edit_preview(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.millwork.store'), [
            'save_intent' => 'draft',
            'client_name' => 'Millwork Client',
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
            ]],
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ]);

        $quote = MillworkQuotation::query()->where('client_name', 'Millwork Client')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);

        $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk()
            ->assertSee($quote->quote_number);

        $this->post(route('admin.quotations.millwork.calculate-existing', $quote), [
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 2, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.piece_count', 1);

        $this->get(route('admin.quotations.millwork.cut-list', $quote))
            ->assertOk()
            ->assertSee('Details')
            ->assertSee('Sheet 1:')
            ->assertSee($quote->quote_number)
            ->assertDontSee('Rip at')
            ->assertDontSee('mw-cut-steps', false);

        $this->post(route('admin.quotations.millwork.calculate-existing', $quote), [
            '_method' => 'PUT',
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 12, 'depth' => 8, 'unit' => 'in']],
            ]],
        ])->assertStatus(405);
    }

    public function test_materials_and_finish_fields_conditional_visibility(): void
    {
        $this->actingAsAdmin();

        // 1. Fresh create page: default shelf has finish: 'none', include_back: false, edge_banding: true
        $res = $this->get(route('admin.quotations.millwork.create'))
            ->assertOk();
        $html = $res->getContent();

        $this->assertMatchesRegularExpression('/data-mw-mat="back"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/data-mw-mat="paint"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/data-mw-mat="stain"\s+hidden/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="edge-banding"\s+hidden/', $html);

        // 2. Draft quotation with kallax, include_back: 1, finish: paint, edge_banding: 0
        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-COND-1',
            'client_name' => 'Kallax Client',
            'inputs' => [
                'pieces' => [[
                    'type' => 'kallax',
                    'qty' => 1,
                    'width' => 35,
                    'height' => 15,
                    'depth' => 15,
                    'columns' => 3,
                    'rows' => 1,
                    'finish' => 'paint',
                    'edge_banding' => 0,
                    'include_back' => 1,
                    'unit' => 'in',
                ]],
            ],
            'calculation' => [],
            'status' => 'draft',
        ]);

        $resEdit = $this->get(route('admin.quotations.millwork.edit', $quote))
            ->assertOk();
        $htmlEdit = $resEdit->getContent();

        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="back"\s+hidden/', $htmlEdit);
        $this->assertDoesNotMatchRegularExpression('/data-mw-mat="paint"\s+hidden/', $htmlEdit);
        $this->assertMatchesRegularExpression('/data-mw-mat="stain"\s+hidden/', $htmlEdit);
        $this->assertMatchesRegularExpression('/data-mw-mat="edge-banding"\s+hidden/', $htmlEdit);
    }

    public function test_publish_requires_dimensions(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.millwork.create'))
            ->post(route('admin.quotations.millwork.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Empty Millwork',
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => '', 'depth' => '', 'unit' => 'in']],
                ]],
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'ask_reply_to_accept' => '0',
            ])->assertRedirect()
            ->assertSessionHasErrors('calculation');
    }
}
