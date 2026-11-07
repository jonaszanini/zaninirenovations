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
        $this->assertStringContainsString('New wall (lin. ft)', $html);
        $this->assertStringContainsString('Duct boxing (lin. ft)', $html);
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
        $this->assertStringContainsString('New wall (lin. ft)', $html);
        $this->assertStringContainsString('Duct boxing (lin. ft)', $html);
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
        $this->assertStringContainsString('Doors to frame', $html);
    }

    public function test_layout_import_lives_on_overview_and_fills_all_destination_fields(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $overview = strstr($html, 'data-br-step="overview"');
        $this->assertNotFalse($overview);
        $overview = substr($overview, 0, (int) strpos($overview, 'data-br-step="demolition"'));
        $this->assertStringContainsString('data-br-layout-picker', $overview);
        $this->assertStringContainsString('Import layout', $overview);
        $this->assertSame(1, substr_count($html, 'id="br-layout-import"'));
        $this->assertSame(1, substr_count($html, 'id="br-perimeter-lf"'));

        $drywall = strstr($html, 'data-br-step="drywall"');
        $this->assertNotFalse($drywall);
        $drywall = substr($drywall, 0, (int) strpos($drywall, 'data-br-step="flooring"'));
        $this->assertStringNotContainsString('data-br-layout-picker', $drywall);

        foreach ([
            'br-area', 'br-height', 'br-rooms', 'br-partition', 'br-bulkhead', 'br-frame-doors',
            'br-ins-wall-sf', 'br-ins-ceil-sf', 'br-ceil-sf', 'br-dw-wall',
            'br-dw-door-open', 'br-dw-window-open', 'br-dw-open', 'br-fl-sf', 'br-doors',
            'br-base', 'br-casing', 'br-windows', 'br-p-wall', 'br-p-ceil', 'br-p-trim',
        ] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
        $this->assertStringContainsString('Does not change rates', $html);
        $this->assertStringContainsString('use Import layout on Overview', $html);
        $this->assertStringNotContainsString('on the Drywall step', $html);
        $this->assertStringNotContainsString('licensed', $html);
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
        $this->assertStringContainsString(e("Electrician's quote"), $html);
        $this->assertStringContainsString(e("Plumber's quote"), $html);
        $this->assertStringNotContainsString('New basement renovation quotation', $html);
        $this->assertStringNotContainsString('licensed', $html);
        $this->assertStringNotContainsString('licence', $html);
        $this->assertStringNotContainsString('name="wiring_runs"', $html);
        $this->assertStringContainsString('name="outlet_qty"', $html);
        $this->assertStringContainsString('name="bath_sink_qty"', $html);
        $this->assertStringContainsString('How many sinks?', $html);
        $this->assertStringContainsString('>Pot lights</span>', $html);
        $this->assertStringContainsString('Smoke / CO', $html);
        $this->assertStringContainsString('Headers / beams', $html);
        $this->assertStringContainsString('br-field--phone-wide', $html);
    }

    public function test_qty_inputs_use_decimal_html5_step_not_wizard_section_index(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        foreach (['br-base', 'br-shoe', 'br-p-trim'] as $id) {
            $this->assertMatchesRegularExpression(
                '/<input(?=[^>]*\bid="'.preg_quote($id, '/').'")(?=[^>]*\bstep="0\.01")/',
                $html
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<input(?=[^>]*\bid="'.preg_quote($id, '/').'")(?=[^>]*\bstep="(?:10|11)")/',
                $html
            );
        }
        $this->assertStringContainsString('$wizardStep', file_get_contents(resource_path('views/admin/quotations/basement/_wizard_sections.blade.php')));
        $this->assertDoesNotMatchRegularExpression(
            '/\$step\b/',
            file_get_contents(resource_path('views/admin/quotations/basement/_wizard_sections.blade.php'))
        );
    }

    public function test_trade_checkboxes_post_section_keys_not_one(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        foreach (array_keys(BasementQuotationCalculator::tradeSections()) as $tradeKey) {
            $this->assertMatchesRegularExpression(
                '/name="enabled_sections\[]"[^>]*value="'.preg_quote($tradeKey, '/').'"[^>]*data-br-enabled-section="'.preg_quote($tradeKey, '/').'"/',
                $html
            );
        }
        $this->assertDoesNotMatchRegularExpression(
            '/name="enabled_sections\[]"[^>]*value="1"/',
            $html
        );
        $this->assertStringContainsString('name="enabled_sections[]" value="overview"', $html);
    }

    public function test_flooring_step_offers_allowed_types_without_carpet(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('value="hardwood"', $html);
        $this->assertStringContainsString('value="engineered"', $html);
        $this->assertStringContainsString('value="lvp"', $html);
        $this->assertStringContainsString('value="laminate"', $html);
        $this->assertStringContainsString('value="tile"', $html);
        $this->assertStringNotContainsString('value="carpet"', $html);
        $this->assertStringNotContainsString('>Carpet</option>', $html);
        $this->assertStringContainsString('Underlayment (LVP / laminate)', $html);
        $this->assertStringNotContainsString('Underlayment (LVP / laminate / carpet)', $html);
        $this->assertStringContainsString('Foam or cork under LVP or laminate.', $html);
        $this->assertStringNotContainsString('laminate, or carpet', $html);
        $this->assertStringContainsString('id="br-hardwood-caution"', $html);
        $this->assertMatchesRegularExpression(
            '/id="br-hardwood-caution"[^>]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Basement humidity caution for solid hardwood', $html);
        $this->assertStringContainsString('slab moisture', $html);
    }

    public function test_hardwood_flooring_shows_basement_humidity_caution_with_info_icon(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['flooring_type'] = 'hardwood';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('value="hardwood"', $html);
        $this->assertStringContainsString('id="br-hardwood-caution"', $html);
        $this->assertStringContainsString('Basement humidity caution for solid hardwood', $html);
        $this->assertTrue((bool) preg_match('/<p class="br-hardwood-caution"[^>]*>/', $html, $match));
        $this->assertStringNotContainsString('hidden', $match[0]);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('does not change the quoted product specification', $html);
    }

    public function test_engineered_wood_does_not_show_solid_hardwood_humidity_caution(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['flooring_type'] = 'engineered';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('value="engineered"', $html);
        $this->assertMatchesRegularExpression(
            '/id="br-hardwood-caution"[^>]*\bhidden\b/',
            $html
        );
    }

    public function test_persisted_custom_scope_is_marked_dirty_so_preview_cannot_wipe_it(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['client_scope_included'] = 'Custom included list from last save.';
        $inputs['client_scope_not_included'] = 'Custom not included.';
        $inputs['client_scope_assumptions'] = 'Custom assumptions.';
        $inputs['client_scope_allowances'] = 'Custom allowances.';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('Custom included list from last save.', $html);
        $this->assertStringContainsString('Custom not included.', $html);
        $this->assertStringContainsString('Custom assumptions.', $html);
        $this->assertStringContainsString('Custom allowances.', $html);
        foreach (['client_scope_included', 'client_scope_not_included', 'client_scope_assumptions', 'client_scope_allowances'] as $name) {
            $this->assertMatchesRegularExpression(
                '/<textarea[^>]*name="'.preg_quote($name, '/').'"[^>]*data-br-scope-dirty="1"/s',
                $html
            );
            $this->assertMatchesRegularExpression(
                '/<textarea[^>]*name="'.preg_quote($name, '/').'"[^>]*data-br-scope-persisted="1"/s',
                $html
            );
        }
    }

    public function test_empty_create_scope_fields_are_not_marked_dirty(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertDoesNotMatchRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-dirty="1"/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-persisted="1"/s',
            $html
        );
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
        $this->assertStringContainsString('name="include_bcin_drawings"', $html);
        $this->assertStringContainsString('name="bcin_drawings_amount"', $html);
        $this->assertStringContainsString('City building permit — optional extra-charge service', $html);
        $this->assertStringContainsString('BCIN drawings — optional extra-charge service', $html);
        $this->assertStringContainsString('data-br-step="client_scope"', $html);
        $this->assertStringContainsString('name="contractor_fee_percent"', $html);
        $this->assertStringContainsString('name="client_scope_included"', $html);
        $this->assertStringContainsString('name="flooring_waste_percent"', $html);
        $this->assertStringNotContainsString('name="selected_value_tier"', $html);
        $this->assertStringNotContainsString('value_package_notes', $html);
        $this->assertStringNotContainsString('Invest —', $html);
        $this->assertStringContainsString('municipal building-permit fee', $html);
        $this->assertStringContainsString('BCIN qualified designer is not the same credential as a professional engineer', $html);
        $this->assertStringContainsString('engineer signing when required', $html);
        $this->assertStringContainsString('data-cp-money-entry="direct"', $html);
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

    public function test_new_quote_hides_duplicate_drywall_ceiling_field(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('id="br-ceil-sf"', $html);
        $this->assertStringContainsString('name="ceiling_sf"', $html);
        $this->assertStringContainsString('One ceiling area for this quote', $html);
        $this->assertStringNotContainsString('data-br-legacy-ceiling-notice', $html);
        $this->assertStringNotContainsString('Ceiling drywall (sq ft)', $html);
        $this->assertStringNotContainsString('id="br-dw-ceil"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<input(?=[^>]*\bid="br-ceil-sf")(?=[^>]*\bdisabled\b)/',
            $html
        );
    }

    public function test_legacy_split_ceiling_quote_shows_saved_hang_notice_not_a_second_input(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['ceiling_sf'] = 1500;
        $inputs['drywall_ceiling_sf'] = 118;
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view(
            'admin.quotations.basement._wizard_sections',
            array_merge($this->wizardData($inputs, 'full', $enabledNow), ['isEdit' => true])
        )->render();

        $this->assertStringNotContainsString('Ceiling drywall (sq ft)', $html);
        $this->assertStringContainsString('original hang-or-work-area fallback', $html);
        $this->assertStringContainsString('data-br-legacy-ceiling-notice', $html);
        $this->assertStringContainsString('Saved hang (118 sq ft) stays on preview and resave', $html);
        $this->assertStringContainsString('Use Ceiling area', $html);
        $this->assertStringContainsString('data-br-adopt-ceiling-hang', $html);
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-dw-ceil")(?=[^>]*\btype="hidden")(?=[^>]*\bvalue="118")/',
            $html
        );
    }

    public function test_legacy_blank_hang_quote_shows_adoption_notice(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        unset($inputs['ceiling_hang_unified']);
        $inputs['ceiling_sf'] = 1500;
        $inputs['area_sqft'] = 1200;
        $inputs['drywall_ceiling_sf'] = '';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view(
            'admin.quotations.basement._wizard_sections',
            array_merge($this->wizardData($inputs, 'full', $enabledNow), ['isEdit' => true])
        )->render();

        $this->assertStringContainsString('data-br-legacy-ceiling-notice', $html);
        $this->assertStringContainsString('Saved hang (follows work area) stays on preview and resave', $html);
        $this->assertStringContainsString('Use Ceiling area', $html);
        $this->assertStringContainsString('id="br-dw-ceil"', $html);
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
            'enabledCount' => count($enabledNow) + 1,
            'filledKeys' => [],
        ];
    }
}
