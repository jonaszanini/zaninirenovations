<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementCeilingAreaTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_form_has_one_ceiling_area_and_hides_drywall_duplicate(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="br-ceil-sf"', $html);
        $this->assertStringContainsString('name="ceiling_sf"', $html);
        $this->assertStringContainsString('data-br-split-ceiling="0"', $html);
        $this->assertMatchesRegularExpression(
            '/data-br-split-ceiling-field[^>]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('One ceiling area for this quote', $html);
    }

    public function test_unified_ceiling_persists_on_save_reload_and_preview(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload([
            'client_name' => 'Ceiling Unify Client',
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 3767.65,
        ]);
        unset($payload['drywall_ceiling_sf']);

        $store = $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Ceiling Unify Client')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $this->assertEqualsWithDelta(1500.0, (float) ($quote->inputs['ceiling_sf'] ?? 0), 0.01);
        $this->assertFalse(\App\Services\BasementCeilingQuantities::usesSplit($quote->inputs ?? []));
        $this->assertEqualsWithDelta(1500.0, (float) ($quote->calculation['scope']['drywall_ceiling_sf'] ?? 0), 0.01);

        $edit = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-ceil-sf")(?=[^>]*\bvalue="1500)/',
            $edit
        );
        $this->assertMatchesRegularExpression(
            '/data-br-split-ceiling-field[^>]*\bhidden\b/',
            $edit
        );

        $preview = $this->post(route('admin.quotations.basement.preview'), $payload)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(1500.0, (float) ($preview['scope']['drywall_ceiling_sf'] ?? 0), 0.01);
        $hang = collect($preview['labor'] ?? [])->firstWhere('key', 'dw_hang');
        $this->assertIsArray($hang);
        $this->assertStringContainsString('5,268', $hang['detail']);
    }

    public function test_legacy_split_quote_keeps_saved_drywall_ceiling_and_shows_the_field(): void
    {
        $this->actingAsAdmin();

        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 3767.65,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Legacy Split Ceiling',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'version' => 1,
        ]);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-br-split-ceiling="1"', $html);
        $this->assertTrue((bool) preg_match('/<div class="br-field--wide"[^>]*data-br-split-ceiling-field[^>]*>/', $html, $match));
        $this->assertStringNotContainsString('hidden', $match[0]);
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*\bid="br-dw-ceil")(?=[^>]*\bvalue="118)/',
            $html
        );
        $this->assertStringContainsString('saved a drywall ceiling area different from Ceiling Options', $html);

        $recalc = (new BasementQuotationCalculator)->calculate($quote->inputs);
        $this->assertEqualsWithDelta(118.0, (float) $recalc['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertEqualsWithDelta(
            (float) $quote->final_quote_total,
            (float) $recalc['summary']['final_quote_total'],
            0.05
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'client_name' => 'Ceiling Unify Client',
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
            'ceiling_type' => 'drywall',
        ], $overrides);
    }
}
