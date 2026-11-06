<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementProjectDescriptionDisclosureTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_create_page_shows_collapsed_description_disclosure(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('Project description', false)
            ->assertSee('fa-chevron-down', false)
            ->assertSee('id="br-desc-panel"', false)
            ->assertSee('id="br-work-description"', false)
            ->assertSee('Improve description with AI', false)
            ->assertSee('Create automatically from quotation', false)
            ->assertSee('Optional — appears on the client PDF', false)
            ->assertDontSee('licensed')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<details class="br-desc-acc" id="br-desc-acc"[^>]*\bopen\b/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<summary class="br-desc-acc__summary"[^>]*aria-expanded="false"[^>]*aria-controls="br-desc-panel"/',
            $html
        );
        $this->assertDescriptionPanelOrder($html);
        $this->assertDisclosureStylesAndScript();
    }

    public function test_edit_opens_disclosure_when_description_is_saved(): void
    {
        $this->actingAsAdmin();

        $empty = $this->makeQuote(null);
        $emptyHtml = $this->get(route('admin.quotations.basement.edit', $empty))
            ->assertOk()
            ->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/<details class="br-desc-acc" id="br-desc-acc"[^>]*\bopen\b/',
            $emptyHtml
        );
        $this->assertStringContainsString('aria-expanded="false"', $emptyHtml);

        $filled = $this->makeQuote('<p>Rec room finish with LVP.</p>');
        $html = $this->get(route('admin.quotations.basement.edit', $filled))
            ->assertOk()
            ->assertSee('Rec room finish with LVP', false)
            ->assertSee('aria-expanded="true"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<details class="br-desc-acc" id="br-desc-acc"[^>]*\bopen\b/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<summary class="br-desc-acc__summary"[^>]*aria-expanded="true"[^>]*aria-controls="br-desc-panel"/',
            $html
        );
        $this->assertDescriptionPanelOrder($html);
    }

    public function test_saving_keeps_description_inside_disclosure(): void
    {
        $this->actingAsAdmin();

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Disclosure Client',
            'work_description' => '<p>Finished rec room, bathroom, and LVP floor.</p>',
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];

        $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Disclosure Client')->first();
        $this->assertNotNull($quote);
        $this->assertStringContainsString('Finished rec room, bathroom, and LVP floor.', (string) $quote->work_description);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('Finished rec room, bathroom, and LVP floor.', false)
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/<details class="br-desc-acc" id="br-desc-acc"[^>]*\bopen\b/',
            $html
        );

        $this->put(route('admin.quotations.basement.update', $quote), array_merge($payload, [
            'work_description' => '<p>Finished rec room, bathroom, and LVP floor. Added bulkhead.</p>',
        ]))->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $this->assertStringContainsString('Added bulkhead.', (string) $quote->work_description);
    }

    private function assertDescriptionPanelOrder(string $html): void
    {
        $start = strpos($html, 'id="br-desc-panel"');
        $this->assertNotFalse($start);
        $block = substr($html, $start, 12000);

        $textarea = strpos($block, 'id="br-work-description"');
        $improve = strpos($block, 'Improve description with AI');
        $improveConfig = strpos($block, 'Edit quotation work description prompt');
        $fromQuote = strpos($block, 'Create automatically from quotation');
        $fromQuoteConfig = strpos($block, 'Edit create-from-quotation prompt');
        $history = strpos($block, 'data-history-mount');

        $this->assertNotFalse($textarea);
        $this->assertNotFalse($improve);
        $this->assertNotFalse($improveConfig);
        $this->assertNotFalse($fromQuote);
        $this->assertNotFalse($fromQuoteConfig);
        $this->assertNotFalse($history);
        $this->assertLessThan($improve, $textarea);
        $this->assertLessThan($improveConfig, $improve);
        $this->assertLessThan($fromQuote, $improveConfig);
        $this->assertLessThan($fromQuoteConfig, $fromQuote);
        $this->assertLessThan($history, $fromQuoteConfig);
        $this->assertStringContainsString('name="work_description"', $block);
    }

    private function assertDisclosureStylesAndScript(): void
    {
        $css = (string) file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertStringContainsString('.br-desc-acc__summary:hover', $css);
        $this->assertStringContainsString('.br-desc-acc__summary:focus-visible', $css);
        $this->assertStringContainsString('.br-desc-acc[open] > .br-desc-acc__summary', $css);
        $this->assertStringContainsString('@media (max-width: 400px)', $css);

        $js = (string) file_get_contents(public_path('js/admin-basement-quotation.js'));
        $this->assertStringContainsString('initProjectDescriptionDisclosure', $js);
        $this->assertStringContainsString("summary.setAttribute('aria-expanded'", $js);
    }

    private function makeQuote(?string $workDescription): BasementQuotation
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'owner_hourly_rate' => 80,
        ]);

        return BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => $workDescription ? 'Described Client' : 'Empty Desc Client',
            'is_draft' => false,
            'inputs' => [
                'area_sqft' => 400,
                'ceiling_height_ft' => 8,
                'owner_hourly_rate' => 80,
            ],
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'work_description' => $workDescription,
            'version' => 1,
        ]);
    }
}
