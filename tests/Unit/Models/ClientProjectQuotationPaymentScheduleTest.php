<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Services\QuotationPaymentSchedule;
use PHPUnit\Framework\TestCase;

class ClientProjectQuotationPaymentScheduleTest extends TestCase
{
    public function test_resolved_schedule_uses_saved_custom_percentages(): void
    {
        $quotation = new ClientProjectQuotation([
            'payment_installments' => 2,
            'payment_percentages' => [40, 60],
        ]);

        $this->assertSame([
            'installments' => 2,
            'percentages' => [40.0, 60.0],
        ], $quotation->resolvedPaymentSchedule());
    }

    public function test_resolved_schedule_falls_back_to_source_snapshot(): void
    {
        $quotation = new ClientProjectQuotation([
            'payment_installments' => null,
            'payment_percentages' => null,
            'source_snapshot' => [
                'payment_installments' => 3,
                'payment_percentages' => [25, 25, 50],
            ],
        ]);

        $this->assertSame([
            'installments' => 3,
            'percentages' => [25.0, 25.0, 50.0],
        ], $quotation->resolvedPaymentSchedule());
    }

    public function test_resolved_schedule_uses_plan_defaults_when_percentages_missing(): void
    {
        $quotation = new ClientProjectQuotation([
            'payment_installments' => 2,
            'payment_percentages' => null,
        ]);

        $this->assertSame([
            'installments' => 2,
            'percentages' => QuotationPaymentSchedule::percentages(2),
        ], $quotation->resolvedPaymentSchedule());
    }

    public function test_resolved_schedule_is_null_when_installment_count_is_invalid(): void
    {
        $quotation = new ClientProjectQuotation([
            'payment_installments' => 0,
            'source_snapshot' => ['client_name' => 'Jane'],
        ]);

        $this->assertNull($quotation->resolvedPaymentSchedule());
    }
}
