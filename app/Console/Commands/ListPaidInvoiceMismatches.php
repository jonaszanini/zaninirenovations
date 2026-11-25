<?php

namespace App\Console\Commands;

use App\Services\Invoice\RepairPaidInvoiceMismatchService;
use Illuminate\Console\Command;

/**
 * Read-only list of invoices whose status is still unpaid while a payment
 * or installment is recorded as paid. Does not update rows or send email.
 */
class ListPaidInvoiceMismatches extends Command
{
    protected $signature = 'invoices:list-paid-mismatch';

    protected $description = 'List invoices that are unpaid while a payment or installment is recorded as paid.';

    public function handle(RepairPaidInvoiceMismatchService $repair): int
    {
        $rows = $repair->mismatches();

        if ($rows->isEmpty()) {
            $this->info('No paid mismatches.');

            return self::SUCCESS;
        }

        foreach ($rows as $invoice) {
            $this->line((string) $invoice->invoice_number);
        }

        return self::SUCCESS;
    }
}
