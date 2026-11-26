<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\SharedWork;
use App\Services\BasementQuotationCalculator;
use App\Services\SharedWorkCatalog;
use App\Support\QuotationHelperLabor;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class BasementRateOverrideTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_quote_rate_override_saves_reopens_totals_pdf_and_ignores_later_shared_price_edits(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->where('key', 'paint_walls')->first();
        $this->assertNotNull($work, 'paint_walls must be seeded by migrations');
        $originalFlat = $work->flat_rate;
        $work->flat_rate = 2.51;
        $work->save();
        SharedWorkCatalog::forgetCache();

        try {
            $settingsOwner = (float) BasementSettings::current()->owner_hourly_rate;
            $this->assertGreaterThan(0, $settingsOwner);

            $enabled = ['overview', 'painting'];
            $payload = [
                'save_intent' => 'publish',
                'client_name' => 'Rate Override Client',
                'scope_preset' => 'custom',
                'enabled_sections' => $enabled,
                'area_sqft' => 200,
                'ceiling_height_ft' => 8,
                'ceiling_type' => 'none',
                'flooring_type' => 'none',
                'paint_wall_sf' => 100,
                'paint_ceiling_sf' => 0,
                'paint_trim_lf' => 0,
                'paint_coats' => 2,
                'paint_colors' => 1,
                'include_cleaning' => '0',
                'include_touchups' => '0',
                'include_demolition' => '0',
                'include_helper' => '0',
                'include_commute' => '0',
                'include_debris_removal' => '0',
                'ask_reply_to_accept' => '0',
                'payment_installments' => 3,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
                'detector_qty' => 0,
                'rate_overrides' => [
                    'paint_walls' => '1.20',
                ],
            ];

            $this->post(route('admin.quotations.basement.store'), $payload)
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            $quote = BasementQuotation::query()->where('client_name', 'Rate Override Client')->first();
            $this->assertNotNull($quote);
            $this->assertEqualsWithDelta(1.20, (float) ($quote->inputs['rate_overrides']['paint_walls'] ?? 0), 0.001);
            $work->refresh();
            $this->assertEqualsWithDelta(2.51, (float) $work->flat_rate, 0.001);

            $expectedHours = round((100 * 2) / ($settingsOwner / 1.20), 2);
            $sharedHours = round((100 * 2) / ($settingsOwner / 2.51), 2);
            $this->assertNotEquals($sharedHours, $expectedHours);

            $paint = collect($quote->calculation['labor'] ?? [])->firstWhere('key', 'paint_walls');
            $this->assertNotNull($paint);
            $this->assertEqualsWithDelta($expectedHours, (float) $paint['hours'], 0.01);
            $this->assertEqualsWithDelta(
                QuotationHelperLabor::amount($expectedHours, 80, 40, false),
                (float) $paint['amount'],
                0.01
            );

            $without = $quote->inputs;
            unset($without['rate_overrides']);
            $plain = (new BasementQuotationCalculator)->calculate($without);
            $plainPaint = collect($plain['labor'])->firstWhere('key', 'paint_walls');
            $this->assertEqualsWithDelta($sharedHours, (float) $plainPaint['hours'], 0.01);
            $this->assertNotEquals(
                (float) $plain['summary']['grand_total'],
                (float) $quote->calculation['summary']['grand_total']
            );
            $this->assertEqualsWithDelta(
                (float) $quote->calculation['summary']['final_quote_total'],
                (float) $quote->final_quote_total,
                0.01
            );

            $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
            $block = $this->rateBlock($editHtml, 'paint_walls');
            $this->assertStringContainsString('value="1.20"', $block);
            $this->assertStringContainsString('is-custom', $block);
            $this->assertDoesNotMatchRegularExpression('/data-br-rate-custom[^>]*\bhidden\b/', $block);
            $this->assertStringContainsString('Custom', $block);
            $this->assertStringContainsString('aria-label="Edit wall paint rates"', $editHtml);

            $clientProject = ClientProject::factory()->create([
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'billing_type' => ClientProject::BILLING_FIXED,
            ]);
            $this->post(route('admin.quotations.basement.link', $quote), [
                'client_project_id' => $clientProject->id,
            ])->assertSessionHasNoErrors();

            $cpq = ClientProjectQuotation::query()
                ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
                ->where('source_id', $quote->id)
                ->first();
            $this->assertNotNull($cpq);

            $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$clientProject, $cpq]));
            $pdf->assertOk();
            $pdfPath = storage_path('app/rate-override-'.$quote->id.'.pdf');
            file_put_contents($pdfPath, $pdf->getContent());
            $pdfText = $this->extractPdfText($pdfPath);
            @unlink($pdfPath);
            $this->assertStringContainsString(ClientProject::formatMoney((float) $quote->final_quote_total), $pdfText);

            $work->flat_rate = 9.99;
            $work->save();
            SharedWorkCatalog::forgetCache();

            $locked = (new BasementQuotationCalculator)->calculate($quote->inputs);
            $lockedPaint = collect($locked['labor'])->firstWhere('key', 'paint_walls');
            $this->assertEqualsWithDelta($expectedHours, (float) $lockedPaint['hours'], 0.01);

            $followsShared = (new BasementQuotationCalculator)->calculate($without);
            $followsPaint = collect($followsShared['labor'])->firstWhere('key', 'paint_walls');
            $bumpedHours = round((100 * 2) / ($settingsOwner / 9.99), 2);
            $this->assertEqualsWithDelta($bumpedHours, (float) $followsPaint['hours'], 0.01);
            $this->assertNotEquals($expectedHours, $bumpedHours);
        } finally {
            $work->flat_rate = $originalFlat;
            $work->save();
            SharedWorkCatalog::forgetCache();
        }
    }

    public function test_quotes_without_a_rate_override_keep_the_shared_rate(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->where('key', 'paint_walls')->first();
        $this->assertNotNull($work);
        $originalFlat = $work->flat_rate;
        $work->flat_rate = 2.51;
        $work->save();
        SharedWorkCatalog::forgetCache();

        try {
            $this->post(route('admin.quotations.basement.store'), [
                'save_intent' => 'draft',
                'client_name' => 'Old Rate Quote',
                'scope_preset' => 'custom',
                'enabled_sections' => ['overview', 'painting'],
                'area_sqft' => 200,
                'ceiling_height_ft' => 8,
                'ceiling_type' => 'none',
                'flooring_type' => 'none',
                'paint_wall_sf' => 100,
                'paint_coats' => 2,
                'paint_colors' => 1,
                'include_cleaning' => '0',
                'include_touchups' => '0',
                'include_demolition' => '0',
                'include_helper' => '0',
                'include_commute' => '0',
                'include_debris_removal' => '0',
                'ask_reply_to_accept' => '0',
                'payment_installments' => 1,
                'owner_hourly_rate' => 80,
                'detector_qty' => 0,
            ])->assertSessionHasNoErrors()->assertRedirect();

            $quote = BasementQuotation::query()->where('client_name', 'Old Rate Quote')->first();
            $this->assertNotNull($quote);
            $this->assertArrayNotHasKey('rate_overrides', $quote->inputs ?? []);

            $settingsOwner = (float) BasementSettings::current()->owner_hourly_rate;
            $sharedHours = round((100 * 2) / ($settingsOwner / 2.51), 2);
            $paint = collect($quote->calculation['labor'] ?? [])->firstWhere('key', 'paint_walls');
            $this->assertNotNull($paint);
            $this->assertEqualsWithDelta($sharedHours, (float) $paint['hours'], 0.01);

            $again = (new BasementQuotationCalculator)->calculate($quote->inputs);
            $this->assertEqualsWithDelta(
                (float) $quote->calculation['summary']['grand_total'],
                (float) $again['summary']['grand_total'],
                0.01
            );

            $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
            $block = $this->rateBlock($editHtml, 'paint_walls');
            $this->assertStringContainsString('value="2.51"', $block);
            $this->assertStringContainsString('data-br-rate-shared="2.51"', $block);
            $this->assertMatchesRegularExpression('/data-br-rate-custom[^>]*\bhidden\b/', $block);
            $this->assertDoesNotMatchRegularExpression('/\bis-custom\b/', $block);
        } finally {
            $work->flat_rate = $originalFlat;
            $work->save();
            SharedWorkCatalog::forgetCache();
        }
    }

    private function rateBlock(string $html, string $key): string
    {
        $pattern = '/<div class="br-rate[^"]*" data-br-rate="'.preg_quote($key, '/').'"[\s\S]{0,2200}/';
        $this->assertSame(1, preg_match($pattern, $html, $match), 'Missing rate editor for '.$key);

        return $match[0];
    }
}
