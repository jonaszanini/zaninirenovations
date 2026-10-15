<?php

namespace App\Services\Invoice;

use App\Mail\ClientProjectInvoiceReceiptClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Support\SignedPublicLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Marks a client invoice paid, writes the receipt PDF, syncs the linked
 * Financials installment, and optionally emails/SMS the receipt.
 *
 * Shared by the project invoice "Mark paid" action and incoming e-transfer
 * matching so totals, status, and receipts stay on one code path.
 */
class MarkInvoicePaidService
{
    public function __construct(
        protected InvoicePdfService $pdfService,
        protected ClientProjectStatusService $statusService,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, flash?: string, warning?: string|null}
     */
    public function mark(
        ClientProject $project,
        ClientProjectInvoice $invoice,
        string $paidOn,
        float $paidAmount,
        string $paymentMethod,
        bool $sendEmail = false,
        bool $sendSms = false,
        ?string $projectStatus = null,
        ?string $extraNote = null,
    ): array {
        if ($invoice->client_project_id !== $project->id) {
            return ['ok' => false, 'error' => 'This invoice does not belong to that project.'];
        }

        if ($invoice->isPaid() || $invoice->isRefunded()) {
            return ['ok' => false, 'error' => $invoice->isRefunded()
                ? 'This invoice has been refunded.'
                : 'This invoice is already marked paid.'];
        }

        if (! $invoice->hasContent() || (float) $invoice->total <= 0) {
            return ['ok' => false, 'error' => 'Add at least one line item with a description and amount before marking the invoice paid.'];
        }

        $isFirstPaidInvoice = ! $project->invoices()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->exists();

        $firstInstallmentJustPaid = false;

        DB::transaction(function () use ($invoice, $project, $paidOn, $paidAmount, $paymentMethod, &$firstInstallmentJustPaid) {
            $invoice->paid_at = $paidOn;
            $invoice->paid_amount = round($paidAmount, 2);
            $invoice->payment_method = $paymentMethod;
            $invoice->status = ClientProjectInvoice::STATUS_PAID;

            $pdf = $this->pdfService->renderReceiptPdf($project, $invoice);
            $bytes = $pdf->output();
            $relativePath = $this->pdfService->receiptPdfStoragePath($project, $invoice);
            Storage::disk('local')->put($relativePath, $bytes);
            $invoice->receipt_pdf_path = $relativePath;

            $invoice->save();

            if (! $invoice->isManual()) {
                $installment = $project->installments()
                    ->orderBy('sort_order')
                    ->get()
                    ->values()
                    ->get($invoice->installment_number - 1);

                if ($installment) {
                    $wasPaid = (bool) $installment->is_paid;

                    if (! $project->isHourly()) {
                        $installment->amount = round(max(0.0, (float) $invoice->subtotal), 2);
                    }

                    if (! $wasPaid) {
                        $installment->is_paid = true;
                        $installment->paid_on = $paidOn;
                    }

                    $installment->save();

                    if (! $wasPaid && (int) $invoice->installment_number === 1) {
                        $firstInstallmentJustPaid = true;
                    }
                }
            }
        });

        $disk = Storage::disk('local');
        $receiptBytes = $disk->exists($invoice->receipt_pdf_path)
            ? $disk->get($invoice->receipt_pdf_path)
            : $this->pdfService->renderReceiptPdf($project, $invoice)->output();

        $emailSent = false;
        $smsSent = false;

        if ($sendEmail && filled(trim((string) $project->client_email))) {
            $cc = $project->secondaryClientEmail();
            $receiptSubject = "Receipt — invoice {$invoice->invoice_number} paid";
            $receiptDescription = "Receipt for invoice {$invoice->invoice_number} — paid "
                .ClientProject::formatMoney((float) $invoice->paid_amount)
                .' on '.Carbon::parse($invoice->paid_at)->format('M j, Y');

            try {
                $mailer = Mail::to($project->client_email);
                if ($cc) {
                    $mailer->cc($cc);
                }
                $mailer->send(new ClientProjectInvoiceReceiptClient(
                    $project,
                    $invoice,
                    $receiptBytes,
                    $this->pdfService->receiptPdfFilename($project, $invoice)
                ));
                $emailSent = true;

                app(ClientCommunicationLogger::class)->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_RECEIPT_SENT,
                    $project->client_email,
                    $cc,
                    $receiptSubject,
                    $receiptDescription,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to email receipt', [
                    'project_id' => $project->id,
                    'invoice_id' => $invoice->id,
                    'exception' => $e->getMessage(),
                ]);

                app(ClientCommunicationLogger::class)->logEmail(
                    $project,
                    ClientCommunicationLog::REASON_RECEIPT_SENT,
                    $project->client_email,
                    $cc,
                    $receiptSubject,
                    $receiptDescription,
                    ClientCommunicationLog::DIRECTION_TO_CLIENT,
                    false,
                    $e->getMessage(),
                );
            }
        }

        if ($sendSms) {
            $smsSent = $this->sendReceiptSms($project, $invoice);
        }

        $deliveryParts = [];
        if ($emailSent) {
            $deliveryParts[] = 'receipt emailed to '.$project->clientEmailRecipientsLabel();
        }
        if ($smsSent) {
            $deliveryParts[] = 'receipt SMS sent to client';
        }
        $deliveryNote = $deliveryParts !== []
            ? '. '.ucfirst(implode('; ', $deliveryParts)).'.'
            : '.';

        $note = "Invoice {$invoice->invoice_number} marked paid "
            .ClientProject::formatMoney((float) $invoice->paid_amount)
            .$deliveryNote;
        if (filled($extraNote)) {
            $note = rtrim($note, '.').'. '.$extraNote;
        }

        try {
            $this->statusService->recordEvent($project, $note);
        } catch (\Throwable $e) {
            Log::warning('Failed to record mark-paid history entry', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        if ($emailSent && $smsSent) {
            $flash = 'Invoice marked paid. Receipt emailed and SMS sent to the client.';
        } elseif ($emailSent) {
            $flash = "Invoice marked paid. Receipt emailed to {$project->clientEmailRecipientsLabel()}.";
        } elseif ($smsSent) {
            $flash = 'Invoice marked paid. Receipt SMS sent to the client.';
        } elseif ($sendEmail || $sendSms) {
            $flash = 'Invoice marked paid. Receipt delivery was requested but could not be sent — check contact info and logs.';
        } else {
            $flash = 'Invoice marked paid.';
        }

        $targetStatus = trim((string) $projectStatus);
        if ($isFirstPaidInvoice && ! $project->isClosed() && $targetStatus !== '') {
            $proj = $project->fresh();
            if ($proj && $targetStatus !== $proj->status) {
                try {
                    $updated = $this->statusService->transition(
                        $proj,
                        $targetStatus,
                        'Status set while marking the first paid invoice.'
                    );
                    $flash .= ' Project status updated to '.$updated->statusLabel().'.';
                } catch (\Throwable $e) {
                    Log::warning('markPaid: project_status transition failed', [
                        'project_id' => $project->id,
                        'invoice_id' => $invoice->id,
                        'target_status' => $targetStatus,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        $warning = null;
        if ($firstInstallmentJustPaid) {
            if (! $project->fresh()->hasSchedule()) {
                $warning = 'Please set the project start and end dates — the calendar notification will be emailed once both are saved.';
            } else {
                $fresh = $project->fresh();
                $result = $this->statusService->maybeSendCalendarDatesEmail($fresh);
                $fixed = $this->statusService->maybeSendFixedScheduleConfirmationToClient($fresh->fresh());
                if ($result === 'sent') {
                    $flash .= ' Calendar notification emailed to the admin.';
                } elseif ($result === 'failed') {
                    $warning = 'The calendar notification email failed to send. Check logs and try again.';
                }
                if ($fixed === 'sent') {
                    $flash .= ' Schedule confirmation emailed to the client.';
                } elseif ($fixed === 'failed') {
                    $warn2 = 'The client schedule confirmation email failed to send. Check logs.';
                    $warning = $warning ? $warning.' '.$warn2 : $warn2;
                }
            }
        }

        return [
            'ok' => true,
            'flash' => $flash,
            'warning' => $warning,
        ];
    }

    protected function sendReceiptSms(ClientProject $project, ClientProjectInvoice $invoice): bool
    {
        $clientSms = app(ClientSmsService::class);
        if (! $clientSms->isConfigured()) {
            return false;
        }

        $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
        if ($smsTo === null) {
            return false;
        }

        $signedUrl = URL::temporarySignedRoute(
            'public.client-project.receipt-pdf',
            SignedPublicLink::expiresAt(),
            ['client_project' => $project, 'invoice' => $invoice],
        );

        $paidFmt = ClientProject::formatMoney((float) $invoice->paid_amount);
        $smsBody = "Zanini Renovations — Receipt for invoice {$invoice->invoice_number}. Paid: {$paidFmt}. View receipt: {$signedUrl}";

        $summary = "Receipt {$invoice->invoice_number} SMS link — {$paidFmt} — \"".Str::limit($smsBody, 120).'"';
        $result = $clientSms->sendToClient(
            $project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_RECEIPT_SMS_LINK,
            $summary,
        );

        if (! $result['ok']) {
            Log::error('Failed to SMS receipt', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'detail' => $result['detail'],
            ]);
        }

        return $result['ok'];
    }
}
