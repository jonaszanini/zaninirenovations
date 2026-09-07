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
