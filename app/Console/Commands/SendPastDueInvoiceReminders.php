<?php

namespace App\Console\Commands;

use App\Models\ClientProjectInvoice;
use App\Services\ClientProjectInvoiceReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily automated payment reminders for past-due sent invoices.
 *
 * Email is used when the project has a client email; otherwise SMS (Mocean).
 * Repeats every day until the invoice is marked paid, unless the admin
 * paused daily reminders on that invoice.
 *
 * Runs via automations:run-daily (HTTP cron at 9am EST).
 */
class SendPastDueInvoiceReminders extends Command
{
    protected $signature = 'client-projects:send-past-due-invoice-reminders {--dry-run : List matches without sending}';

    protected $description = 'Send daily payment reminders for past-due sent invoices (email preferred, else SMS).';

    public function handle(ClientProjectInvoiceReminderService $reminders): int
    {
        if (! config('client_projects.past_due_invoice_reminders_enabled', true)) {
            $this->warn('Past-due invoice reminders are disabled (CLIENT_PROJECTS_PAST_DUE_INVOICE_REMINDERS=false).');

            return self::SUCCESS;
        }

        $invoices = $reminders->pastDueSentInvoices();

        if ($invoices->isEmpty()) {
            $this->info('No past-due sent invoices.');

            return self::SUCCESS;
        }

        $this->info('Past-due invoices: '.$invoices->count());

        foreach ($invoices as $invoice) {
            /** @var ClientProjectInvoice $invoice */
            $project = $invoice->project;
            if ($project === null) {
                $this->warn('  Skipping invoice #'.$invoice->id.' — project missing.');

                continue;
            }

            $label = "  #{$invoice->id} {$invoice->invoice_number} — {$project->name} — due {$invoice->due_on?->toDateString()}";

            if ($this->option('dry-run')) {
                $channel = filled(trim((string) $project->client_email))
                    ? 'email → '.$project->clientEmailRecipientsLabel()
                    : ($reminders->canDeliverSmsReminder($project)
                        ? 'SMS → '.$project->clientSmsRecipientsLabel()
                        : 'skipped (no email or SMS)');
                $this->line($label.' → '.$channel);

                continue;
            }

            $result = $reminders->sendPaymentReminder($project, $invoice);

            if ($result['ok']) {
                $this->info($label.' → sent via '.$result['channel'].' ('.$result['detail'].')');
            } else {
                Log::warning('Past-due invoice reminder failed', [
                    'invoice_id' => $invoice->id,
                    'project_id' => $project->id,
                    'detail' => $result['detail'],
                ]);
                $this->error($label.' → failed: '.$result['detail']);
            }
        }

        return self::SUCCESS;
    }
}
