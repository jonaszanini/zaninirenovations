<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementLegacyFlagsHttpTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_legacy_corner_bead_preview_save_reopen_without_v2_flags_keeps_totals(): void
    {
        $this->actingAsAdmin();
        [$quote, $grand, $expectBead] = $this->seedLegacyQuote($this->cornerBeadLegacyInputs(), function (array $calc) {
            $bead = collect($calc['materials'])->firstWhere('key', 'corner_bead');

            return $bead ? (float) $bead['qty'] : 0.0;
        });

        $this->assertPostOmitsV2FlagsPreservesTotal($quote, $grand);
        $this->assertSaveOmitsV2FlagsPreservesTotal($quote, $grand);
        $bead = collect($quote->fresh()->calculation['materials'] ?? [])->firstWhere('key', 'corner_bead');
        $this->assertNotNull($bead);
        $this->assertSame($expectBead, (float) $bead['qty']);
    }

    public function test_legacy_drywall_boxing_preview_save_without_v2_flags_keeps_totals(): void
    {
        $this->actingAsAdmin();
        [$quote, $grand] = $this->seedLegacyQuote($this->boxingLegacyInputs(), fn () => 0.0);

        $boxingBead = collect((new BasementQuotationCalculator)->calculate($quote->inputs, BasementSettings::current())['materials'])
            ->firstWhere('key', 'corner_bead_boxing');
        $this->assertNull($boxingBead);

        $this->assertPostOmitsV2FlagsPreservesTotal($quote, $grand);
        $this->assertSaveOmitsV2FlagsPreservesTotal($quote, $grand);
        $reopened = BasementQuotation::findOrFail($quote->id);
        $this->assertNull(collect($reopened->calculation['materials'] ?? [])->firstWhere('key', 'corner_bead_boxing'));
    }

    public function test_legacy_trim_material_preview_save_without_v2_flags_keeps_totals(): void
    {
        $this->actingAsAdmin();
        [$quote, $grand, $expectUnit] = $this->seedLegacyQuote($this->trimLegacyInputs(), fn () => 'each');

        $base = collect($quote->calculation['materials'])->firstWhere('key', 'baseboard');
        $this->assertSame('each', $base['unit'] ?? null);

        $this->assertPostOmitsV2FlagsPreservesTotal($quote, $grand);
        $this->assertSaveOmitsV2FlagsPreservesTotal($quote, $grand);
        $base = collect($quote->fresh()->calculation['materials'])->firstWhere('key', 'baseboard');
        $this->assertSame($expectUnit, $base['unit'] ?? null);
    }

    public function test_legacy_header_qty_preview_save_without_header_lf_keeps_totals(): void
    {
        $this->actingAsAdmin();
        [$quote, $grand] = $this->seedLegacyQuote($this->headerLegacyInputs(), fn () => 0.0);

        $headers = collect($quote->calculation['labor'])->firstWhere('key', 'framing_headers');
        $this->assertStringContainsString('opening', (string) ($headers['detail'] ?? ''));
        $this->assertEqualsWithDelta(4.0, (float) ($headers['hours'] ?? 0), 0.02);

        $this->assertPostOmitsV2FlagsPreservesTotal($quote, $grand);
        $this->assertSaveOmitsV2FlagsPreservesTotal($quote, $grand);
        $this->assertFalse(array_key_exists('header_lf', $quote->fresh()->inputs));
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  callable(array<string, mixed>): mixed  $afterCalc
     * @return array{0: BasementQuotation, 1: float, 2: mixed}
     */
    private function seedLegacyQuote(array $inputs, callable $afterCalc): array
    {
        $settings = BasementSettings::current();
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $settings);
        $grand = (float) $calc['summary']['grand_total'];
        $quote = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => (string) ($inputs['client_name'] ?? 'Legacy HTTP Client'),
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $grand,
            'is_draft' => true,
            'version' => 1,
            'payment_installments' => 3,
        ]);

        return [$quote, $grand, $afterCalc($calc)];
    }

    private function assertPostOmitsV2FlagsPreservesTotal(BasementQuotation $quote, float $grand): void
    {
        $payload = $this->legacyEditPayload($quote);
        $preview = $this->post(route('admin.quotations.basement.preview', $quote), $payload)
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta($grand, (float) ($preview['summary']['grand_total'] ?? 0), 0.02);
    }

    private function assertSaveOmitsV2FlagsPreservesTotal(BasementQuotation $quote, float $grand): void
    {
        $payload = array_merge($this->legacyEditPayload($quote), [
            'save_intent' => 'publish',
            'payment_installments' => 3,
            'ask_reply_to_accept' => '0',
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
        $quote->refresh();
        $this->assertEqualsWithDelta($grand, (float) $quote->final_quote_total, 0.02);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyEditPayload(BasementQuotation $quote): array
    {
        $payload = array_merge($quote->inputs, [
            'client_name' => $quote->client_name,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
        ]);
        unset(
            $payload['corner_bead_by_height'],
            $payload['drywall_boxing_v2'],
            $payload['trim_material_lf_v2'],
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function cornerBeadLegacyInputs(): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'HTTP Legacy Corner Bead',
            'area_sqft' => 500,
            'ceiling_height_ft' => 9,
            'drywall_wall_sf' => 500,
            'drywall_corners' => 2,
            'finish_level' => 4,
            'flooring_type' => 'lvp',
            'flooring_sf' => 500,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($inputs['corner_bead_by_height'], $inputs['drywall_boxing_v2'], $inputs['trim_material_lf_v2']);

        return $inputs;
    }

    /**
     * @return array<string, mixed>
     */
    private function boxingLegacyInputs(): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'HTTP Legacy Boxing',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'drywall_wall_sf' => 400,
            'bulkhead_lf' => 10,
            'header_lf' => 8,
            'header_qty' => 0,
            'finish_level' => 4,
            'flooring_type' => 'none',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($inputs['corner_bead_by_height'], $inputs['drywall_boxing_v2'], $inputs['trim_material_lf_v2']);

        return $inputs;
    }

    /**
     * @return array<string, mixed>
     */
    private function trimLegacyInputs(): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'HTTP Legacy Trim',
            'area_sqft' => 400,
            'baseboard_lf' => 40,
            'include_shoe' => true,
            'shoe_lf' => 40,
            'door_qty' => 0,
            'drywall_wall_sf' => 0,
            'flooring_type' => 'none',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($inputs['trim_material_lf_v2']);

        return $inputs;
    }

    /**
     * @return array<string, mixed>
     */
    private function headerLegacyInputs(): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'client_name' => 'HTTP Legacy Header Qty',
            'area_sqft' => 600,
            'ceiling_height_ft' => 8,
            'partition_lf' => 20,
            'header_qty' => 2,
            'flooring_type' => 'lvp',
            'flooring_sf' => 600,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($inputs['header_lf']);

        return $inputs;
    }
}
