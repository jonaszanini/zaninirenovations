<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Mail\ClientProjectQuoteAcceptedAdmin;
use App\Mail\ClientProjectQuoteAcceptedClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\QuoteAcceptanceService;
use App\Support\BusinessContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\IntegrationTestCase;

class SignedLinkAcceptTest extends IntegrationTestCase
{
    use MakesAcceptableQuotations;

    public function test_unsigned_accept_url_is_rejected(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        $this->get(route('public.quotations.accept', $quotation))
            ->assertForbidden();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);
        $tampered = preg_replace('/signature=[^&]+/', 'signature=deadbeef', $url);

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();
    }

    public function test_signed_link_accepts_quote_and_promotes_project(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Accept this quote', false)
            ->assertSee($project->referenceCode(), false)
            ->assertSee($quotation->displayQuoteNumber(), false)
            ->assertSee('Total incl. HST', false)
            ->assertSee('name="_token"', false);

        $this->assertFalse($quotation->fresh()->isAccepted());
        $this->assertSame(ClientProject::STATUS_QUOTE_SENT, $project->fresh()->status);

        $this->post($url)
            ->assertRedirect()
            ->assertHeader('X-Robots-Tag', 'noindex');

        $quotation->refresh();
        $project->refresh();

        $this->assertTrue($quotation->isAccepted());
        $this->assertSame(ClientProjectQuotation::ACCEPTED_VIA_LINK, $quotation->accepted_via);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertSame(ClientProject::STATUS_PROPOSAL_ACCEPTED, $project->status);

        $this->get($url)
            ->assertOk()
            ->assertSee('Quote accepted', false)
            ->assertSee('now accepted', false);
    }

    public function test_second_accept_is_idempotent(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();
        $firstAcceptedAt = $quotation->fresh()->accepted_at;

        $this->post($url)
            ->assertOk()
            ->assertSee('Already accepted', false);

        $quotation->refresh();
        $this->assertTrue($quotation->isAccepted());
        $this->assertEquals($firstAcceptedAt?->timestamp, $quotation->accepted_at?->timestamp);
    }

    public function test_rejected_or_superseded_quote_cannot_be_accepted(): void
    {
        [, $rejected] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_REJECTED,
        ]);
        $rejectedUrl = URL::signedRoute('public.quotations.accept', ['quotation' => $rejected]);

        $this->get($rejectedUrl)
            ->assertOk()
            ->assertSee('cannot be accepted', false);

        $this->post($rejectedUrl)
            ->assertOk()
            ->assertSee('cannot be accepted', false);

        $this->assertSame(ClientProjectQuotation::STATUS_REJECTED, $rejected->fresh()->status);

        [$closedProject, $quote] = $this->makeLinkedQuotation(
            ['status' => ClientProjectQuotation::STATUS_SENT],
            ['status' => ClientProject::STATUS_CANCELLED],
        );
        $closedUrl = URL::signedRoute('public.quotations.accept', ['quotation' => $quote]);

        $this->post($closedUrl)
            ->assertOk()
            ->assertSee('cannot be accepted', false);
        $this->assertFalse($quote->fresh()->isAccepted());
        $this->assertSame(ClientProject::STATUS_CANCELLED, $closedProject->fresh()->status);

        [, $superseded] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_SUPERSEDED,
            'quote_number' => 'SQ-2026-0099',
            'quotation_number' => 'QUO-2026-0099',
        ]);
        $superUrl = URL::signedRoute('public.quotations.accept', ['quotation' => $superseded]);
        $this->post($superUrl)
            ->assertOk()
            ->assertSee('cannot be accepted', false);
        $this->assertSame(ClientProjectQuotation::STATUS_SUPERSEDED, $superseded->fresh()->status);
    }

    public function test_get_never_accepts_and_sends_noindex_robots_header(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Accept this quote', false)
            ->assertDontSee('Quote accepted', false);

        $quotation->refresh();
        $project->refresh();

        $this->assertFalse($quotation->isAccepted());
        $this->assertNull($quotation->accepted_at);
        $this->assertNull($quotation->accepted_via);
        $this->assertSame(ClientProject::STATUS_QUOTE_SENT, $project->status);
    }

    public function test_post_invalid_state_also_sends_noindex_robots_header(): void
    {
        [, $rejected] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_REJECTED,
        ]);
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $rejected]);

        $this->post($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('cannot be accepted', false);

        $this->assertFalse($rejected->fresh()->isAccepted());
    }

    public function test_pdf_accept_cta_is_a_link_without_the_raw_url_as_text(): void
    {
        [, $quotation] = $this->makeLinkedQuotation();
        $quotation->load('project');
        $acceptUrl = $quotation->signedAcceptUrl();

        $html = view('admin.client_projects.quotation.pdf', [
            'project' => $quotation->project,
            'quotation' => $quotation,
            'pdfPhotos' => collect(),
            'acceptUrl' => $acceptUrl,
        ])->render();

        $this->assertStringContainsString('Accept this quote', $html);
        $this->assertStringContainsString('quote-accept-cta', $html);
        $this->assertStringContainsString('href="'.$acceptUrl.'"', $html);
        $this->assertStringNotContainsString('quote-accept-url', $html);
        $this->assertStringNotContainsString('>'.$acceptUrl.'<', $html);
    }

    public function test_admin_acceptance_email_subject_uses_commas(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $mail = new ClientProjectQuoteAcceptedAdmin($quotation, $project->fresh());
        $subject = $mail->subjectLine();

        $this->assertSame(
            'Quote accepted, '.$quotation->displayQuoteNumber().', Ada Client',
            $subject
        );
        $this->assertStringNotContainsString('—', $subject);
        $this->assertStringNotContainsString('–', $subject);
    }

    public function test_signed_accept_emails_the_business_once(): void
    {
        Mail::fake();
        [$project, $quotation] = $this->makeLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();
        $this->post($url)->assertOk();

        Mail::assertSent(ClientProjectQuoteAcceptedAdmin::class, 1);
        Mail::assertSent(ClientProjectQuoteAcceptedAdmin::class, function (ClientProjectQuoteAcceptedAdmin $mail) use ($project, $quotation) {
            $mail->build();
            $html = view($mail->view, $mail->viewData)->render();
            $this->assertStringContainsString($project->client_name, $html);
            $this->assertStringContainsString($quotation->displayQuoteNumber(), $html);
            $this->assertStringContainsString('has accepted quote', $html);
            $this->assertStringContainsString('status has been changed to', $html);
            $this->assertStringContainsString('Accepted', $html);

            return $mail->hasTo(BusinessContact::DEFAULT_EMAIL);
        });
    }

    public function test_admin_accept_does_not_email_the_business_inbox(): void
    {
        Mail::fake();
        [, $quotation] = $this->makeLinkedQuotation();

        $status = app(QuoteAcceptanceService::class)->accept(
            $quotation,
            ClientProjectQuotation::ACCEPTED_VIA_ADMIN,
            '127.0.0.1',
            null,
        );

        $this->assertSame(QuoteAcceptanceService::STATUS_ACCEPTED, $status);
        Mail::assertNothingSent();
        Mail::assertNotSent(ClientProjectQuoteAcceptedClient::class);
    }

    public function test_signed_link_emails_the_client_once_and_still_notifies_the_business(): void
    {
        Mail::fake();
        [$project, $quotation] = $this->makeLinkedQuotation();
        $quoteNumber = $quotation->displayQuoteNumber();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();
        $this->post($url)->assertOk();

        Mail::assertSent(ClientProjectQuoteAcceptedClient::class, 1);
        Mail::assertSent(ClientProjectQuoteAcceptedAdmin::class, 1);
        Mail::assertSent(ClientProjectQuoteAcceptedClient::class, function (ClientProjectQuoteAcceptedClient $mail) use ($quoteNumber) {
            $mail->build();
            $html = view($mail->view, $mail->viewData)->render();
            $phone = BusinessContact::phoneDisplay();
            $subject = 'Thank you for choosing Zanini Renovations, quote '.$quoteNumber;

            $this->assertSame($subject, $mail->subjectLine());
            $this->assertSame($subject, $mail->subject);
            $this->assertStringContainsString('Hi Ada,', $html);
            $this->assertStringContainsString(
                'Thank you for accepting quote '.$quoteNumber.' for Kitchen renovation. We truly appreciate the trust you have placed in Zanini Renovations.',
                $html,
            );
            $this->assertStringContainsString(
                'We will be in touch shortly to take the next step together, including scheduling and the project details.',
                $html,
            );
            $this->assertStringContainsString(
                'If you have any questions in the meantime, simply reply to this email or call us at '.$phone.'.',
                $html,
            );
            $this->assertStringContainsString('Best regards,', $html);
            $this->assertStringContainsString('Jonas Zanini', $html);
            $this->assertStringContainsString('Zanini Renovations', $html);
            $this->assertStringContainsString(BusinessContact::email().' | zaninirenovations.ca', $html);
            $this->assertTrue($mail->hasReplyTo(BusinessContact::email()));

            return $mail->hasTo('ada@example.test');
        });

        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT,
            'recipient' => 'ada@example.test',
            'subject' => 'Thank you for choosing Zanini Renovations, quote '.$quoteNumber,
            'status' => ClientCommunicationLog::STATUS_SENT,
            'direction' => ClientCommunicationLog::DIRECTION_TO_CLIENT,
            'channel' => ClientCommunicationLog::CHANNEL_EMAIL,
        ]);
        $this->assertSame(1, ClientCommunicationLog::query()
            ->where('client_project_id', $project->id)
            ->where('reason', ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT)
            ->count());
        $this->assertSame(
            'Quote accepted (client)',
            ClientCommunicationLog::REASON_LABELS[ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT],
        );
    }

    public function test_client_first_name_falls_back_to_there(): void
    {
        Mail::fake();
        [, $quotation] = $this->makeLinkedQuotation([], ['client_name' => '']);
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();

        Mail::assertSent(ClientProjectQuoteAcceptedClient::class, function (ClientProjectQuoteAcceptedClient $mail) {
            $mail->build();
            $html = view($mail->view, $mail->viewData)->render();
            $this->assertSame('there', $mail->clientFirstName());
            $this->assertStringContainsString('Hi there,', $html);

            return true;
        });

        $blank = new ClientProjectQuoteAcceptedClient($quotation, new ClientProject([
            'client_name' => '   ',
            'name' => 'Kitchen renovation',
        ]));
        $this->assertSame('there', $blank->clientFirstName());

        $named = new ClientProjectQuoteAcceptedClient($quotation, new ClientProject([
            'client_name' => 'Ada Client',
            'name' => 'Kitchen renovation',
        ]));
        $this->assertSame('Ada', $named->clientFirstName());
    }

    public function test_question_sentence_drops_the_call_clause_without_a_phone(): void
    {
        $this->assertSame(
            'If you have any questions in the meantime, simply reply to this email.',
            ClientProjectQuoteAcceptedClient::questionSentence(''),
        );
        $this->assertSame(
            'If you have any questions in the meantime, simply reply to this email.',
            ClientProjectQuoteAcceptedClient::questionSentence('   '),
        );
        $this->assertSame(
            'If you have any questions in the meantime, simply reply to this email or call us at (647) 834-7570.',
            ClientProjectQuoteAcceptedClient::questionSentence('(647) 834-7570'),
        );
    }

    public function test_link_accept_skips_client_email_without_a_valid_address(): void
    {
        Mail::fake();
        Log::spy();
        [$project, $quotation] = $this->makeLinkedQuotation([], [
            'client_email' => 'not-an-email',
        ]);
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();

        $quotation->refresh();
        $this->assertTrue($quotation->isAccepted());
        Mail::assertNotSent(ClientProjectQuoteAcceptedClient::class);
        Mail::assertSent(ClientProjectQuoteAcceptedAdmin::class, 1);
        $this->assertDatabaseMissing('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT,
        ]);
        Log::shouldHaveReceived('info')
            ->with('Quote acceptance client email skipped: no valid client email', \Mockery::type('array'))
            ->once();
    }

    public function test_link_accept_ccs_the_secondary_client_email(): void
    {
        Mail::fake();
        [$project, $quotation] = $this->makeLinkedQuotation([], [
            'client_email_secondary' => 'spouse@example.test',
        ]);
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();

        Mail::assertSent(ClientProjectQuoteAcceptedClient::class, function (ClientProjectQuoteAcceptedClient $mail) {
            return $mail->hasTo('ada@example.test') && $mail->hasCc('spouse@example.test');
        });
        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT,
            'recipient' => 'ada@example.test',
            'cc' => 'spouse@example.test',
            'status' => ClientCommunicationLog::STATUS_SENT,
        ]);
    }

    public function test_api_accept_does_not_email_the_client(): void
    {
        Mail::fake();
        [, $quotation] = $this->makeLinkedQuotation();

        $status = app(QuoteAcceptanceService::class)->accept(
            $quotation,
            ClientProjectQuotation::ACCEPTED_VIA_API,
            '127.0.0.1',
            'gmail-msg-abc',
        );

        $this->assertSame(QuoteAcceptanceService::STATUS_ACCEPTED, $status);
        $this->assertTrue($quotation->fresh()->isAccepted());
        Mail::assertNotSent(ClientProjectQuoteAcceptedClient::class);
        Mail::assertNotSent(ClientProjectQuoteAcceptedAdmin::class);
    }

    public function test_client_mail_failure_does_not_undo_acceptance_and_logs_failure(): void
    {
        Log::spy();
        [$project, $quotation] = $this->makeLinkedQuotation();
        $quoteNumber = $quotation->displayQuoteNumber();

        Mail::shouldReceive('to')->andReturnUsing(function ($address, $name = null) {
            if ($address === 'ada@example.test') {
                throw new \RuntimeException('SMTP down');
            }

            $pending = \Mockery::mock();
            $pending->shouldReceive('send')->once()->andReturnNull();

            return $pending;
        });

        $status = app(QuoteAcceptanceService::class)->accept(
            $quotation,
            ClientProjectQuotation::ACCEPTED_VIA_LINK,
            '127.0.0.1',
        );

        $this->assertSame(QuoteAcceptanceService::STATUS_ACCEPTED, $status);
        $quotation->refresh();
        $project->refresh();
        $this->assertTrue($quotation->isAccepted());
        $this->assertSame(ClientProjectQuotation::ACCEPTED_VIA_LINK, $quotation->accepted_via);
        $this->assertSame(ClientProject::STATUS_PROPOSAL_ACCEPTED, $project->status);

        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_QUOTE_ACCEPTED_CLIENT,
            'recipient' => 'ada@example.test',
            'subject' => 'Thank you for choosing Zanini Renovations, quote '.$quoteNumber,
            'status' => ClientCommunicationLog::STATUS_FAILED,
            'direction' => ClientCommunicationLog::DIRECTION_TO_CLIENT,
            'error' => 'SMTP down',
        ]);
        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_QUOTE_ACCEPTED_ADMIN,
            'status' => ClientCommunicationLog::STATUS_SENT,
        ]);
        Log::shouldHaveReceived('warning')
            ->with('Quote acceptance client email failed', \Mockery::type('array'))
            ->once();
    }
}
