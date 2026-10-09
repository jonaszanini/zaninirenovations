<?php

namespace Tests\Feature\Admin;

use App\Models\FenceFormulaSetting;
use App\Models\FenceQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceExistingPostLabourCreditTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_config_page_exposes_existing_post_labour_credit_money_field(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.quotations.fence.config'));

        $response->assertOk();
        $response->assertSee('Labour credit per existing post', false);
        $response->assertSee('name="existing_post_labour_credit"', false);
        $response->assertSee('cp-money-input', false);
        $html = $response->getContent();
        $this->assertStringNotContainsString('data-cp-money-entry="direct"', $html);
        $this->assertMatchesRegularExpression(
            '/id="fq-existing-post-credit"[^>]*class="[^"]*cp-money-input/',
            $html
        );
    }

    public function test_formula_update_rejects_negative_existing_post_credit(): void
    {
        $this->actingAsAdmin();
        $settings = FenceFormulaSetting::current();

        $response = $this->from(route('admin.quotations.fence.config'))
            ->put(route('admin.quotations.fence.config.formula.update'), $this->formulaPayload([
                'existing_post_labour_credit' => -1,
            ]));

        $response->assertRedirect(route('admin.quotations.fence.config'));
        $response->assertSessionHasErrors('existing_post_labour_credit');
        $this->assertEqualsWithDelta(30.0, (float) $settings->fresh()->existing_post_labour_credit, 0.001);
    }

    public function test_formula_update_saves_existing_post_credit(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.quotations.fence.config.formula.update'), $this->formulaPayload([
            'existing_post_labour_credit' => 45.50,
        ]));

        $response->assertRedirect();
        $this->assertEqualsWithDelta(45.50, (float) FenceFormulaSetting::current()->existing_post_labour_credit, 0.001);
    }

    public function test_preview_applies_existing_post_labour_credit(): void
    {
        $this->actingAsAdmin();

        $base = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24]],
            'existing_post_labour_credit' => 30,
        ]))->assertOk()->json();

        $left = $this->postJson(route('admin.quotations.fence.preview'), $this->previewPayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => 1]],
            'existing_post_labour_credit' => 30,
        ]))->assertOk()->json();

        $this->assertSame(0, $base['totals']['existing_post_credit']['count']);
        $this->assertSame(1, $left['totals']['existing_post_credit']['count']);
        $this->assertEqualsWithDelta(30.0, $left['totals']['existing_post_credit']['amount'], 0.001);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $left['totals']['labour_subtotal'],
            0.02
        );
    }

    public function test_new_quote_persists_config_credit_and_old_quote_stays_zero_until_reprice(): void
    {
        $this->actingAsAdmin();
        FenceFormulaSetting::current()->update(['existing_post_labour_credit' => 30]);

        $this->post(route('admin.quotations.fence.store'), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 30,
        ]))->assertRedirect();

        $new = FenceQuotation::query()->latest('id')->first();
        $this->assertNotNull($new);
        $this->assertEqualsWithDelta(30.0, (float) $new->existing_post_labour_credit, 0.001);
        $this->assertSame(1, $new->calculation['totals']['existing_post_credit']['count']);

        $old = FenceQuotation::create([
            'quote_number' => 'FQ-TEST-OLD',
            'client_name' => 'Legacy fence',
            'fence_height_ft' => 6,
            'board_style' => FenceQuotation::BOARD_STYLE_STANDARD,
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 0,
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'calculation' => ['totals' => ['labour_subtotal' => 1320]],
            'materials_subtotal' => 0,
            'hst' => 0,
            'labour_total' => 1320,
            'grand_total' => 1320,
            'include_labour_hst' => true,
        ]);

        $this->put(route('admin.quotations.fence.update', $old), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 0,
        ]))->assertRedirect();

        $old->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55, (float) $old->labour_total, 0.02);

        $this->put(route('admin.quotations.fence.update', $old), $this->storePayload([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => '1']],
            'existing_post_labour_credit' => 0,
            'reprice' => '1',
        ]))->assertRedirect();

        $old->refresh();
        $this->assertEqualsWithDelta(30.0, (float) $old->existing_post_labour_credit, 0.001);
        $this->assertEqualsWithDelta(24 * 55 - 30, (float) $old->labour_total, 0.02);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formulaPayload(array $overrides = []): array
    {
        $settings = FenceFormulaSetting::current();

        return array_merge([
            'labour_rate_per_ft' => (float) $settings->labour_rate_per_ft,
            'labour_rate_8ft_per_ft' => (float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft),
            'gate_rails' => (int) $settings->gate_rails,
            'concrete_per_post_6ft' => (float) $settings->concrete_per_post_6ft,
            'concrete_per_post_8ft' => (float) $settings->concrete_per_post_8ft,
            'labour_per_additional_gate' => (float) ($settings->labour_per_additional_gate ?? 0),
            'existing_post_labour_credit' => 30,
            'rails_per_section_6ft' => (int) $settings->rails_per_section_6ft,
            'rails_per_section_8ft' => (int) $settings->rails_per_section_8ft,
            'nails_per_picket' => (int) $settings->nails_per_picket,
            'board_width_in' => (float) $settings->board_width_in,
            'overlapping_in' => (float) ($settings->overlapping_in ?? 0),
            'max_post_spacing_ft' => (float) $settings->max_post_spacing_ft,
            'concrete_per_post_6x6_6ft' => (float) ($settings->concrete_per_post_6x6_6ft ?? 2.5),
            'concrete_per_post_6x6_8ft' => (float) ($settings->concrete_per_post_6x6_8ft ?? 3.5),
            'waste_extra_posts' => (int) ($settings->waste_extra_posts ?? 1),
            'waste_pickets_per_section' => (int) ($settings->waste_pickets_per_section ?? 1),
            'waste_extra_rails' => (int) ($settings->waste_extra_rails ?? 3),
            'waste_extra_brackets' => (int) ($settings->waste_extra_brackets ?? 6),
            'waste_extra_caps' => (int) ($settings->waste_extra_caps ?? 1),
            'waste_concrete_posts_per_extra_bag' => (int) ($settings->waste_concrete_posts_per_extra_bag ?? 3),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function previewPayload(array $overrides = []): array
    {
        return array_merge([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 30,
            'include_labour_hst' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge($this->previewPayload(), [
            'client_name' => 'Test fence client',
            'payment_installments' => 1,
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
        ], $overrides);
    }
}
