<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationStickyTotalsBarTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @dataProvider quotationCreateRouteProvider
     */
    public function test_sticky_totals_bar_appears_on_every_quotation_create_page(string $routeName): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route($routeName));
        $response->assertOk();

        // 1. Shared sticky totals bar and spacer
        $response->assertSee('data-quote-totals-bar', false);
        $response->assertSee('quote-totals-bar-spacer', false);

        // 2. Navigation
        $response->assertSee('data-quote-save-btn', false);

        // 3. Live figures
        $response->assertSee('data-quote-materials', false);
        $response->assertSee('data-quote-labour', false);
        $response->assertSee('data-quote-hst', false);
        $response->assertSee('data-quote-grand', false);

        // 4. Send to client button
        $response->assertSee('data-quote-send-btn', false);
        $response->assertSee('Send to client');

        // 5. Accessibility attributes
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('data-quote-live-announcer', false);
    }

    public function quotationCreateRouteProvider(): array
    {
        return [
            'basement' => ['admin.quotations.basement.create'],
            'fence' => ['admin.quotations.fence.create'],
            'flooring' => ['admin.quotations.flooring.create'],
            'low_deck' => ['admin.quotations.low-deck.create'],
            'millwork' => ['admin.quotations.millwork.create'],
            'painting' => ['admin.quotations.painting.create'],
            'paving' => ['admin.quotations.paving.create'],
            'shed' => ['admin.quotations.shed.create'],
            'stairs' => ['admin.quotations.stairs.create'],
            'standard' => ['admin.quotations.standard.create'],
            'tile' => ['admin.quotations.tile.create'],
            'trimming' => ['admin.quotations.trimming.create'],
        ];
    }

    public function test_sticky_totals_bar_figures_match_breakdown_on_edit_screens(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Jane Homeowner',
            'client_email' => 'jane@example.test',
        ]);

        // 1. Standard Quotation
        $standard = StandardQuotation::create([
            'quote_number' => 'SQ-2026-9901',
            'client_name' => 'Jane Homeowner',
            'client_project_id' => $project->id,
            'materials' => [
                ['description' => 'Lumber', 'quantity' => 10, 'unit_price' => 50, 'total' => 500],
            ],
            'grand_total' => 1130.00,
        ]);
        $res = $this->get(route('admin.quotations.standard.edit', $standard));
        $res->assertOk();
        $res->assertSee('data-quote-totals-bar', false);
        $res->assertSee('CA$1,130.00');

        // 2. Fence Quotation
        $fence = FenceQuotation::create([
            'quote_number' => 'FQ-2026-9902',
            'client_name' => 'Jane Homeowner',
            'client_project_id' => $project->id,
            'sides' => [['length_ft' => 50]],
            'grand_total' => 2260.00,
            'calculation' => [
                'materials_total' => 1000.00,
                'labor_total' => 1000.00,
                'hst' => 260.00,
                'grand_total' => 2260.00,
            ],
        ]);
        $res = $this->get(route('admin.quotations.fence.edit', $fence));
        $res->assertOk();
        $res->assertSee('data-quote-totals-bar', false);
        $res->assertSee('CA$2,260.00');

        // 3. Basement Quotation
        $basement = BasementQuotation::create([
            'quote_number' => 'BQ-2026-9903',
            'client_name' => 'Jane Homeowner',
            'client_project_id' => $project->id,
            'inputs' => ['area_sqft' => 500],
            'grand_total' => 3390.00,
            'calculation' => [
                'materials_pre_hst' => 1500.00,
                'labor_pre_hst' => 1500.00,
                'hst' => 390.00,
                'grand_total' => 3390.00,
            ],
        ]);
        $res = $this->get(route('admin.quotations.basement.edit', $basement));
        $res->assertOk();
        $res->assertSee('data-quote-totals-bar', false);
        $res->assertSee('data-quote-grand', false);
        $res->assertSee('data-quote-materials', false);
        $res->assertSee('data-quote-labour', false);

        // 4. Millwork Quotation
        $millwork = MillworkQuotation::create([
            'quote_number' => 'MQ-2026-9904',
            'client_name' => 'Jane Homeowner',
            'client_project_id' => $project->id,
            'inputs' => ['sections' => []],
            'grand_total' => 4520.00,
            'calculation' => [
                'materials_pre_hst' => 2000.00,
                'labor_pre_hst' => 2000.00,
                'hst' => 520.00,
                'grand_total' => 4520.00,
            ],
        ]);
        $res = $this->get(route('admin.quotations.millwork.edit', $millwork));
        $res->assertOk();
        $res->assertSee('data-quote-totals-bar', false);
        $res->assertSee('data-quote-grand', false);
        $res->assertSee('data-quote-materials', false);
        $res->assertSee('data-quote-labour', false);
    }
}
