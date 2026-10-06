<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
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
}
