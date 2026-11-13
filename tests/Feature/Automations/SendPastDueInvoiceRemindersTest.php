<?php

namespace Tests\Feature\Automations;

use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;

class SendPastDueInvoiceRemindersTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-16 09:00:00', 'America/Toronto'));
        config(['client_projects.past_due_invoice_reminders_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_email_reminder_for_past_due_sent_invoice(): void
    {
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.com',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);

        $invoice = ClientProjectInvoice::factory()
            ->for($project, 'project')
            ->sent()
            ->create([
                'due_on' => '2026-06-10',
            ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders')
            ->assertSuccessful();

        Mail::assertSent(ClientProjectInvoiceReminderClient::class, function ($mail) use ($project, $invoice) {
            return $mail->hasTo('client@example.com')
                && $mail->project->is($project)
                && $mail->invoice->is($invoice);
        });
    }

    public function test_skips_sent_invoices_with_a_null_due_date(): void
    {
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.com',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);

        ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'due_on' => null,
            'total' => 452.00,
        ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertTrue(
            app(\App\Services\ClientProjectInvoiceReminderService::class)->pastDueSentInvoices()->isEmpty()
        );
    }

    public function test_skips_paid_and_not_yet_due_invoices(): void
    {
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.com',
        ]);

        ClientProjectInvoice::factory()->for($project, 'project')->paid()->create([
            'due_on' => '2026-06-10',
        ]);

        ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'due_on' => '2026-06-20',
        ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dry_run_lists_matches_without_sending(): void
    {
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.com',
        ]);

        ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'due_on' => '2026-06-10',
        ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders', ['--dry-run' => true])
            ->assertSuccessful();

        Mail::assertNothingSent();
    }
}
