<?php

namespace Tests\Feature\Etransfer;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use Tests\IntegrationTestCase;

class ApiEtransferNoticeTest extends IntegrationTestCase
{
    private const TOKEN = 'test-etransfer-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['etransfer.api_token' => self::TOKEN]);
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->postJson('/api/etransfers', [
            'sender_name' => 'Jane Citizen',
            'amount' => 100,
        ], ['Authorization' => 'Bearer wrong-token'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');

        $this->assertSame(0, EtransferNotice::query()->count());
    }

    public function test_missing_token_is_rejected(): void
    {
        $this->postJson('/api/etransfers', [
            'sender_name' => 'Jane Citizen',
            'amount' => 100,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'unauthorized');
    }

    public function test_blank_configured_token_rejects_all_requests(): void
    {
        config(['etransfer.api_token' => '']);

        $this->postJson('/api/etransfers', [
            'sender_name' => 'Jane Citizen',
            'amount' => 100,
        ], $this->authHeaders())
            ->assertUnauthorized();
    }

    public function test_structured_payload_creates_pending_notice(): void
    {
        $project = ClientProject::factory()->create([
            'client_name' => 'Jane Citizen',
            'client_email' => 'jane.citizen@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0001',
            'subtotal' => 1000,
            'hst' => 130,
            'total' => 1130,
        ]);

        $response = $this->postJson('/api/etransfers', [
            'sender_name' => 'Jane Citizen',
            'sender_email' => 'jane.citizen@example.test',
            'amount' => 1130.00,
            'reference' => 'CASTRUCT001',
            'message' => 'INV-2026-0001 kitchen',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('notice.status', EtransferNotice::STATUS_PENDING)
            ->assertJsonPath('notice.amount', 1130)
            ->assertJsonPath('notice.invoice_id', null);

        $this->assertNotEmpty($response->json('candidates'));
        $this->assertSame($invoice->id, $response->json('candidates.0.invoice_id'));
        $this->assertFalse($invoice->fresh()->isPaid());
    }

    public function test_raw_text_is_parsed(): void
    {
        $text = <<<'TXT'
Jane Citizen sent you $1,130.00 CAD
Message: INV-2026-0888
Reference Number: CAPARSE0001
Sent: October 6, 2026 at 4:32 PM ET
TXT;

        $this->postJson('/api/etransfers', [
            'raw_text' => $text,
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('notice.sender_name', 'Jane Citizen')
            ->assertJsonPath('notice.amount', 1130)
            ->assertJsonPath('notice.reference', 'CAPARSE0001')
            ->assertJsonPath('notice.message', 'INV-2026-0888')
            ->assertJsonPath('notice.status', 'pending');
    }

    public function test_duplicate_reference_does_not_insert_twice(): void
    {
        $payload = [
            'sender_name' => 'Jane Citizen',
            'amount' => 200.00,
            'reference' => 'CADEDUPE0001',
        ];

        $this->postJson('/api/etransfers', $payload, $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('status', 'created');

        $this->postJson('/api/etransfers', $payload, $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, EtransferNotice::query()->count());
    }

    public function test_missing_amount_and_raw_text_is_invalid(): void
    {
        $this->postJson('/api/etransfers', [
            'sender_name' => 'Jane Citizen',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }
}
