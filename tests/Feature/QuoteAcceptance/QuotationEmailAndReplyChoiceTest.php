<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Mail\ClientProjectQuotationClient;
use App\Support\QuotationReplyToAccept;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationEmailAndReplyChoiceTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;

    public function test_quotation_email_subject_contains_project_and_quote_numbers(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation([
            'quote_number' => 'SQ-2026-0001',
        ]);

        $this->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect();

        Mail::assertSent(ClientProjectQuotationClient::class, function (ClientProjectQuotationClient $mail) use ($project, $quotation) {
            $expected = ClientProjectQuotationClient::subjectFor($project, $quotation);
            $mail->build();
            $this->assertSame($expected, $mail->subject);
            $this->assertStringContainsString('PRJ', $expected);
            $this->assertStringContainsString('SQ-2026-0001', $expected);
            $html = view($mail->view, $mail->viewData)->render();
            $this->assertStringContainsString('Accept this quote', $html);
            $this->assertStringContainsString('I accept', $html);

            return true;
        });
    }

    public function test_email_is_blocked_when_reply_to_accept_choice_is_unset(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation([
            'ask_reply_to_accept' => null,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertNull($quotation->fresh()->sent_at);
    }

    public function test_save_is_blocked_when_reply_to_accept_choice_is_unset(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Reply Choice Client',
                'area_sqft' => 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'payment_installments' => 3,
                'include_commute' => 0,
                'include_debris_removal' => 0,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ]);

        $response->assertRedirect(route('admin.quotations.basement.create'));
        $response->assertSessionHasErrors(QuotationReplyToAccept::FIELD);
        $this->assertSame(0, \App\Models\BasementQuotation::query()->count());
    }

    public function test_editor_shows_required_yes_no_choice(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->assertSee('Ask client to reply', false)
            ->assertSee('name="ask_reply_to_accept"', false)
            ->assertSee('value="1"', false)
            ->assertSee('value="0"', false);
    }
}
