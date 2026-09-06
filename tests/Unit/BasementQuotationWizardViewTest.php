<?php

namespace Tests\Unit;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementQuotationWizardViewTest extends TestCase
{
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

    public function test_wall_wizard_includes_doors_trim_step(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $inputs['scope_preset'] = 'wall';
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('wall');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'wall', $enabledNow))->render();

        $this->assertDoesNotMatchRegularExpression('/data-br-step="doors_trim"[^>]*\bhidden\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-br-step="framing"[^>]*\bhidden\b/', $html);
        $this->assertMatchesRegularExpression('/data-br-step="flooring"[^>]*\bhidden\b/', $html);
        $this->assertStringContainsString('Auto: wall length × 2', $html);
        $this->assertStringContainsString('Auto: wall length × height × both sides', $html);
        $this->assertMatchesRegularExpression('/data-br-skip-on-wall[^>]*\bhidden\b/', $html);
        $this->assertContains('doors_trim', $enabledNow);
    }

    public function test_electrical_and_plumbing_steps_render_priced_lines_and_scope_hint(): void
    {
        $inputs = BasementQuotationCalculator::defaultInputs();
        $enabledNow = BasementQuotationCalculator::presetEnabledSections('full');

        $html = view('admin.quotations.basement._wizard_sections', $this->wizardData($inputs, 'full', $enabledNow))->render();

        $this->assertStringContainsString('name="electrical_lines[0][description]"', $html);
        $this->assertStringContainsString('name="plumbing_lines[0][description]"', $html);
        $this->assertStringContainsString('licensed electrician', $html);
        $this->assertStringContainsString('licensed plumber', $html);
        $this->assertStringNotContainsString('name="wiring_runs"', $html);
        $this->assertStringContainsString('name="outlet_qty"', $html);
        $this->assertStringContainsString('name="bath_sink_qty"', $html);
        $this->assertStringContainsString('How many sinks?', $html);
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
