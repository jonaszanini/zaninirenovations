<?php

namespace Tests\Feature\Admin;

use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Services\AdminDashboardService;
use App\Services\ClientProjectInvoiceReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class CancelledProjectInvoiceExclusionTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'America/Toronto'));
        config(['client_projects.past_due_invoice_reminders_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_excludes_cancelled_and_rejected_overdue_invoices_from_receivables(): void
    {
        $this->actingAsAdmin();

        $active = ClientProject::factory()->create([
            'name' => 'Active Kitchen Reno',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $active->id,
            'invoice_number' => 'INV-ACTIVE-001',
            'due_on' => now()->subDays(4)->toDateString(),
            'subtotal' => 2000.00,
            'hst' => 260.00,
            'total' => 2260.00,
        ]);

        $cancelled = ClientProject::factory()->create([
            'name' => 'Cancelled Closet Doors',
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $cancelled->id,
            'invoice_number' => 'INV-CANCEL-0238',
            'due_on' => now()->subDays(50)->toDateString(),
            'subtotal' => 1050.37,
            'hst' => 136.55,
            'total' => 1186.92,
        ]);

        $rejected = ClientProject::factory()->create([
            'name' => 'Rejected Fence Job',
            'status' => ClientProject::STATUS_REJECTED,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $rejected->id,
            'invoice_number' => 'INV-REJECT-0099',
            'due_on' => now()->subDays(10)->toDateString(),
            'subtotal' => 500.00,
            'hst' => 65.00,
            'total' => 565.00,
        ]);

        $payload = app(AdminDashboardService::class)->build(true);

        $this->assertSame(1, $payload['cards']['overdue_invoice_count']);
        $this->assertEquals(2260.00, $payload['cards']['overdue_invoice_total']);
        $this->assertEquals(2260.00, $payload['cards']['outstanding_receivables']);

        $overdueNumbers = collect($payload['overdue_invoices'])->pluck('invoice_number')->all();
        $this->assertContains('INV-ACTIVE-001', $overdueNumbers);
        $this->assertNotContains('INV-CANCEL-0238', $overdueNumbers);
        $this->assertNotContains('INV-REJECT-0099', $overdueNumbers);

        $attentionNames = collect($payload['attention']['alerts'])->pluck('name')->all();
        $this->assertContains('Active Kitchen Reno', $attentionNames);
        $this->assertNotContains('Cancelled Closet Doors', $attentionNames);
        $this->assertNotContains('Rejected Fence Job', $attentionNames);

        $upcomingNames = collect($payload['upcoming'])->pluck('name')->all();
        $this->assertNotContains('Cancelled Closet Doors', $upcomingNames);

        $this->get(route('admin.dashboard', ['refresh' => 1]))
            ->assertOk()
            ->assertSee('INV-ACTIVE-001')
            ->assertDontSee('INV-CANCEL-0238')
            ->assertDontSee('INV-REJECT-0099')
            ->assertDontSee('Cancelled Closet Doors');
    }

    public function test_collectible_scope_drops_cancelled_and_rejected_project_invoices(): void
    {
        $active = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        $activeInvoice = ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $active->id,
            'total' => 100.00,
        ]);

        $cancelled = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        $cancelledInvoice = ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $cancelled->id,
            'total' => 200.00,
        ]);

        $ids = ClientProjectInvoice::query()->collectible()->pluck('id');

        $this->assertTrue($ids->contains($activeInvoice->id));
        $this->assertFalse($ids->contains($cancelledInvoice->id));
        $this->assertTrue($activeInvoice->fresh()->isCollectible());
        $this->assertFalse($cancelledInvoice->fresh()->isCollectible());
        $this->assertTrue($cancelled->excludesInvoicesFromReceivables());
        $this->assertFalse($active->excludesInvoicesFromReceivables());
    }

    public function test_global_financials_outstanding_excludes_cancelled_project_invoices(): void
    {
        $this->actingAsAdmin();

        $active = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $active->id,
            'invoice_number' => 'INV-FIN-ACTIVE',
            'total' => 1130.00,
        ]);

        $cancelled = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $cancelled->id,
            'invoice_number' => 'INV-FIN-CANCEL',
            'total' => 1186.92,
        ]);

        $this->get(route('admin.financials.invoices'))
            ->assertOk()
            ->assertSee('INV-FIN-ACTIVE')
            ->assertSee('INV-FIN-CANCEL')
            ->assertSee(ClientProject::formatMoney(1130.00))
            ->assertDontSee(ClientProject::formatMoney(2316.92));
    }

    public function test_daily_reminder_job_skips_cancelled_and_rejected_project_invoices(): void
    {
        Mail::fake();

        $cancelled = ClientProject::factory()->create([
            'client_email' => 'cancelled@example.com',
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        ClientProjectInvoice::factory()->for($cancelled, 'project')->sent()->create([
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        $rejected = ClientProject::factory()->create([
            'client_email' => 'rejected@example.com',
            'status' => ClientProject::STATUS_REJECTED,
        ]);
        ClientProjectInvoice::factory()->for($rejected, 'project')->sent()->create([
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        $active = ClientProject::factory()->create([
            'client_email' => 'active@example.com',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        $activeInvoice = ClientProjectInvoice::factory()->for($active, 'project')->sent()->create([
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('client-projects:send-past-due-invoice-reminders')
            ->assertSuccessful();

        Mail::assertSent(ClientProjectInvoiceReminderClient::class, 1);
        Mail::assertSent(ClientProjectInvoiceReminderClient::class, function ($mail) use ($active, $activeInvoice) {
            return $mail->hasTo('active@example.com')
                && $mail->project->is($active)
                && $mail->invoice->is($activeInvoice);
        });
        Mail::assertNotSent(ClientProjectInvoiceReminderClient::class, function ($mail) {
            return $mail->hasTo('cancelled@example.com') || $mail->hasTo('rejected@example.com');
        });

        $pastDue = app(ClientProjectInvoiceReminderService::class)->pastDueSentInvoices();
        $this->assertCount(1, $pastDue);
        $this->assertTrue($pastDue->first()->is($activeInvoice));
    }

    public function test_manual_reminder_is_refused_for_cancelled_project_invoice(): void
    {
        $this->actingAsAdmin();
        Mail::fake();

        $project = ClientProject::factory()->create([
            'client_email' => 'cancelled@example.com',
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.reminder', [$project, $invoice]))
            ->assertRedirect(route('admin.client-projects.show', $project).'#invoice')
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_cancelled_project_invoice_tab_shows_existing_invoices_read_only(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Cancelled Closet Doors',
            'status' => ClientProject::STATUS_CANCELLED,
        ]);
        $invoice = ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $project->id,
            'invoice_number' => 'INV-2026-0238',
            'due_on' => '2026-08-14',
            'subtotal' => 1050.37,
            'hst' => 136.55,
            'total' => 1186.92,
        ]);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertDontSee('Invoices are locked')
            ->assertSee('Existing invoices')
            ->assertSee('INV-2026-0238')
            ->assertSee('CA$1,186.92')
            ->assertSee('Delete')
            ->assertSee(route('admin.client-projects.invoices.pdf', [$project, $invoice]), false)
            ->assertDontSee('New manual invoice')
            ->assertDontSee('Create Invoice')
            ->assertDontSee('Create invoice from hours');

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.create', $project), [
                'mode' => 'manual',
            ])
            ->assertRedirect(route('admin.client-projects.show', $project).'#invoice')
            ->assertSessionHas('error');

        $this->from(route('admin.client-projects.show', $project))
            ->delete(route('admin.client-projects.invoices.destroy', [$project, $invoice]))
            ->assertRedirect(route('admin.client-projects.show', $project).'#invoice')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('client_project_invoices', ['id' => $invoice->id]);
    }
}
