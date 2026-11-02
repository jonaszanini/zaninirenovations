<?php

namespace App\Jobs;

use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\QuotationSendAudit;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendQuotationToClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ClientProject $project,
        public ClientProjectQuotation $quotation,
        public string $recipientEmail,
        public ?string $ccEmail,
        public string $subject,
        public ?string $messageBody,
        public string $pdfPath,
        public string $pdfFilename,
        public string $pdfHash,
        public ?int $adminId,
        public string $adminName,
        public ?string $idempotencyKey = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}

    public function handle(
        ClientProjectStatusService $statusService,
        ClientCommunicationLogger $communicationLogger
    ): void {
        if (filled($this->idempotencyKey)) {
            $existing = QuotationSendAudit::where('idempotency_key', $this->idempotencyKey)
                ->where('status', QuotationSendAudit::STATUS_SENT)
                ->first();
            if ($existing) {
                return;
            }
        }

        try {
            $mailer = Mail::to($this->recipientEmail);
            if (filled($this->ccEmail)) {
                $mailer->cc($this->ccEmail);
            }

            $pdfBytes = Storage::disk('local')->get($this->pdfPath);

            $mailable = new ClientProjectQuotationClient(
                $this->project,
                $this->quotation,
                $pdfBytes,
                $this->pdfFilename,
                null,
                $this->subject,
                $this->messageBody
            );

            $mailer->send($mailable);

            // Audit record
            QuotationSendAudit::create([
                'client_project_id' => $this->project->id,
                'client_project_quotation_id' => $this->quotation->id,
                'quotation_type' => $this->sourceType ?: $this->quotation->source_type,
                'quotation_id' => $this->sourceId ?: $this->quotation->source_id,
                'admin_id' => $this->adminId,
                'admin_name' => $this->adminName,
                'server_time' => now(),
                'recipient_email' => $this->recipientEmail,
                'cc_email' => $this->ccEmail,
                'subject' => $this->subject,
                'message' => $this->messageBody,
                'quote_version' => 'v' . $this->quotation->version,
                'pdf_hash' => $this->pdfHash,
                'status' => QuotationSendAudit::STATUS_SENT,
                'idempotency_key' => $this->idempotencyKey,
            ]);

            // Set status to Sent and update sent_at
            if ($this->quotation->status !== ClientProjectQuotation::STATUS_ACCEPTED) {
                $this->quotation->status = ClientProjectQuotation::STATUS_SENT;
            }
            $this->quotation->sent_at = now();
            $this->quotation->save();

            // Publish source quote if it was a draft
            $sourceModel = $this->quotation->sourceModel();
            if ($sourceModel && isset($sourceModel->is_draft) && $sourceModel->is_draft) {
                $sourceModel->is_draft = false;
                $sourceModel->save();
            }

            // Communication log
            $quoteDescription = "{$this->quotation->typeLabel()} {$this->quotation->quotation_number} v{$this->quotation->version} — total "
                . ClientProject::formatMoney((float) $this->quotation->grandTotal());

            $communicationLogger->logEmail(
                $this->project,
                ClientCommunicationLog::REASON_QUOTATION_SENT,
                $this->recipientEmail,
                $this->ccEmail,
                $this->subject,
                $quoteDescription,
            );

            // Project status / history
            $sendNote = "{$this->quotation->typeLabel()} {$this->quotation->quotation_number} emailed to {$this->recipientEmail} (v{$this->quotation->version}).";
            if ($this->project->isAwaitingQuotePreparation()) {
                try {
                    $statusService->transition(
                        $this->project,
                        ClientProject::STATUS_QUOTE_SENT,
                        $sendNote
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed auto-transitioning project to Quote Sent', ['error' => $e->getMessage()]);
                }
            } else {
                try {
                    $statusService->recordEvent($this->project, $sendNote);
                } catch (\Throwable $e) {
                    Log::warning('Failed recording quotation send history entry', ['error' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendQuotationToClientJob failed', [
                'project_id' => $this->project->id,
                'quotation_id' => $this->quotation->id,
                'exception' => $e,
            ]);

            QuotationSendAudit::create([
                'client_project_id' => $this->project->id,
                'client_project_quotation_id' => $this->quotation->id,
                'quotation_type' => $this->sourceType ?: $this->quotation->source_type,
                'quotation_id' => $this->sourceId ?: $this->quotation->source_id,
                'admin_id' => $this->adminId,
                'admin_name' => $this->adminName,
                'server_time' => now(),
                'recipient_email' => $this->recipientEmail,
                'cc_email' => $this->ccEmail,
                'subject' => $this->subject,
                'message' => $this->messageBody,
                'quote_version' => 'v' . $this->quotation->version,
                'pdf_hash' => $this->pdfHash,
                'status' => QuotationSendAudit::STATUS_FAILED,
                'error' => $e->getMessage(),
                'idempotency_key' => $this->idempotencyKey,
            ]);

            // Do NOT set status to Sent on failure
            throw $e;
        }
    }
}
