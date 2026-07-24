<?php

namespace Tests\Feature\Automations;

use App\Mail\ClientProjectCommencementEveClient;
use App\Models\ClientProject;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;

class NotifyClientProjectCommencementEveTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00', 'America/Toronto'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_client_email_and_sms_the_day_before_start_date(): void
    {
        Mail::fake();
        config(['client_projects.commencement_eve_email_to' => '']);

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->mock(ClientSmsService::class, function ($mock) {
            $mock->shouldReceive('sendToClient')
                ->once()
                ->andReturn(['ok' => true, 'detail' => 'sent', 'intro_sent' => false]);
        });

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'end_date' => '2026-06-15',
            'client_email' => 'client@example.com',
            'client_phone' => '(613) 555-0100',
            'status' => ClientProject::STATUS_SCHEDULED,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        Mail::assertSent(ClientProjectCommencementEveClient::class, function ($mail) use ($project) {
            return $mail->hasTo('client@example.com')
                && $mail->project->is($project);
        });

        $project->refresh();
        $this->assertSame('2026-06-11', $project->day_before_start_sent_for_date?->toDateString());
    }

    public function test_marks_sent_when_client_email_succeeds_even_if_sms_fails(): void
    {
        Mail::fake();
        config(['client_projects.commencement_eve_email_to' => '']);

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->mock(ClientSmsService::class, function ($mock) {
            $mock->shouldReceive('sendToClient')
                ->once()
                ->andReturn(['ok' => false, 'detail' => 'gateway error', 'intro_sent' => false]);
        });

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'client_email' => 'client@example.com',
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        Mail::assertSent(ClientProjectCommencementEveClient::class);

        $project->refresh();
        $this->assertNull($project->day_before_start_sent_for_date);
    }

    public function test_marks_sent_when_client_sms_succeeds_even_if_admin_email_fails(): void
    {
        config(['client_projects.commencement_eve_email_to' => 'admin@example.com']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->mock(ClientSmsService::class, function ($mock) {
            $mock->shouldReceive('sendToClient')
                ->once()
                ->andReturn(['ok' => true, 'detail' => 'sent', 'intro_sent' => false]);
        });

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            // No client_email: the client channel under test here is SMS
            // only, so the (mocked, always-throwing) Mail::to() call is hit
            // exactly once — for the optional admin email — not twice.
            'client_email' => null,
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        $project->refresh();
        $this->assertSame('2026-06-11', $project->day_before_start_sent_for_date?->toDateString());
    }

    public function test_does_not_mark_sent_when_client_email_fails(): void
    {
        config(['client_projects.commencement_eve_email_to' => '']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->mock(MoceanSmsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'client_email' => 'client@example.com',
            'status' => ClientProject::STATUS_SCHEDULED,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        $project->refresh();
        $this->assertNull($project->day_before_start_sent_for_date);
    }
}
