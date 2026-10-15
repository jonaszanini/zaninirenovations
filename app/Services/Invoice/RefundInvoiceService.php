<?php

namespace App\Services\Invoice;

use App\Mail\ClientProjectInvoiceRefundClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Records a refund against a paid invoice, writes a refund receipt PDF,
 * and clears the linked installment only when the paid amount is fully returned.
 *
 * Does not change the project status. Partial refunds stay Paid and can be
 * topped up later until nothing refundable remains.
 */
class RefundInvoiceService
{
    public function __construct(
        protected InvoicePdfService $pdfService,
        protected ClientProjectStatusService $statusService,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, flash?: string}
     */
    public function refund(
        ClientProject $project,
        ClientProjectInvoice $invoice,
        string $refundedOn,
        float $amount,
        string $method,
        ?string $note = null,
        bool $sendEmail = false,
    ): array {
        if ($invoice->client_project_id !== $project->id) {
            return ['ok' => false, 'error' => 'This invoice does not belong to that project.'];
        }

        if ($invoice->isRefunded()) {
            return ['ok' => false, 'error' => 'This invoice has already been fully refunded.'];
        }

        if (! $invoice->isPaid()) {
            return ['ok' => false, 'error' => 'Only paid invoices can be refunded.'];
        }

        $amount = round($amount, 2);
        if ($amount < 0.01) {
            return ['ok' => false, 'error' => 'Enter a refund amount greater than zero.'];
        }

        $remaining = $invoice->refundableBalance();
        if ($amount - $remaining > 0.009) {
            return [
                'ok' => false,
                'error' => 'Refund cannot exceed the amount still refundable ('
                    .ClientProject::formatMoney($remaining).').',
            ];
        }

        $paid = $invoice->paidCash();
        $newTotal = round($invoice->refundCash() + $amount, 2);
        $full = $newTotal + 0.009 >= $paid;

        DB::transaction(function () use ($invoice, $project, $refundedOn, $method, $note, $newTotal, $full) {
            $invoice->refund_amount = $newTotal;
            $invoice->refunded_at = $refundedOn;
            $invoice->refund_method = $method;

            $incoming = trim((string) $note);
            if ($incoming !== '') {
                $existing = trim((string) $invoice->refund_note);
                $combined = $existing === '' ? $incoming : $existing."\n".$incoming;
                $invoice->refund_note = mb_substr($combined, 0, 500);
            }

            if ($full) {
                $invoice->status = ClientProjectInvoice::STATUS_REFUNDED;
            }

            $pdf = $this->pdfService->renderRefundPdf($project, $invoice);
            $relativePath = $this->pdfService->refundPdfStoragePath($project, $invoice);
            Storage::disk('local')->put($relativePath, $pdf->output());
            $invoice->refund_pdf_path = $relativePath;
            $invoice->save();

            if ($full && ! $invoice->isManual()) {
                $installment = $project->installments()
                    ->orderBy('sort_order')
                    ->get()
                    ->values()
                    ->get($invoice->installment_number - 1);

                if ($installment && $installment->is_paid) {
                    $installment->is_paid = false;
                    $installment->paid_on = null;
                    $installment->save();
                }
            }
        });

        $invoice->refresh();

        $emailSent = false;
        if ($sendEmail) {
            $emailSent = $this->emailRefundReceipt($project, $invoice, $amount);
        }

        $money = ClientProject::formatMoney($amount);
        if ($full) {
            $flash = "Invoice {$invoice->invoice_number} refunded {$money}.";
        } else {
            $flash = "Refund of {$money} recorded on invoice {$invoice->invoice_number}. "
                .ClientProject::formatMoney($invoice->refundableBalance()).' is still refundable.';
        }

        if ($emailSent) {
            $flash .= ' Refund receipt emailed to '.$project->clientEmailRecipientsLabel().'.';
        } elseif ($sendEmail) {
            $flash .= ' Refund receipt email was requested but could not be sent — check the client email and the communication log.';
        }

        $noteLine = $full
            ? "Invoice {$invoice->invoice_number} fully refunded {$money}."
            : "Invoice {$invoice->invoice_number} partially refunded {$money}.";

        try {
            $this->statusService->recordEvent($project, $noteLine);
        } catch (\Throwable $e) {
            Log::warning('Failed to record refund history entry', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return [
            'ok' => true,
            'flash' => $flash,
        ];
    }

    protected function emailRefundReceipt(ClientProject $project, ClientProjectInvoice $invoice, float $amount): bool
    {
        if (blank(trim((string) $project->client_email))) {
            return false;
        }

        $disk = Storage::disk('local');
        $bytes = filled($invoice->refund_pdf_path) && $disk->exists($invoice->refund_pdf_path)
            ? $disk->get($invoice->refund_pdf_path)
            : $this->pdfService->renderRefundPdf($project, $invoice)->output();

        $cc = $project->secondaryClientEmail();
        $subject = "Refund — invoice {$invoice->invoice_number}";
        $description = "Refund receipt for invoice {$invoice->invoice_number} — "
            .ClientProject::formatMoney($amount)
            .' on '.Carbon::parse($invoice->refunded_at)->format('M j, Y');

        try {
            $mailer = Mail::to($project->client_email);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectInvoiceRefundClient(
                $project,
                $invoice,
                $bytes,
                $this->pdfService->refundPdfFilename($project, $invoice),
                $amount,
            ));

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_REFUND_SENT,
                $project->client_email,
                $cc,
                $subject,
                $description,
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to email refund receipt', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_REFUND_SENT,
                $project->client_email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return false;
        }
    }
}
