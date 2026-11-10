<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementClientProvidesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_review_client_provides_checkboxes_render_section_keys(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/type="checkbox"[^>]*name="client_provides_sections\[\]"[^>]*value="flooring"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/type="checkbox"[^>]*name="client_provides_sections\[\]"[^>]*value="painting"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/type="checkbox"[^>]*name="client_provides_bath_items\[\]"[^>]*value="vanity"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="client_provides_sections\[\]"[^>]*value="1"[^>]*checked/',
            $html
        );
    }

    public function test_preview_accepts_client_provides_sections_and_zeros_flooring_materials(): void
    {
        $this->actingAsAdmin();
        $quote = $this->seedFlooringQuote();

        $payload = $this->editPayload($quote);
        $basePreview = $this->postJson(route('admin.quotations.basement.calculate-existing', $quote), $payload)
            ->assertOk()
            ->json();

        $payload['client_provides_sections'] = ['flooring'];
        $withPreview = $this->postJson(route('admin.quotations.basement.calculate-existing', $quote), $payload)
            ->assertOk()
            ->json();

        $floorMatsBase = collect($basePreview['materials'] ?? [])->where('section', 'flooring')->sum('amount');
        $floorMatsWith = collect($withPreview['materials'] ?? [])->where('section', 'flooring')->sum('amount');
        $this->assertGreaterThan(0, $floorMatsBase);
        $this->assertEqualsWithDelta(0.0, (float) $floorMatsWith, 0.01);

        $floorLabBase = collect($basePreview['labor'] ?? [])->where('section', 'flooring')->sum('amount');
        $floorLabWith = collect($withPreview['labor'] ?? [])->where('section', 'flooring')->sum('amount');
        $this->assertEqualsWithDelta($floorLabBase, $floorLabWith, 0.01);

        $grandDrop = (float) ($basePreview['summary']['grand_total'] ?? 0)
            - (float) ($withPreview['summary']['grand_total'] ?? 0);
        $this->assertEqualsWithDelta(4331.85, $grandDrop, 0.05);
    }

    public function test_saved_quote_keeps_stored_total_until_publish_save(): void
    {
        $this->actingAsAdmin();
        $quote = $this->seedFlooringQuote();
        $storedTotal = (float) $quote->final_quote_total;

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(ClientProject::formatMoney($storedTotal), $editHtml);

        $preview = $this->postJson(
            route('admin.quotations.basement.calculate-existing', $quote),
            array_merge($this->editPayload($quote), ['client_provides_sections' => ['flooring']])
        )->assertOk()->json();
        $this->assertLessThan($storedTotal, (float) ($preview['summary']['grand_total'] ?? 0));

        $quote->refresh();
        $this->assertEqualsWithDelta($storedTotal, (float) $quote->final_quote_total, 0.01);
    }

    public function test_reopen_with_saved_flooring_client_provides_matches_toolbar_and_checkbox(): void
    {
        $this->actingAsAdmin();
        $quote = $this->seedFlooringQuote();
        $payload = array_merge($this->publishPayload(), $this->editPayload($quote), [
            'client_provides_sections' => ['flooring'],
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $stored = (float) $quote->final_quote_total;
        $previewInputs = array_merge($quote->inputs, $this->editPayload($quote));
        $live = (new BasementQuotationCalculator)->calculate($previewInputs);
        $this->assertEqualsWithDelta((float) $live['summary']['grand_total'], $stored, 0.02);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/name="client_provides_sections\[\]"[^>]*value="flooring"[^>]*checked/',
            $html
        );
        $this->assertStringContainsString(ClientProject::formatMoney($stored), $html);
    }

    public function test_publish_save_persists_client_provides_sections(): void
    {
        $this->actingAsAdmin();
        $quote = $this->seedFlooringQuote();
        $storedTotal = (float) $quote->final_quote_total;

        $payload = array_merge($this->publishPayload(), $this->editPayload($quote), [
            'client_provides_sections' => ['flooring'],
        ]);
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertContains('flooring', $quote->inputs['client_provides_sections'] ?? []);
        $this->assertLessThan($storedTotal, (float) $quote->final_quote_total);

        $reopen = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/name="client_provides_sections\[\]"[^>]*value="flooring"[^>]*checked/',
            $reopen
        );
    }

    private function seedFlooringQuote(): BasementQuotation
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 800,
            'include_demolition' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'flooring_sf' => 800,
            'flooring_type' => 'lvp',
            'underlayment' => true,
            'contractor_fee_percent' => 10,
            'insulate_walls' => false,
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('full'),
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        return BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => 'Client provides flooring QA',
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => (float) $calc['summary']['final_quote_total'],
            'version' => 1,
            'payment_installments' => 3,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(BasementQuotation $quote): array
    {
        return array_merge(is_array($quote->inputs) ? $quote->inputs : [], [
            'client_name' => $quote->client_name,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
            'payment_installments' => 3,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publishPayload(): array
    {
        return [
            'save_intent' => 'publish',
            'payment_installments' => 3,
            'ask_reply_to_accept' => '0',
        ];
    }
}
