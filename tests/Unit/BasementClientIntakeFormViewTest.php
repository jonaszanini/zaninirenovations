<?php

namespace Tests\Unit;

use App\Models\BasementQuotation;
use Tests\TestCase;

class BasementClientIntakeFormViewTest extends TestCase
{
    public function test_yesno_partial_starts_unchecked(): void
    {
        $html = view('site.basement_intake._yesno', [
            'name' => 'include_toilet',
            'label' => 'Install a toilet?',
            'form' => [],
        ])->render();

        $this->assertNoCheckedRadios($html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_supply_partial_starts_unchecked(): void
    {
        $html = view('site.basement_intake._supply', [
            'name' => 'sink_provide',
            'label' => 'Who supplies the vanity or pedestal sink?',
            'form' => [],
        ])->render();

        $this->assertNoCheckedRadios($html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_area_partial_starts_with_no_mode_selected(): void
    {
        $html = view('site.basement_intake._area', [
            'prefix' => 'area_entry',
            'modeName' => 'area_entry_mode',
            'label' => 'Bathroom size',
            'form' => [],
        ])->render();

        $this->assertNoCheckedRadios($html);
        $this->assertStringContainsString('hidden', $html);
    }

    public function test_bathroom_questionnaire_has_no_preselected_radios(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        $quote = new BasementQuotation([
            'client_intake_token' => str_repeat('a', 64),
        ]);

        $html = view('site.basement_intake.show', [
            'quote' => $quote,
            'form' => [],
            'preset' => 'bathroom',
            'isBathroomOnly' => true,
        ])->render();

        $this->assertNoCheckedRadios($html);
        $this->assertStringContainsString('Nothing is pre-selected', $html);
        $this->assertStringContainsString('Bathroom type', $html);
        $this->assertStringContainsString('How many sinks?', $html);
        $this->assertStringContainsString('licensed electrician', $html);
        $this->assertStringContainsString('licensed plumber', $html);
    }

    public function test_restored_answers_check_the_chosen_option(): void
    {
        $html = view('site.basement_intake._yesno', [
            'name' => 'include_toilet',
            'label' => 'Install a toilet?',
            'form' => ['include_toilet' => '1'],
        ])->render();

        $this->assertMatchesRegularExpression(
            '/name="include_toilet"\s+value="1"[^>]*checked/i',
            $html
        );
    }

    private function assertNoCheckedRadios(string $html): void
    {
        preg_match_all('/<input\b[^>]*>/i', $html, $tags);
        foreach ($tags[0] as $tag) {
            if (! preg_match('/type=["\']radio["\']/i', $tag)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/\bchecked\b/i', $tag, $tag);
        }
    }
}
