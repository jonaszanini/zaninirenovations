<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\QuoteAcceptanceService;
use Tests\IntegrationTestCase;

class ApiAcceptTest extends IntegrationTestCase
{
    use MakesAcceptableQuotations;

    private const TOKEN = 'test-quote-accept-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['quote_accept.api_token' => self::TOKEN]);
    }

    public function test_correct_token_accepts_quote(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
            'source_note' => 'gmail-msg-abc',
        ], $this->authHeaders())
            ->assertOk()
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_ACCEPTED]);

        $quotation->refresh();
        $project->refresh();
        $this->assertTrue($quotation->isAccepted());
        $this->assertSame(ClientProjectQuotation::ACCEPTED_VIA_API, $quotation->accepted_via);
        $this->assertSame('gmail-msg-abc', $quotation->accepted_source_note);
        $this->assertSame(ClientProject::STATUS_PROPOSAL_ACCEPTED, $project->status);
    }

    public function test_second_api_call_is_already_accepted(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();
        $payload = [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ];

        $this->postJson('/api/quotations/accept', $payload, $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('status', QuoteAcceptanceService::STATUS_ACCEPTED);

        $this->postJson('/api/quotations/accept', $payload, $this->authHeaders())
            ->assertOk()
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_ALREADY_ACCEPTED]);
    }

    public function test_wrong_token_is_rejected(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ], ['Authorization' => 'Bearer wrong-token'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');

        $this->assertFalse($quotation->fresh()->isAccepted());
    }

    public function test_missing_token_is_rejected(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');
    }

    public function test_not_found_for_unknown_project_or_quote(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        $this->postJson('/api/quotations/accept', [
            'project_number' => 'PRJ99999',
            'quote_number' => $quotation->displayQuoteNumber(),
        ], $this->authHeaders())
            ->assertNotFound()
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_NOT_FOUND]);

        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => 'SQ-1999-0000',
        ], $this->authHeaders())
            ->assertNotFound()
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_NOT_FOUND]);
    }

    public function test_invalid_state_for_rejected_quote(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_REJECTED,
        ]);

        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ], $this->authHeaders())
            ->assertStatus(409)
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_INVALID_STATE]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }
}
