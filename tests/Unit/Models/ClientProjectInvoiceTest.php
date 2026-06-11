<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectInvoice;
use PHPUnit\Framework\TestCase;

class ClientProjectInvoiceTest extends TestCase
{
    public function test_clean_items_drops_blank_rows_and_preserves_line_kind(): void
    {
        $invoice = new ClientProjectInvoice([
            'items' => [
                ['description' => 'Labour', 'amount' => 500, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => '', 'amount' => 0],
                ['description' => 'Materials', 'amount' => 113, 'line_kind' => ClientProjectInvoice::LINE_KIND_MATERIALS],
            ],
        ]);

        $clean = $invoice->cleanItems();

        $this->assertCount(2, $clean);
        $this->assertSame(ClientProjectInvoice::LINE_KIND_MATERIALS, $clean[1]['line_kind']);
    }

    public function test_hourly_labour_total_subtracts_labour_credit(): void
    {
        $invoice = new ClientProjectInvoice([
            'items' => [
                ['description' => 'Hours', 'amount' => 800, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ['description' => 'Prepaid', 'amount' => 200, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT],
                ['description' => 'Screws', 'amount' => 50, 'line_kind' => ClientProjectInvoice::LINE_KIND_MATERIALS],
            ],
        ]);

        $this->assertSame(600.0, $invoice->hourlyLabourTotalFromItems());
        $this->assertSame(50.0, $invoice->hourlyMaterialsTotalFromItems());
    }

    public function test_effective_discount_caps_at_gross_subtotal(): void
    {
        $invoice = new ClientProjectInvoice([
            'installment_number' => 1,
            'items' => [
                ['description' => 'Deposit', 'amount' => 100],
            ],
            'discount_amount' => 500,
        ]);

        $this->assertSame(100.0, $invoice->effectiveDiscountAmount());
    }

    /**
     * @dataProvider paymentMethodLabelProvider
     */
    public function test_payment_method_labels(?string $method, ?string $expected): void
    {
        $invoice = new ClientProjectInvoice(['payment_method' => $method]);

        $this->assertSame($expected, $invoice->paymentMethodLabel());
    }

    public static function paymentMethodLabelProvider(): array
    {
        return [
            'cash' => [ClientProjectInvoice::PAYMENT_METHOD_CASH, 'Cash'],
            'etransfer' => [ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER, 'e-Transfer'],
            'missing' => [null, null],
        ];
    }
}
