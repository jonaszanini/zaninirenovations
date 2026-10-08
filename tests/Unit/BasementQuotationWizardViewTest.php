<?php

namespace Tests\Unit;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class BasementQuotationWizardViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['view']->share('errors', new ViewErrorBag);
    }
    public function test_bathroom_wizard_nests_new_wall_and_duct_fields(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['scope_preset'] = 'bathroom';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('bathroom');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'bathroom', $enabledNow))->render();

        $this->assertStringContainsString('id="br-framing-slot-bath"', $html);
        $this->assertStringContainsString('Walls &amp; ducts', $html);
        $this->assertStringContainsString('New wall (linear ft)', $html);
        $this->assertStringContainsString('Work around duct (linear ft)', $html);
        $this->assertMatchesRegularExpression('/data-br-step="framing"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-step="electrical"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-step="plumbing"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('name="partition_lf"', $html);
        $this->assertStringContainsString('name="bulkhead_lf"', $html);
    }

    public function test_full_basement_wizard_keeps_framing_on_its_own_step(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('id="br-framing-slot-step"', $html);
        $this->assertStringContainsString('New wall (linear ft)', $html);
        $this->assertStringContainsString('Work around duct (linear ft)', $html);
        $this->assertDoesNotMatchRegularExpression('/data-br-step="framing"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/id="br-framing-slot-bath"[^>]*\bhidden\b/', $html);
    }

    public function test_demolition_step_has_tear_out_checkboxes(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('What to tear out', $html);
        $this->assertStringContainsString('name="demo_include_flooring"', $html);
        $this->assertStringContainsString('name="demo_include_studs"', $html);
        $this->assertStringContainsString('name="demo_include_drywall"', $html);
        $this->assertStringContainsString('name="demo_include_baseboard"', $html);
        $this->assertStringContainsString('name="demo_studs_lf"', $html);
        $this->assertStringContainsString('name="demo_baseboard_lf"', $html);
        $this->assertStringContainsString('id="br-demo-studs-fields"', $html);
        $this->assertStringContainsString('id="br-demo-base-fields"', $html);
        $this->assertStringContainsString('class="br-demo-types', $html);
        $this->assertStringContainsString('class="admin-form-group br-demo-bins"', $html);
    }

    public function test_wall_wizard_includes_doors_trim_step(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['scope_preset'] = 'wall';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('wall');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'wall', $enabledNow))->render();

        $this->assertDoesNotMatchRegularExpression('/data-br-step="doors_trim"[^>]*\bhidden\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-br-step="framing"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-step="flooring"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('placeholder="Auto"', $html);
        $this->assertMatchesRegularExpression('/data-br-skip-on-wall[^>]*\bhidden\b/', $html);
        $this->assertContains('doors_trim', $enabledNow);
        $this->assertStringContainsString('id="br-wall-primary-slot"', $html);
        $this->assertStringContainsString('Include a door', $html);
        $this->assertStringContainsString('name="partition_lf"', $html);
        $this->assertMatchesRegularExpression('/data-br-area-overview[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-hide-on-wall[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('data-br-overview-mode="wall"', $html);
    }

    public function test_full_basement_keeps_area_on_overview_and_wall_fields_on_framing(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('data-br-overview-mode="area"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-br-area-overview[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-wall-overview[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('id="br-framing-primary-slot"', $html);
        $this->assertStringContainsString('name="partition_lf"', $html);
        $this->assertStringContainsString('Door openings to frame', $html);
    }

    public function test_electrical_and_plumbing_steps_render_priced_lines_and_scope_hint(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('name="electrical_lines[0][description]"', $html);
        $this->assertStringContainsString('name="plumbing_lines[0][description]"', $html);
        $this->assertStringContainsString('qualified electrician', $html);
        $this->assertStringContainsString('qualified plumber', $html);
        $this->assertStringContainsString("Electrician's quote", $html);
        $this->assertStringContainsString("Plumber's quote", $html);
        $this->assertStringNotContainsString('New basement renovation quotation', $html);
        $this->assertStringNotContainsString('licensed', $html);
        $this->assertStringNotContainsString('licence', $html);
        $this->assertStringNotContainsString('name="wiring_runs"', $html);
        $this->assertStringContainsString('name="outlet_qty"', $html);
        $this->assertStringContainsString('name="bath_sink_qty"', $html);
        $this->assertStringContainsString('How many sinks?', $html);
    }

    public function test_work_area_mode_toggle_sits_beside_the_label(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $overview = strstr($html, 'data-br-step="overview"');
        $this->assertNotFalse($overview);
        $overview = strstr($overview, 'br-overview-geom');
        $this->assertNotFalse($overview);
        $overview = substr($overview, 0, (int) strpos($overview, 'data-br-step="demolition"'));

        $primary = strpos($overview, 'br-lw__primary');
        $head = strpos($overview, 'br-lw__head');
        $segment = strpos($overview, 'br-lw-segment');
        $companion = strpos($overview, 'br-lw__companion');

        $this->assertNotFalse($primary);
        $this->assertNotFalse($head);
        $this->assertNotFalse($segment);
        $this->assertNotFalse($companion);
        $this->assertGreaterThan($primary, $head);
        $this->assertGreaterThan($head, $segment);
        $this->assertGreaterThan($segment, $companion);
        $this->assertStringContainsString('id="br-height"', $overview);
    }

    public function test_overview_step_has_include_helper_checkbox_and_hidden_rate(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('name="include_helper"', $html);
        $this->assertStringContainsString('Include a helper', $html);
        $this->assertStringContainsString('name="material_waste_percent"', $html);
        $this->assertStringContainsString('name="drywall_sheet_size"', $html);
        $this->assertStringContainsString('name="stud_spacing_in"', $html);
        $this->assertStringContainsString('name="drywall_door_openings"', $html);
        $this->assertStringContainsString('name="include_building_permit"', $html);
        $this->assertStringContainsString('id="br-helper-rate-wrap"', $html);
        $this->assertMatchesRegularExpression('/id="br-helper-rate-wrap"[^>]*\bhidden\b/', $html);
    }

    public function test_finishing_step_uses_wrapping_rows(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('class="br-finishing"', $html);
        $this->assertStringContainsString('class="br-finishing-row"', $html);
        $this->assertStringContainsString('br-finishing-row--checks', $html);
        $this->assertStringContainsString('br-finishing-row--permit', $html);
        $this->assertStringContainsString('id="br-permit-fields"', $html);
        $this->assertStringContainsString('id="br-hw"', $html);
        $this->assertStringContainsString('name="include_cleaning"', $html);
        $this->assertStringContainsString('name="include_touchups"', $html);
        $this->assertStringContainsString('name="include_building_permit"', $html);
        $this->assertStringContainsString('name="building_permit_amount"', $html);
        $this->assertStringNotContainsString('data-cp-money-entry="direct"', $html);
        $this->assertStringNotContainsString('br-finishing-grid', $html);

        $finish = strstr($html, 'data-br-step="finishing"');
        $this->assertNotFalse($finish);
        $hw = strpos($finish, 'id="br-hw"');
        $clean = strpos($finish, 'name="include_cleaning"');
        $touch = strpos($finish, 'name="include_touchups"');
        $permit = strpos($finish, 'name="include_building_permit"');
        $amount = strpos($finish, 'id="br-permit-amount"');
        $this->assertNotFalse($hw);
        $this->assertGreaterThan($hw, $clean);
        $this->assertGreaterThan($clean, $touch);
        $this->assertGreaterThan($touch, $permit);
        $this->assertGreaterThan($permit, $amount);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<string>  $enabledNow
     * @return array<string, mixed>
     */
    private function wizardData(array $inputs, string $preset, array $enabledNow): array
    {
        return [
            'inputs' => $inputs,
            'settings' => new BasementSettings([
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ]),
            'isEdit' => false,
            'scopePreset' => $preset,
            'enabledNow' => $enabledNow,
            'enabledCount' => count($enabledNow),
            'filledKeys' => [],
        ];
    }
}
