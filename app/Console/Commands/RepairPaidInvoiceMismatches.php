<?php

namespace App\Console\Commands;

use App\Services\Invoice\RepairPaidInvoiceMismatchService;
use Illuminate\Console\Command;

/**
 * Sets mismatched invoices to Paid using the recorded paid date and stops
 * reminders. Never emails the client. Safe to run more than once.
 */
class RepairPaidInvoiceMismatches extends Command
{
    protected $signature = 'invoices:repair-paid-mismatch';

    protected $description = 'Mark unpaid invoices paid when their installment or payment is already recorded. Does not email clients.';

    public function handle(RepairPaidInvoiceMismatchService $repair): int
    {
        $changed = $repair->repair();

        if ($changed === []) {
            $this->info('No paid mismatches to repair.');

            return self::SUCCESS;
        }

        foreach ($changed as $number) {
            $this->line($number);
        }

        $this->info('Repaired '.count($changed).' invoice(s). No client email was sent.');

        return self::SUCCESS;
    }
}
