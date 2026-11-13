<?php

namespace App\Services;

use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Support\EasternStandardTimeDisplay;
use App\Support\SignedPublicLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Payment reminders for sent, unpaid invoices — email preferred, SMS fallback.
 */
class ClientProjectInvoiceReminderService
{
    public function __construct(
        private ClientCommunicationLogger $logger,
        private ClientSmsService $clientSms,
        private ClientProjectStatusService $statusService,
    ) {}

    /**
     * Sent invoices with a due date before today (Toronto), excluding
     * cancelled/rejected projects via {@see ClientProjectInvoice::scopeCollectible()}.
     *
     * @return Collection<int, ClientProjectInvoice>
     */
    public function pastDueSentInvoices(): Collection
    {
        $today = Carbon::today('America/Toronto')->toDateString();

        return ClientProjectInvoice::query()
            ->collectible()
            ->with('project')
            ->where('status', ClientProjectInvoice::STATUS_SENT)
            ->whereNull('reminders_paused_at')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today)
            ->orderBy('due_on')
            ->get();
    }

    /**
     * @return array{ok: bool, channel: ?string, detail: string}
     */
    public function sendPaymentReminder(ClientProject $project, ClientProjectInvoice $invoice): array
    {
        if ($project->excludesInvoicesFromReceivables()) {
            return ['ok' => false, 'channel' => null, 'detail' => 'Reminders are not sent for invoices on cancelled or rejected projects.'];
        }

        if (! $invoice->isSent()) {
            return ['ok' => false, 'channel' => null, 'detail' => 'Invoice has not been sent yet.'];
        }

        if ($invoice->isPaid()) {
            return ['ok' => false, 'channel' => null, 'detail' => 'Invoice is already paid.'];
        }

        if (empty($invoice->due_on)) {
            return ['ok' => false, 'channel' => null, 'detail' => 'This invoice has no due date, so a past due reminder is not sent.'];
        }

        if (filled(trim((string) $project->client_email))) {
            return $this->sendEmailReminder($project, $invoice);
        }

        if ($this->canDeliverSmsReminder($project)) {
            return $this->sendSmsReminder($project, $invoice);
        }

        return ['ok' => false, 'channel' => null, 'detail' => 'No client email or SMS-capable phone on file.'];
    }

    public function canDeliverSmsReminder(ClientProject $project): bool
    {
        return $this->clientSms->isConfigured()
            && ClientProject::phoneDigitsForSms($project->client_phone) !== null;
    }

    /**
     * @return array{ok: bool, channel: string, detail: string}
     */
    private function sendEmailReminder(ClientProject $project, ClientProjectInvoice $invoice): array
    {
        $cc = $project->secondaryClientEmail();
        $reminderSubject = "Payment reminder — invoice {$invoice->invoice_number}";
        $reminderDescription = "Payment reminder for invoice {$invoice->invoice_number} — outstanding "
            .ClientProject::formatMoney((float) $invoice->total)
            .($invoice->due_on ? ', due '.\Carbon\Carbon::parse($invoice->due_on)->format('M j, Y') : '');

        try {
            $bytes = $this->invoicePdfBytes($project, $invoice);
            $mailer = Mail::to($project->client_email);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectInvoiceReminderClient(
                $project,
                $invoice,
                $bytes,
                $this->invoicePdfFilename($project, $invoice),
            ));

            $this->logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_INVOICE_REMINDER,
                $project->client_email,
                $cc,
                $reminderSubject,
                $reminderDescription,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to email invoice reminder', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);

            $this->logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_INVOICE_REMINDER,
                $project->client_email,
                $cc,
                $reminderSubject,
                $reminderDescription,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return ['ok' => false, 'channel' => 'email', 'detail' => $e->getMessage()];
        }

        $this->recordHistory(
            $project,
            "Payment reminder for invoice {$invoice->invoice_number} emailed to {$project->clientEmailRecipientsLabel()}."
        );

        return ['ok' => true, 'channel' => 'email', 'detail' => $project->clientEmailRecipientsLabel()];
    }

    /**
     * @return array{ok: bool, channel: string, detail: string}
     */
    private function sendSmsReminder(ClientProject $project, ClientProjectInvoice $invoice): array
    {
        $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
        if ($smsTo === null) {
            return ['ok' => false, 'channel' => 'sms', 'detail' => 'Client phone is missing or not valid for texting.'];
        }

        $signedUrl = URL::temporarySignedRoute(
            'public.client-project.invoice-pdf',
            SignedPublicLink::expiresAt(),
            ['client_project' => $project, 'invoice' => $invoice],
        );

        $totalFmt = ClientProject::formatMoney((float) $invoice->total);
        $dueFmt = $invoice->due_on
            ? EasternStandardTimeDisplay::formatCalendarLongDate($invoice->due_on)
            : 'the due date';
        $smsBody = "Zanini Renovations — Reminder: Invoice {$invoice->invoice_number} ({$totalFmt}) was due {$dueFmt}. View invoice: {$signedUrl}";

        $summary = "Invoice {$invoice->invoice_number} past-due reminder SMS — {$totalFmt} — \"".Str::limit($smsBody, 120).'"';
        $result = $this->clientSms->sendToClient(
            $project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_INVOICE_REMINDER,
            $summary,
        );

        if (! $result['ok']) {
            return ['ok' => false, 'channel' => 'sms', 'detail' => $result['detail']];
        }

        $this->recordHistory(
            $project,
            "Payment reminder for invoice {$invoice->invoice_number} sent via SMS to {$project->clientSmsRecipientsLabel()}."
        );

        return ['ok' => true, 'channel' => 'sms', 'detail' => $project->clientSmsRecipientsLabel()];
    }

    private function recordHistory(ClientProject $project, string $note): void
    {
        try {
            $this->statusService->recordEvent($project, $note);
        } catch (\Throwable $e) {
            Log::warning('Failed to record invoice-reminder history entry', [
                'project_id' => $project->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function invoicePdfBytes(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $disk = Storage::disk('local');
        if (filled($invoice->invoice_pdf_path) && $disk->exists($invoice->invoice_pdf_path)) {
            return $disk->get($invoice->invoice_pdf_path);
        }

        return $this->renderInvoicePdf($project, $invoice)->output();
    }

    private function renderInvoicePdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.pdf', [
            'project' => $project,
            'invoice' => $invoice,
            'isHourlyManual' => $project->isHourly() && $invoice->isManual(),
            'hourlyLabourTotal' => $invoice->hourlyLabourTotalFromItems(),
            'hourlyMaterialsTotal' => $invoice->hourlyMaterialsTotalFromItems(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();
        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    private function stampPageNumbers($pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Roboto', 'normal') ?: $fontMetrics->getFont('Helvetica', 'normal');
        $size = 9;
        $color = [0.42, 0.45, 0.50];

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $reservedWidth = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);

        $x = $canvas->get_width() - 60 - $reservedWidth;
        $y = $canvas->get_height() - 50;

        $canvas->page_text($x, $y, $text, $font, $size, $color);
    }

    private function invoicePdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "invoice-{$invoice->invoice_number}-{$slug}.pdf";
    }
}
