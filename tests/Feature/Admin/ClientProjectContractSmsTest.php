<?php

namespace Tests\Feature\Admin;

use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use Illuminate\Support\Facades\URL;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectContractSmsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_unsigned_contract_pdf_url_is_rejected(): void
    {
        $project = ClientProject::factory()->create([
            'client_phone' => '6134536943',
            'client_email' => null,
        ]);
        $this->createContract($project);

        $this->get(route('public.client-project.contract-pdf', $project))
            ->assertForbidden();
    }

    public function test_valid_signed_contract_pdf_url_is_allowed(): void
    {
        $project = ClientProject::factory()->create();
        $this->createContract($project);

        $url = URL::temporarySignedRoute(
            'public.client-project.contract-pdf',
            now()->addHour(),
            ['client_project' => $project],
        );

        $response = $this->get($url);
        // Signature must be accepted (not 403). Full PDF render needs PHP GD
        // (logo/signature PNGs); CI has it, some local PHP builds do not.
        $this->assertNotSame(403, $response->status());
        if (extension_loaded('gd')) {
            $response->assertOk();
        }
    }

    public function test_contract_sms_sends_link_without_email_or_marking_sent(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_phone' => '6134536943',
            'client_email' => null,
        ]);
        $contract = $this->createContract($project);

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->mock(ClientSmsService::class, function ($mock) {
            $mock->shouldReceive('sendToClient')
                ->once()
                ->withArgs(function ($proj, $to, $body, $reason) {
                    return (int) $proj->id > 0
                        && $to === '16134536943'
                        && str_contains($body, 'View PDF:')
                        && $reason === ClientCommunicationLog::REASON_CONTRACT_SMS_LINK;
                })
                ->andReturn(['ok' => true, 'detail' => 'sent', 'intro_sent' => false]);
        });

        $this->from(route('admin.client-projects.show', $project).'#contract')
            ->post(route('admin.client-projects.contract.sms', $project))
            ->assertRedirect(route('admin.client-projects.show', $project).'#contract');

        $contract->refresh();
        $this->assertNull($contract->sent_at);
        $this->assertSame(1, (int) $contract->version);
    }

    public function test_contract_sms_blocked_when_phone_missing(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_phone' => null,
            'client_email' => 'client@example.com',
        ]);
        $this->createContract($project);

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->from(route('admin.client-projects.show', $project).'#contract')
            ->post(route('admin.client-projects.contract.sms', $project))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_contract_tab_shows_sms_button_when_phone_present_without_email(): void
    {
        $this->actingAsAdmin();

        config(['mocean.api_token' => 'test-token']);

        $project = ClientProject::factory()->create([
            'client_phone' => '6134536943',
            'client_email' => null,
            'client_name' => 'Mandy & Allan',
            'client_address' => '123 Main St',
        ]);
        $this->createContract($project);

        $this->get(route('admin.client-projects.show', $project).'#contract')
            ->assertOk()
            ->assertSee('SMS', false)
            ->assertSee(route('admin.client-projects.contract.sms', $project), false)
            ->assertSee('You can still text a PDF link via SMS', false);
    }

    private function createContract(ClientProject $project): ClientProjectContract
    {
        return ClientProjectContract::query()->create([
            'client_project_id' => $project->id,
            'html_content' => '<p>Scope of work for the renovation.</p>',
            'version' => 1,
        ]);
    }
}
