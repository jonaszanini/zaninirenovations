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

        // 5. Accessibility attributes & Save button in unsaved alert
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('data-quote-live-announcer', false);
        $response->assertSee('data-modal-save-btn', false);
    }

    public function test_css_enforces_hidden_panel_and_640px_mobile_breakpoint(): void
    {
        $css = file_get_contents(public_path('css/quotation-totals-bar.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.quote-totals-mobile-panel[hidden]', $css);
        $this->assertStringContainsString('display: none !important;', $css);
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringNotContainsString('@media (max-width: 768px)', $css);

        $millworkCss = file_get_contents(public_path('css/admin-millwork.css'));
        $this->assertNotFalse($millworkCss);
        $this->assertStringNotContainsString('.mw-totals-bar {', $millworkCss);
    }

    public function test_paving_bar_breakdown_matches_pdf_hst_and_adds_up_to_grand_total(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Paving Client',
            'client_email' => 'paving@example.test',
        ]);

        // Scenario where internal materials+fixed HST ($319.88) + labour HST ($156.00) = $475.88,
        // while the final quote total includes profit margin so the client PDF HST is $480.78.
        $paving = PavingQuotation::create([
            'quote_number' => 'PV-2026-9905',
            'client_name' => 'Paving Client',
            'client_project_id' => $project->id,
            'inputs' => ['stone_area_sq_ft' => 100, 'excavation_depth_in' => 8],
            'final_quote_total' => 4179.08,
            'commute_amount' => 0,
            'debris_removal_amount' => 0,
            'calculation' => [
                'inputs' => ['hst_rate' => 13],
                'materials' => ['subtotal_before_tax' => 1960.62, 'with_tax' => 2215.50],
                'fixed_costs' => ['subtotal_before_tax' => 500.00, 'with_tax' => 565.00],
                'labour' => ['total' => 1200.00],
                'summary' => [
                    'hst_on_materials_and_fixed' => 319.88,
                    'final_quote_total' => 4179.08,
                ],
            ],
        ]);

        $breakdown = \App\Support\QuotationTotalsBarBreakdown::forPaving($paving, $paving->calculation);

        // 1. HST is $480.78 (matching PDF pre-tax split), NOT $475.88
        $this->assertSame(480.78, $breakdown['hst']);
        $this->assertSame(2498.30, $breakdown['materials']);
        $this->assertSame(1200.00, $breakdown['labour']);
        $this->assertSame(0.00, $breakdown['extras']);

        // 2. Lines add up to the grand total to the cent
        $sum = round($breakdown['materials'] + $breakdown['labour'] + $breakdown['extras'] + $breakdown['hst'], 2);
        $this->assertSame($breakdown['grand'], $sum);

        // 3. Edit screen renders the unified HST ($480.78) on the totals bar
        $res = $this->get(route('admin.quotations.paving.edit', $paving));
        $res->assertOk();
        $res->assertSee('CA$480.78');
        $res->assertDontSee('CA$475.88');
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
