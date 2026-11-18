<?php

namespace Tests\Feature\Automations;

use App\Mail\ClientProjectCommencementEveAdmin;
use App\Mail\ClientProjectCommencementEveClient;
use App\Models\ClientCommunicationLog;
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

        $this->spy(ClientSmsService::class)->shouldReceive('sendToClient')->never();
        $this->spy(MoceanSmsService::class)->shouldReceive('send')->never();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_admin_email_and_does_not_send_client_email_or_sms(): void
    {
        Mail::fake();
        config(['client_projects.commencement_eve_email_to' => 'admin@example.com']);

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

        Mail::assertSent(ClientProjectCommencementEveAdmin::class, function ($mail) use ($project) {
            return $mail->hasTo('admin@example.com')
                && $mail->project->is($project);
        });
        Mail::assertNotSent(ClientProjectCommencementEveClient::class);
        Mail::assertSent(ClientProjectCommencementEveAdmin::class, 1);

        $project->refresh();
        $this->assertSame('2026-06-11', $project->day_before_start_sent_for_date?->toDateString());
        $this->assertNoClientReminderWasRecorded($project);

        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
            'recipient' => 'admin@example.com',
            'status' => ClientCommunicationLog::STATUS_SENT,
            'direction' => ClientCommunicationLog::DIRECTION_TO_ADMIN,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        Mail::assertSent(ClientProjectCommencementEveAdmin::class, 1);
        Mail::assertNotSent(ClientProjectCommencementEveClient::class);
    }

    public function test_marks_sent_when_no_admin_address_is_configured(): void
    {
        Mail::fake();
        config(['client_projects.commencement_eve_email_to' => '']);

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'client_email' => 'client@example.com',
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNotSent(ClientProjectCommencementEveClient::class);
        Mail::assertNotSent(ClientProjectCommencementEveAdmin::class);

        $project->refresh();
        $this->assertSame('2026-06-11', $project->day_before_start_sent_for_date?->toDateString());
        $this->assertNoClientReminderWasRecorded($project);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_marks_sent_after_admin_email_fails_so_it_does_not_retry(): void
    {
        config(['client_projects.commencement_eve_email_to' => 'admin@example.com']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'client_email' => 'client@example.com',
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();

        $project->refresh();
        $this->assertSame('2026-06-11', $project->day_before_start_sent_for_date?->toDateString());
        $this->assertNoClientReminderWasRecorded($project);

        $this->assertDatabaseHas('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
            'status' => ClientCommunicationLog::STATUS_FAILED,
        ]);
        $this->assertDatabaseMissing('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_CLIENT,
        ]);
        $this->assertDatabaseMissing('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_SMS,
        ]);

        $this->artisan('client-projects:notify-commencement-eve')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_send_or_mark(): void
    {
        Mail::fake();
        config(['client_projects.commencement_eve_email_to' => 'admin@example.com']);

        $project = ClientProject::factory()->create([
            'start_date' => '2026-06-11',
            'client_email' => 'client@example.com',
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_SCHEDULED,
            'day_before_start_sent_for_date' => null,
        ]);

        $this->artisan('client-projects:notify-commencement-eve', ['--dry-run' => true])
            ->expectsOutputToContain('Client email and SMS are disabled.')
            ->expectsOutputToContain('admin email → admin@example.com')
            ->doesntExpectOutputToContain('client@example.com')
            ->assertSuccessful();

        Mail::assertNothingSent();

        $project->refresh();
        $this->assertNull($project->day_before_start_sent_for_date);
        $this->assertNoClientReminderWasRecorded($project);
    }

    private function assertNoClientReminderWasRecorded(ClientProject $project): void
    {
        $this->assertSame(
            0,
            $project->statusHistory()->where('note', 'like', '%reminder sent%')->count()
        );

        $this->assertDatabaseMissing('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_CLIENT,
        ]);
        $this->assertDatabaseMissing('client_communication_logs', [
            'client_project_id' => $project->id,
            'reason' => ClientCommunicationLog::REASON_COMMENCEMENT_EVE_SMS,
        ]);
    }
}
