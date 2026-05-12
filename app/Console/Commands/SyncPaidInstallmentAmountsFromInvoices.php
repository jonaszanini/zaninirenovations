<?php

namespace App\Console\Commands;

use App\Models\ClientProjectInvoice;
use Illuminate\Console\Command;

class SyncPaidInstallmentAmountsFromInvoices extends Command
{
    protected $signature = 'client-projects:sync-paid-installment-amounts
                            {--dry-run : List changes without saving}';

    protected $description = 'Set each paid installment invoice’s linked Financials row `amount` to that invoice’s billed subtotal (after discount), matching mark-paid behaviour for older data.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $changed = 0;

        ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereNotNull('installment_number')
            ->with(['project' => fn ($q) => $q->with('installments')])
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($dryRun, &$scanned, &$changed): void {
                foreach ($invoices as $invoice) {
                    $scanned++;
                    $project = $invoice->project;
                    if (!$project || $project->isHourly()) {
                        continue;
                    }

                    $n = (int) $invoice->installment_number;
                    if ($n < 1) {
                        continue;
                    }

                    $installment = $project->installments->values()->get($n - 1);
                    if (!$installment) {
                        continue;
                    }

                    $newAmount = round(max(0.0, (float) $invoice->subtotal), 2);
                    if (abs((float) $installment->amount - $newAmount) < 0.005) {
                        continue;
                    }

                    $this->line(sprintf(
                        'Project #%d — %s — installment %d: %s → %s',
                        $project->id,
                        $invoice->invoice_number,
                        $n,
                        number_format((float) $installment->amount, 2),
                        number_format($newAmount, 2)
                    ));

                    if (!$dryRun) {
                        $installment->amount = $newAmount;
                        $installment->save();
                    }
                    $changed++;
                }
            });

        $verb = $dryRun ? 'Would adjust' : 'Adjusted';
        $this->info("{$verb} {$changed} installment row(s); scanned {$scanned} paid installment invoice(s).");

        return self::SUCCESS;
    }
}
