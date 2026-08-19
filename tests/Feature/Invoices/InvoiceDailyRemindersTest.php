<?php

namespace Tests\Feature\Invoices;

use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class InvoiceDailyRemindersTest extends IntegrationTestCase
{
    use ActsAsAdmin;

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

    public function test_daily_job_skips_invoices_with_reminders_paused(): void
    {
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'client@example.com',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);

        ClientProjectInvoice::factory()
            ->for($project, 'project')
            ->sent()
            ->remindersPaused()
            ->create([
                'due_on' => '2026-06-10',
            ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_admin_can_pause_and_resume_daily_reminders(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        $invoice = ClientProjectInvoice::factory()
            ->for($project, 'project')
            ->sent()
            ->create([
                'due_on' => '2026-06-10',
            ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertNotNull($invoice->reminders_paused_at);
        $this->assertTrue($invoice->dailyRemindersPaused());

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertNull($invoice->reminders_paused_at);
        $this->assertFalse($invoice->dailyRemindersPaused());
    }

    public function test_deleting_unpaid_invoice_keeps_the_project_and_allows_recreate(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        $invoice = ClientProjectInvoice::factory()
            ->for($project, 'project')
            ->sent()
            ->create([
                'installment_number' => 1,
                'installment_description' => 'On signing (holds the schedule)',
            ]);

        $this->from(route('admin.client-projects.show', $project))
            ->delete(route('admin.client-projects.invoices.destroy', [$project, $invoice]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('client_project_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('client_projects', ['id' => $project->id]);
    }
}
