<?php

namespace Tests\Feature\Admin;

use App\Mail\ClientProjectInvoiceClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\PrepareBillingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PrepareBillingTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_prepare_billing(): void
    {
        $project = $this->acceptedProjectWithPlan();

        $this->get(route('admin.client-projects.billing.prepare', $project))
            ->assertRedirect(route('login'));
        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect(route('login'));
        $this->assertSame(0, $project->invoices()->count());
    }

    public function test_unaccepted_and_declined_quotes_cannot_create_drafts(): void
    {
        $this->actingAsAdmin();

        $unaccepted = $this->makeProjectWithQuote(ClientProjectQuotation::STATUS_SENT, ClientProject::STATUS_QUOTE_SENT);
        $this->get(route('admin.client-projects.billing.prepare', $unaccepted))
            ->assertOk()
            ->assertSee('Accept a quotation before preparing billing.', false);
        $this->post(route('admin.client-projects.billing.confirm', $unaccepted), [
            'due_on' => [1 => '2026-12-01'],
        ])->assertRedirect(route('admin.client-projects.billing.prepare', $unaccepted));
        $this->assertSame(0, $unaccepted->invoices()->count());

        $declined = $this->makeProjectWithQuote(ClientProjectQuotation::STATUS_REJECTED, ClientProject::STATUS_QUOTE_SENT);
        $this->get(route('admin.client-projects.billing.prepare', $declined))
            ->assertOk()
            ->assertSee('Accept a quotation before preparing billing.', false);
        $this->post(route('admin.client-projects.billing.confirm', $declined), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertSessionHasErrors();
        $this->assertSame(0, $declined->invoices()->count());
    }

    public function test_hourly_and_cancelled_projects_are_blocked(): void
    {
        $this->actingAsAdmin();

        $hourly = $this->acceptedProjectWithPlan(['billing_type' => ClientProject::BILLING_HOURLY]);
        $this->get(route('admin.client-projects.billing.prepare', $hourly))
            ->assertOk()
            ->assertSee('Hourly projects bill from logged hours', false);
        $this->post(route('admin.client-projects.billing.confirm', $hourly), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertSessionHasErrors();
        $this->assertSame(0, $hourly->invoices()->count());

        $cancelled = $this->acceptedProjectWithPlan(['status' => ClientProject::STATUS_CANCELLED]);
        $this->get(route('admin.client-projects.billing.prepare', $cancelled))
            ->assertOk()
            ->assertSee('Billing is locked', false);
        $this->post(route('admin.client-projects.billing.confirm', $cancelled), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertSessionHasErrors();
        $this->assertSame(0, $cancelled->invoices()->count());
    }

    public function test_admin_accept_then_prepare_review_creates_same_drafts_in_financials_and_invoices(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProjectWithQuote(ClientProjectQuotation::STATUS_SENT, ClientProject::STATUS_QUOTE_SENT);
        $quotation = $project->quotations()->first();

        $this->post(route('admin.client-projects.quotation.status', [$project, $quotation]), [
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
        ])->assertRedirect()
            ->assertSessionHas('success', function ($message) {
                return is_string($message)
                    && str_contains($message, 'Prepare billing')
                    && str_contains($message, 'nothing is issued');
            });

        $project->refresh();
        $this->assertTrue($project->isAccepted());
        $this->assertSame(2, $project->installments()->count());
        $this->assertSame(0, $project->invoices()->count());

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('Prepare billing', false)
            ->assertSee('Create invoice for next installment', false)
            ->assertSee('Scheduled', false)
            ->assertSee('Draft', false)
            ->assertSee('Issued', false)
            ->assertSee('Outstanding', false);

        $prepare = $this->get(route('admin.client-projects.billing.prepare', $project));
        $prepare->assertOk()
            ->assertSee('Review installment drafts', false)
            ->assertSee('Confirm draft invoices', false)
            ->assertSee('Will create draft', false)
            ->assertSee('CAD', false);

        $this->post(route('admin.client-projects.billing.confirm', $project), [])
            ->assertRedirect(route('admin.client-projects.billing.prepare', $project))
            ->assertSessionHasErrors();
        $this->assertSame(0, $project->invoices()->count());

        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect(route('admin.client-projects.show', $project).'#invoice')
            ->assertSessionHas('success');

        $invoices = $project->invoices()->orderBy('installment_number')->get();
        $this->assertCount(2, $invoices);
        foreach ($invoices as $invoice) {
            $this->assertTrue($invoice->isDraft());
            $this->assertNotNull($invoice->invoice_number);
            $this->assertNotNull($invoice->due_on);
        }
        $this->assertEqualsWithDelta(400.00, (float) $invoices[0]->subtotal, 0.01);
        $this->assertEqualsWithDelta(52.00, (float) $invoices[0]->hst, 0.01);
        $this->assertEqualsWithDelta(452.00, (float) $invoices[0]->total, 0.01);
        $this->assertEqualsWithDelta(600.00, (float) $invoices[1]->subtotal, 0.01);
        $this->assertEqualsWithDelta(78.00, (float) $invoices[1]->hst, 0.01);
        $this->assertEqualsWithDelta(678.00, (float) $invoices[1]->total, 0.01);

        $show = $this->get(route('admin.client-projects.show', $project));
        $show->assertOk()
            ->assertSee($invoices[0]->invoice_number, false)
            ->assertSee($invoices[1]->invoice_number, false)
            ->assertSee('Draft', false);

        $this->get(route('admin.financials.invoices', ['status' => 'draft']))
            ->assertOk()
            ->assertSee($invoices[0]->invoice_number, false)
            ->assertSee($invoices[1]->invoice_number, false)
            ->assertSee('CA$0.00', false);

        $this->assertEqualsWithDelta(
            0.0,
            (float) ClientProjectInvoice::query()->collectible()->where('status', ClientProjectInvoice::STATUS_SENT)->sum('total'),
            0.001
        );

        $totals = app(PrepareBillingService::class)->totals($project->fresh()->load('invoices', 'installments'));
        $this->assertEqualsWithDelta(1130.00, $totals['scheduled'], 0.01);
        $this->assertEqualsWithDelta(1130.00, $totals['draft'], 0.01);
        $this->assertEqualsWithDelta(0.0, $totals['issued'], 0.01);
        $this->assertEqualsWithDelta(0.0, $totals['outstanding'], 0.01);
        $this->assertEqualsWithDelta($totals['draft'] + $totals['issued'] + $totals['paid'], $totals['scheduled'], 0.01);
    }

    public function test_client_acceptance_then_admin_prepare_billing_is_coherent(): void
    {
        $project = $this->makeProjectWithQuote(ClientProjectQuotation::STATUS_SENT, ClientProject::STATUS_QUOTE_SENT);
        $quotation = $project->quotations()->first();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->post($url)->assertRedirect();
        $this->get($url)
            ->assertOk()
            ->assertSee('now accepted', false)
            ->assertDontSee('Prepare billing', false)
            ->assertDontSee('Confirm draft invoices', false);

        $this->assertSame(0, $project->invoices()->count());

        $this->actingAsAdmin();
        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('Prepare billing', false);

        $this->post(route('admin.client-projects.billing.confirm', $project->fresh()), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();

        $this->assertSame(2, $project->invoices()->where('status', ClientProjectInvoice::STATUS_DRAFT)->count());
    }

    public function test_confirm_is_idempotent_and_does_not_duplicate_or_overbill(): void
    {
        $this->actingAsAdmin();
        $project = $this->acceptedProjectWithPlan();
        $this->assertGreaterThan(0, $project->installments()->count(), 'expected seeded payment plan');
        $payload = ['due_on' => [1 => '2026-12-01', 2 => '2027-01-15']];

        $this->post(route('admin.client-projects.billing.confirm', $project), $payload)->assertRedirect();
        $ids = $project->invoices()->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);

        $this->post(route('admin.client-projects.billing.confirm', $project), $payload)->assertRedirect();
        $this->assertEquals($ids, $project->invoices()->orderBy('id')->pluck('id')->all());
        $this->assertCount(2, $project->fresh()->invoices);

        app(PrepareBillingService::class)->confirm($project->fresh(), $payload['due_on']);
        $this->assertCount(2, $project->fresh()->invoices);
        $this->assertEqualsWithDelta(
            1130.00,
            (float) $project->invoices()->sum('total'),
            0.01
        );
    }

    public function test_next_installment_reuses_existing_draft_then_advances(): void
    {
        $this->actingAsAdmin();
        $project = $this->acceptedProjectWithPlan();
        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();

        $first = $project->invoices()->where('installment_number', 1)->first();
        $second = $project->invoices()->where('installment_number', 2)->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $this->post(route('admin.client-projects.invoices.create', $project), ['mode' => 'next'])
            ->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $first]));

        $first->status = ClientProjectInvoice::STATUS_SENT;
        $first->sent_at = now();
        $first->save();

        $this->post(route('admin.client-projects.invoices.create', $project), ['mode' => 'next'])
            ->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $second]));
    }

    public function test_legacy_sent_and_paid_installments_are_not_rebilled(): void
    {
        $this->actingAsAdmin();
        $project = $this->acceptedProjectWithPlan();

        $legacy = ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $project->id,
            'installment_number' => 1,
            'invoice_number' => 'INV-LEGACY-0001',
            'subtotal' => 400,
            'hst' => 52,
            'total' => 452,
            'due_on' => '2026-05-01',
        ]);

        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();

        $this->assertSame('INV-LEGACY-0001', $legacy->fresh()->invoice_number);
        $this->assertTrue($legacy->fresh()->isSent());
        $this->assertSame(1, $project->invoices()->where('installment_number', 1)->count());
        $created = $project->invoices()->where('installment_number', 2)->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->isDraft());
        $this->assertNotSame('INV-LEGACY-0001', $created->invoice_number);

        $created->status = ClientProjectInvoice::STATUS_PAID;
        $created->paid_at = now();
        $created->paid_amount = $created->total;
        $created->save();

        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();
        $this->assertCount(2, $project->fresh()->invoices);
        $this->assertEqualsWithDelta(
            452.00,
            (float) ClientProjectInvoice::query()->collectible()->where('status', ClientProjectInvoice::STATUS_SENT)->sum('total'),
            0.01
        );
    }

    public function test_stale_quote_vs_plan_conflict_is_surfaced_not_recalculated(): void
    {
        $this->actingAsAdmin();
        $project = $this->acceptedProjectWithPlan();
        $quotation = $project->quotations()->first();
        $quotation->payment_percentages = [10, 90];
        $quotation->save();

        $planPct = (float) $project->installments()->orderBy('sort_order')->first()->percentage;
        $this->assertEqualsWithDelta(40.0, $planPct, 0.01);

        $this->get(route('admin.client-projects.billing.prepare', $project->fresh()->load(['installments', 'invoices', 'quotations'])))
            ->assertOk()
            ->assertSee('do not match', false)
            ->assertSee('not recalculated', false);

        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect(route('admin.client-projects.billing.prepare', $project))
            ->assertSessionHasErrors();
        $this->assertSame(0, $project->invoices()->count());
        $this->assertEqualsWithDelta(40.0, (float) $project->fresh()->installments()->orderBy('sort_order')->first()->percentage, 0.01);
    }

    public function test_explicit_issue_path_still_requires_send_and_does_not_email_on_prepare(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->actingAsAdmin();
        $project = $this->acceptedProjectWithPlan();

        $this->post(route('admin.client-projects.billing.confirm', $project), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();

        Mail::assertNothingSent();

        $invoice = $project->invoices()->where('installment_number', 1)->first();
        $this->get(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertOk()
            ->assertSee('Save Draft', false)
            ->assertSee('Draft', false);

        $this->mock(InvoicePdfService::class, function ($mock) {
            $pdf = new class
            {
                public function output(): string
                {
                    return '%PDF-fake';
                }
            };
            $mock->shouldReceive('renderInvoicePdf')->andReturn($pdf);
            $mock->shouldReceive('invoicePdfStoragePath')->andReturn('invoices/test.pdf');
            $mock->shouldReceive('invoicePdfFilename')->andReturn('invoice.pdf');
        });

        $this->post(route('admin.client-projects.invoices.send', [$project, $invoice]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertTrue($invoice->isSent());
        Mail::assertSent(ClientProjectInvoiceClient::class);
        $this->assertEqualsWithDelta(
            (float) $invoice->total,
            (float) ClientProjectInvoice::query()->collectible()->where('status', ClientProjectInvoice::STATUS_SENT)->sum('total'),
            0.01
        );

        $stillDraft = $project->invoices()->where('installment_number', 2)->first();
        $this->assertTrue($stillDraft->isDraft());
        $totals = app(PrepareBillingService::class)->totals($project->fresh()->load(['invoices', 'installments']));
        $this->assertEqualsWithDelta((float) $invoice->total, $totals['issued'], 0.01);
        $this->assertEqualsWithDelta((float) $stillDraft->total, $totals['draft'], 0.01);
        $this->assertEqualsWithDelta((float) $invoice->total, $totals['outstanding'], 0.01);
        $this->assertEqualsWithDelta(1130.00, $totals['scheduled'], 0.01);
    }

    public function test_other_project_invoices_are_isolated(): void
    {
        $this->actingAsAdmin();
        $a = $this->acceptedProjectWithPlan();
        $b = $this->acceptedProjectWithPlan();

        $this->post(route('admin.client-projects.billing.confirm', $a), [
            'due_on' => [1 => '2026-12-01', 2 => '2027-01-15'],
        ])->assertRedirect();

        $this->assertSame(2, $a->invoices()->count());
        $this->assertSame(0, $b->invoices()->count());
        $invoice = $a->invoices()->first();
        $this->get(route('admin.client-projects.invoices.edit', [$b, $invoice]))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $projectOverrides
     */
    private function acceptedProjectWithPlan(array $projectOverrides = []): ClientProject
    {
        $project = $this->makeProjectWithQuote(
            ClientProjectQuotation::STATUS_ACCEPTED,
            $projectOverrides['status'] ?? ClientProject::STATUS_PROPOSAL_ACCEPTED,
            $projectOverrides
        );
        $project->syncFixedContractTotalsFromQuotation();
        $project->refresh();

        return $project->load(['installments', 'invoices', 'quotations']);
    }

    /**
     * @param  array<string, mixed>  $projectOverrides
     */
    private function makeProjectWithQuote(
        string $quoteStatus,
        string $projectStatus,
        array $projectOverrides = []
    ): ClientProject {
        $project = ClientProject::factory()->create(array_merge([
            'status' => $projectStatus,
            'billing_type' => ClientProject::BILLING_FIXED,
            'payment_plan' => 1,
            'client_email' => 'client@example.test',
        ], $projectOverrides));

        ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Standard quotation',
            'status' => $quoteStatus,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'items' => [
                [
                    'description' => 'Labour and materials',
                    'amount' => 1000,
                    'apply_hst' => true,
                ],
            ],
            'payment_installments' => 2,
            'payment_percentages' => [40, 60],
        ]);

        return $project->fresh()->load(['quotations', 'installments', 'invoices']);
    }
}
