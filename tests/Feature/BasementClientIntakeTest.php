<?php

namespace Tests\Feature;

use App\Mail\BasementClientIntakeMail;
use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementClientIntakeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_unknown_token_shows_unavailable_page(): void
    {
        $this->get('/quote-form/not-a-real-token')
            ->assertOk()
            ->assertSee('Form not available')
            ->assertSee('invalid or has expired');

        $this->get('/quote-form/'.str_repeat('a', 64))
            ->assertOk()
            ->assertSee('Form not available');
    }

    public function test_wall_preset_cannot_use_intake_form(): void
    {
        $quote = $this->makeQuote([
            'scope_preset' => 'wall',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('wall'),
        ]);

        $this->get(route('public.basement-intake.show', ['token' => $quote->client_intake_token]))
            ->assertOk()
            ->assertSee('does not use the client questionnaire');
    }

    public function test_full_form_shows_basement_sections_bathroom_form_hides_them(): void
    {
        $full = $this->makeQuote(['scope_preset' => 'full']);
        $fullHtml = $this->get(route('public.basement-intake.show', ['token' => $full->client_intake_token]))
            ->assertOk()
            ->assertSee('Basement / work area')
            ->assertSee('Living-area ceiling lights')
            ->assertSee('Who supplies the flooring?')
            ->assertSee('New walls (linear feet)')
            ->assertDontSee('Owner hourly')
            ->getContent();
        $this->assertNoCheckedRadios($fullHtml);

        $bath = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'area_sqft' => 80,
        ]);
        $html = $this->get(route('public.basement-intake.show', ['token' => $bath->client_intake_token]))
            ->assertOk()
            ->assertSee('Bathroom size')
            ->assertSee('Who supplies the vanity or pedestal sink?')
            ->getContent();

        $this->assertStringNotContainsString('Living-area ceiling lights', $html);
        $this->assertStringNotContainsString('Who supplies the flooring?', $html);
        $this->assertStringNotContainsString('New walls (linear feet)', $html);
        $this->assertStringNotContainsString('name="flooring_type"', $html);
        $this->assertNoCheckedRadios($html);
    }

    public function test_new_form_does_not_preselect_answers(): void
    {
        $quote = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'area_sqft' => 80,
            'include_vanity' => true,
            'bath_type' => 'full',
            'bath_rough_in' => true,
        ]);

        $html = $this->get(route('public.basement-intake.show', ['token' => $quote->client_intake_token]))
            ->assertOk()
            ->assertSee('Nothing is pre-selected')
            ->getContent();

        $this->assertNoCheckedRadios($html);
    }

    public function test_submit_without_choices_is_rejected(): void
    {
        $quote = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
        ]);
        $url = route('public.basement-intake.show', ['token' => $quote->client_intake_token]);

        $this->from($url)
            ->post(route('public.basement-intake.store', ['token' => $quote->client_intake_token]), [])
            ->assertRedirect($url)
            ->assertSessionHasErrors(['area_entry_mode', 'bath_type', 'sink', 'include_cleaning']);

        $quote->refresh();
        $this->assertNull($quote->client_intake_submitted_at);
    }

    public function test_previous_client_answers_are_restored_when_reopened(): void
    {
        $quote = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
        ]);
        $quote->client_intake_answers = $this->bathroomPayload(['sink' => 'pedestal']);
        $quote->save();

        $html = $this->get(route('public.basement-intake.show', ['token' => $quote->client_intake_token]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="sink"\s+value="pedestal"[^>]*checked/i',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="sink"\s+value="vanity"[^>]*checked/i',
            $html
        );
    }

    public function test_one_submit_lock_rejects_second_post(): void
    {
        $quote = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
            'flooring_type' => 'lvp',
        ]);
        $token = $quote->client_intake_token;
        $payload = $this->bathroomPayload([
            'area_sqft' => 90,
            'flooring_type' => 'tile',
            'sink' => 'pedestal',
            'sink_provide' => 'client',
        ]);

        $this->post(route('public.basement-intake.store', ['token' => $token]), $payload)
            ->assertRedirect(route('public.basement-intake.show', ['token' => $token]));

        $quote->refresh();
        $this->assertNotNull($quote->client_intake_submitted_at);
        $this->assertEquals(90.0, (float) $quote->inputs['area_sqft']);
        $this->assertTrue((bool) $quote->inputs['include_pedestal_sink']);
        $this->assertFalse((bool) $quote->inputs['include_vanity']);
        $this->assertSame('lvp', $quote->inputs['flooring_type']);
        $this->assertContains('pedestal_sink', $quote->inputs['client_provides_bath_items']);
        $this->assertContains('include_pedestal_sink', $quote->clientFilledKeys());

        $this->get(route('public.basement-intake.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('already received your answers');

        $this->post(route('public.basement-intake.store', ['token' => $token]), $this->bathroomPayload([
            'area_sqft' => 10,
        ]))->assertRedirect(route('public.basement-intake.show', ['token' => $token]));

        $quote->refresh();
        $this->assertEquals(90.0, (float) $quote->inputs['area_sqft']);
    }

    public function test_admin_edit_shows_intake_bar_and_client_filled_icon(): void
    {
        $this->actingAsAdmin();

        $quote = $this->makeQuote([
            'scope_preset' => 'full',
            'include_bathroom' => true,
        ]);
        $quote->client_filled_keys = ['include_vanity', 'area_sqft'];
        $quote->save();

        $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('Client questionnaire')
            ->assertSee('Copy link')
            ->assertSee('Link ready')
            ->assertSee('br-intake-copy', false)
            ->assertSee('br-client-filled', false)
            ->assertSee('Filled from client form');
    }

    public function test_admin_can_reopen_and_email_intake(): void
    {
        $this->actingAsAdmin();
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.test',
            'client_name' => 'Pat Client',
        ]);
        $quote = $this->makeQuote([
            'scope_preset' => 'bathroom',
            'enabled_sections' => BasementQuotationCalculator::presetEnabledSections('bathroom'),
            'include_bathroom' => true,
        ]);
        $quote->client_project_id = $project->id;
        $quote->client_intake_submitted_at = now();
        $quote->save();

        $this->post(route('admin.quotations.basement.intake.reopen', $quote))
            ->assertRedirect();
        $quote->refresh();
        $this->assertNull($quote->client_intake_submitted_at);

        $this->post(route('admin.quotations.basement.intake.email', $quote))
            ->assertRedirect();
        Mail::assertSent(BasementClientIntakeMail::class, function (BasementClientIntakeMail $mail) use ($quote) {
            return $mail->quotation->is($quote)
                && str_contains($mail->formUrl, '/quote-form/'.$quote->client_intake_token);
        });
        $quote->refresh();
        $this->assertNotNull($quote->client_intake_sent_at);
    }

    /**
     * @param  array<string, mixed>  $inputOverrides
     */
    private function makeQuote(array $inputOverrides = []): BasementQuotation
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), $inputOverrides);
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        return BasementQuotation::create([
            'quote_number' => 'BR-INTAKE-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'client_name' => 'Intake Test Client',
            'is_draft' => true,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => round((float) ($calc['summary']['final_quote_total'] ?? 0), 2),
            'client_intake_token' => bin2hex(random_bytes(32)),
            'version' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bathroomPayload(array $overrides = []): array
    {
        return array_merge([
            'area_entry_mode' => 'sqft',
            'area_sqft' => 80,
            'include_bathroom' => '1',
            'bath_rough_in' => '1',
            'bath_type' => 'full',
            'sink' => 'vanity',
            'sink_provide' => 'we',
            'faucet_provide' => 'we',
            'include_toilet' => '1',
            'toilet_provide' => 'we',
            'include_shower' => '1',
            'shower_provide' => 'we',
            'showerhead_provide' => 'we',
            'include_tub' => '0',
            'include_exhaust_fan' => '1',
            'include_bath_fixture' => '0',
            'include_bath_spot' => '0',
            'include_cleaning' => '1',
            'include_touchups' => '1',
            'tile_floor_mode' => 'sqft',
            'tile_floor_sqft' => 40,
            'tile_floor_finish' => 'tile',
            'tile_shower_mode' => 'sqft',
            'tile_shower_sqft' => 30,
            'tile_shower_finish' => 'tile',
        ], $overrides);
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
