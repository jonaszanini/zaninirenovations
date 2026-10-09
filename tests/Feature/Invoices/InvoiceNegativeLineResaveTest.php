<?php

namespace Tests\Feature\Invoices;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class InvoiceNegativeLineResaveTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_untouched_resave_of_standard_invoice_with_minus_200_keeps_pre_73_totals(): void
    {
        $this->actingAsAdmin();

        [$project, $invoice] = $this->makeStandardInvoiceWithDepositCredit();

        $this->get(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertOk()
            ->assertSee('data-cp-money-allow-negative', false)
            ->assertSee('-200.00', false);

        $this->put(
            route('admin.client-projects.invoices.update', [$project, $invoice]),
            [
                'intent' => 'draft',
                'hst_exempt' => '0',
                'manual_description' => 'Manual invoice',
                'manual_total' => '800.00',
                'items' => [
                    ['description' => 'Labour', 'amount' => '1000.00'],
                    ['description' => 'Deposit credit', 'amount' => '-200.00'],
                ],
            ]
        )->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEqualsWithDelta(800.00, (float) $invoice->subtotal, 0.0001);
        $this->assertEqualsWithDelta(104.00, (float) $invoice->hst, 0.0001);
        $this->assertEqualsWithDelta(904.00, (float) $invoice->total, 0.0001);
        $this->assertEqualsWithDelta(-200.00, (float) ($invoice->cleanItems()[1]['amount'] ?? 0), 0.0001);
    }

    public function test_untouched_resave_of_hourly_invoice_with_minus_200_keeps_pre_73_totals(): void
    {
        $this->actingAsAdmin();

        [$project, $invoice] = $this->makeHourlyInvoiceWithLabourCredit();

        $this->get(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertOk()
            ->assertSee('data-cp-money-allow-negative', false)
            ->assertSee('-200.00', false);

        $this->put(
            route('admin.client-projects.invoices.update', [$project, $invoice]),
            [
                'intent' => 'draft',
                'hst_exempt' => '0',
                'manual_description' => 'Hourly work',
                'items' => [
                    [
                        'description' => 'Hours',
                        'amount' => '1000.00',
                        'line_kind' => 'labour',
                    ],
                    [
                        'description' => 'Prepaid labour credit',
                        'amount' => '-200.00',
                        'line_kind' => 'labour_credit',
                    ],
                ],
            ]
        )->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEqualsWithDelta(800.00, (float) $invoice->subtotal, 0.0001);
        $this->assertEqualsWithDelta(104.00, (float) $invoice->hst, 0.0001);
        $this->assertEqualsWithDelta(904.00, (float) $invoice->total, 0.0001);
        $this->assertSame(800.0, $invoice->hourlyLabourTotalFromItems());
    }

    public function test_hourly_negative_materials_line_is_rejected_not_dropped(): void
    {
        $this->actingAsAdmin();
        [$project, $invoice] = $this->makeHourlyInvoiceWithLabourCredit();

        $this->from(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->put(
                route('admin.client-projects.invoices.update', [$project, $invoice]),
                [
                    'intent' => 'draft',
                    'hst_exempt' => '0',
                    'manual_description' => 'Hourly work',
                    'items' => [
                        ['description' => 'Hours', 'amount' => '1000.00', 'line_kind' => 'labour'],
                        ['description' => 'Typed line', 'amount' => '-50.00', 'line_kind' => 'materials'],
                    ],
                ]
            )
            ->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertSessionHasErrors('items');

        $invoice->refresh();
        $this->assertEqualsWithDelta(904.00, (float) $invoice->total, 0.0001);
        $this->assertCount(2, $invoice->cleanItems());
        $this->assertSame(ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT, $invoice->cleanItems()[1]['line_kind']);
    }

    public function test_hourly_negative_gas_line_is_rejected_not_dropped(): void
    {
        $this->actingAsAdmin();
        [$project, $invoice] = $this->makeHourlyInvoiceWithLabourCredit();

        $this->from(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->put(
                route('admin.client-projects.invoices.update', [$project, $invoice]),
                [
                    'intent' => 'draft',
                    'hst_exempt' => '0',
                    'manual_description' => 'Hourly work',
                    'items' => [
                        ['description' => 'Hours', 'amount' => '1000.00', 'line_kind' => 'labour'],
                        ['description' => 'Fuel', 'amount' => '-12.00', 'line_kind' => 'gas'],
                    ],
                ]
            )
            ->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertSessionHasErrors('items');

        $invoice->refresh();
        $this->assertEqualsWithDelta(904.00, (float) $invoice->total, 0.0001);
    }

    public function test_standard_invoice_rejects_negative_total(): void
    {
        $this->actingAsAdmin();
        [$project, $invoice] = $this->makeStandardInvoiceWithDepositCredit();

        $this->from(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->put(
                route('admin.client-projects.invoices.update', [$project, $invoice]),
                [
                    'intent' => 'draft',
                    'hst_exempt' => '0',
                    'manual_description' => 'Manual invoice',
                    'manual_total' => '800.00',
                    'items' => [
                        ['description' => 'Labour', 'amount' => '1000.00'],
                        ['description' => 'Credit', 'amount' => '-1234.50'],
                    ],
                ]
            )
            ->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertSessionHasErrors('items');

        $invoice->refresh();
        $this->assertEqualsWithDelta(904.00, (float) $invoice->total, 0.0001);
        $this->assertEqualsWithDelta(-200.00, (float) ($invoice->cleanItems()[1]['amount'] ?? 0), 0.0001);
        $this->assertSame(-30.49, round(-234.50 * ClientProjectInvoice::HST_RATE, 2));
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInvoice}
     */
    private function makeStandardInvoiceWithDepositCredit(): array
    {
        $project = ClientProject::factory()->create([
            'name' => 'Standard credit invoice',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => null,
            'installment_description' => 'Manual invoice',
            'items' => [
                ['description' => 'Labour', 'amount' => 1000.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => 'Deposit credit', 'amount' => -200.00],
            ],
            'subtotal' => 800.00,
            'hst' => 104.00,
            'total' => 904.00,
            'hst_exempt' => false,
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);

        return [$project, $invoice];
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInvoice}
     */
    private function makeHourlyInvoiceWithLabourCredit(): array
    {
        $project = ClientProject::factory()->create([
            'name' => 'Hourly credit invoice',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_HOURLY,
            'hourly_rate' => 50,
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => null,
            'installment_description' => 'Hourly work',
            'items' => [
                ['description' => 'Hours', 'amount' => 1000.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => 'Prepaid labour credit', 'amount' => -200.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT],
            ],
            'subtotal' => 800.00,
            'hst' => 104.00,
            'total' => 904.00,
            'hst_exempt' => false,
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);

        return [$project, $invoice];
    }
}
