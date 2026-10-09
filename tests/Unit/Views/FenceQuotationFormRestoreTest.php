<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class FenceQuotationFormRestoreTest extends TestCase
{
    public function test_render_sides_casts_posted_zero_as_unchecked(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));

        $this->assertStringContainsString('function flagOn(', $blade);
        $this->assertStringContainsString('function connectedOn(', $blade);
        $this->assertStringContainsString("value === '1'", $blade);
        $this->assertStringContainsString('FenceSideFlags::normaliseSides', $blade);
        $this->assertStringContainsString('Saved null is off', $blade);
        $this->assertStringContainsString('$priceDeltaAmountLabel', $blade);
        $this->assertStringContainsString('formatCatalogueDeltaLabel', $blade);
        $this->assertStringNotContainsString('@endif.', $blade);

        $this->assertStringContainsString('flagOn(side.already_have_post_left)', $blade);
        $this->assertStringContainsString('flagOn(side.already_have_post_right)', $blade);
        $this->assertStringContainsString('flagOn(side.gate_goes_here)', $blade);
        $this->assertStringContainsString('connectedOn(side ? side.connected_to_previous : null, i)', $blade);

        $this->assertStringNotContainsString('!!(side && side.already_have_post_left)', $blade);
        $this->assertStringNotContainsString('!!(side && side.already_have_post_right)', $blade);
        $this->assertStringNotContainsString('!!(side && side.gate_goes_here)', $blade);
        $this->assertStringNotContainsString('connected_to_previous === false ? false : true', $blade);
    }

    public function test_editor_exposes_existing_post_labour_credit_line(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));

        $this->assertStringContainsString('Labour credit per existing post', $blade);
        $this->assertStringContainsString('id="fq-existing-post-credit"', $blade);
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[\s\S]*?cp-money-input/',
            $blade
        );
        $this->assertStringNotContainsString(
            'type="hidden" name="existing_post_labour_credit"',
            $blade
        );
        $this->assertStringContainsString('existing-post tick that actually removes a post', $blade);
        $this->assertStringContainsString('label="Labour credit"', $blade);
        $this->assertStringContainsString('−$\' + rateLabel + \' × \'', $blade);
        $this->assertStringContainsString('existing post credit', $blade);
        $this->assertStringContainsString('fq-t-existing-post-credit-row', $blade);
        $this->assertStringContainsString('fq-t-labour-net-row', $blade);
        $this->assertStringContainsString('existing_post_labour_credit', $blade);
        $this->assertStringContainsString("grid-template-columns: 1fr", $blade);
        $this->assertStringContainsString('tLabourNetRow.classList.add(\'is-hidden\')', $blade);
        $this->assertStringContainsString('parseOptionalLabourRate', $blade);
        $this->assertStringNotContainsString(
            "existingPostCreditInput.addEventListener('input'",
            $blade
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="fq-existing-post-credit"[^>]*data-cp-money-entry="direct"/',
            $blade
        );
        $this->assertStringNotContainsString('Existing post credit: -$', $blade);
    }

    public function test_config_page_uses_calculator_style_money_input_for_existing_post_credit(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/config/index.blade.php'));

        $this->assertStringContainsString('Labour credit per existing post', $blade);
        $this->assertStringContainsString('name="existing_post_labour_credit"', $blade);
        $this->assertStringContainsString('id="fq-existing-post-credit"', $blade);
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*class="[^"]*cp-money-input/',
            $blade
        );
        $this->assertStringContainsString('label="Labour credit"', $blade);
        $this->assertStringContainsString('Subtracted from labour when', $blade);
        $this->assertStringNotContainsString(
            'admin-form-help">Subtracted from labour when',
            $blade
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="fq-existing-post-credit"[^>]*data-cp-money-entry="direct"/',
            $blade
        );
    }

    public function test_js_flag_helpers_restore_old_zero_and_null_like_php(): void
    {
        $blade = (string) file_get_contents(resource_path('views/admin/quotations/fence/create.blade.php'));
        $this->assertTrue((bool) preg_match('/function flagOn\(value\) \{.*?\}/s', $blade, $flag));
        $this->assertTrue((bool) preg_match('/function connectedOn\(value, index\) \{.*?\}/s', $blade, $connected));

        $script = $flag[0]."\n".$connected[0]."\n".<<<'JS'
const cases = [
  [flagOn(true), true],
  [flagOn(1), true],
  [flagOn('1'), true],
  [flagOn(false), false],
  [flagOn(0), false],
  [flagOn('0'), false],
  [flagOn(null), false],
  [connectedOn(null, 0), false],
  [connectedOn(undefined, 0), false],
  [connectedOn(null, 1), false],
  [connectedOn(undefined, 1), true],
  [connectedOn('0', 1), false],
  [connectedOn(0, 1), false],
  [connectedOn('1', 1), true],
  [connectedOn(true, 1), true],
];
for (const [got, exp] of cases) {
  if (got !== exp) {
    console.error('mismatch', got, exp);
    process.exit(1);
  }
}
JS;

        $tmp = tempnam(sys_get_temp_dir(), 'fq-flags-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' 2>&1', $output, $code);
        @unlink($tmp);

        $this->assertSame(0, $code, implode("\n", $output));
    }
}
