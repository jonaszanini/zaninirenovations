<?php

namespace Tests\Unit\Views;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminInfoTipIdIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['view']->share('errors', new ViewErrorBag);
    }

    public function test_info_tip_include_does_not_inherit_parent_id_or_label(): void
    {
        $html = Blade::render(
            '@php $id = "br-area"; $label = "Last loop item"; @endphp'
            . '@include("admin.partials.info_tip", ["text" => "Room area help"])'
            . '<input id="br-area" type="number">'
        );

        $this->assertStringContainsString('Room area help', $html);
        $this->assertStringNotContainsString('About Last loop item', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="br-area"[^>]*admin-info-tip__bubble|admin-info-tip__bubble[^>]*id="br-area"/',
            $html
        );
        $this->assertInputIdResolvesToControl($html, ['br-area']);
    }

    public function test_basement_wizard_qty_lw_ids_resolve_to_inputs(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', [
            'inputs' => $inputs,
            'settings' => new BasementSettings([
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ]),
            'isEdit' => false,
            'scopePreset' => 'full',
            'enabledNow' => $enabledNow,
            'enabledCount' => count($enabledNow),
            'filledKeys' => [],
        ])->render();

        $this->assertNoTipStealsInputIds($html);
        $this->assertInputIdResolvesToControl($html, ['br-area', 'br-dw-wall']);
        $this->assertStringContainsString('admin-info-tip', $html);
    }

    public function test_form_field_with_help_does_not_reuse_the_input_id(): void
    {
        $html = view('admin.partials.form_field', [
            'label' => 'Store SKU',
            'name' => 'store_sku',
            'id' => 'admin-field-store_sku',
            'help' => 'Needed only to sync Home Depot prices.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertUniqueIds($html);
        $this->assertInputIdResolvesToControl($html, ['admin-field-store_sku']);
        $this->assertStringContainsString('aria-label="About Store SKU"', $html);
        $this->assertMatchesRegularExpression('/<\/label>\s*<span class="admin-info-tip-wrap"/', $html);
    }

    public function test_materials_config_bulk_sync_checkbox_keeps_its_input_id(): void
    {
        $html = view('admin.partials.checkbox', [
            'name' => 'sync_prices',
            'id' => 'mat-bulk-sync',
            'label' => 'Sync prices after import',
            'info' => 'When checked, fetches name and price via SerpApi.',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertUniqueIds($html);
        $this->assertInputIdResolvesToControl($html, ['mat-bulk-sync']);
        $this->assertStringContainsString('aria-label="About Sync prices after import"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<label[^>]*>[\s\S]*admin-info-tip-wrap[\s\S]*<\/label>/',
            $html
        );
    }

    public function test_qty_lw_partial_does_not_put_the_bubble_on_the_input_id(): void
    {
        $html = view('admin.quotations.basement._qty_lw', [
            'name' => 'area_sf',
            'id' => 'br-area',
            'label' => 'Finished area',
            'value' => 100,
            'modeName' => 'qty_dims[area_sf][mode]',
            'lengthName' => 'qty_dims[area_sf][length_ft]',
            'widthName' => 'qty_dims[area_sf][width_ft]',
            'info' => 'Room area from the imported layout.',
        ])->render();

        $this->assertUniqueIds($html);
        $this->assertInputIdResolvesToControl($html, ['br-area']);
        $this->assertStringContainsString('aria-label="About Finished area"', $html);
        $this->assertMatchesRegularExpression(
            '/<label for="br-area"[^>]*>[\s\S]*?<\/label>\s*<span class="admin-info-tip-wrap"/',
            $html
        );
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertInputIdResolvesToControl(string $html, array $ids): void
    {
        foreach ($ids as $id) {
            $quoted = preg_quote($id, '/');
            $this->assertMatchesRegularExpression(
                '/<(input|textarea|select)\b[^>]*\bid="'.$quoted.'"/',
                $html,
                "Expected {$id} to belong to an input, textarea, or select."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<span\b[^>]*\bid="'.$quoted.'"[^>]*class="[^"]*admin-info-tip__bubble/',
                $html,
                "Bubble must not use input id {$id}."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<span\b[^>]*class="[^"]*admin-info-tip__bubble[^"]*"[^>]*\bid="'.$quoted.'"/',
                $html,
                "Bubble must not use input id {$id}."
            );
        }
    }

    private function assertUniqueIds(string $html): void
    {
        preg_match_all('/\sid="([^"]+)"/', $html, $matches);
        $counts = array_count_values($matches[1]);
        foreach ($counts as $id => $count) {
            $this->assertSame(1, $count, "Duplicate id {$id} found {$count} times.");
        }
        $this->assertNoTipStealsInputIds($html);
    }

    private function assertNoTipStealsInputIds(string $html): void
    {
        preg_match_all('/<(input|textarea|select)\b[^>]*\bid="([^"]+)"/i', $html, $inputs);
        preg_match_all('/<span\b[^>]*id="([^"]+)"[^>]*class="[^"]*admin-info-tip__bubble/', $html, $bubblesA);
        preg_match_all('/<span\b[^>]*class="[^"]*admin-info-tip__bubble[^"]*"[^>]*id="([^"]+)"/', $html, $bubblesB);
        $inputIds = $inputs[2];
        $bubbleIds = array_values(array_filter(array_merge($bubblesA[1], $bubblesB[1])));
        $overlap = array_values(array_intersect($inputIds, $bubbleIds));
        $this->assertSame([], $overlap, 'Tip bubbles collided with input ids: '.implode(', ', $overlap));
        foreach (array_count_values($bubbleIds) as $id => $count) {
            $this->assertSame(1, $count, "Duplicate tip bubble id {$id}.");
        }
    }
}
