<?php

namespace App\Jobs;

use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\QuotationSendAudit;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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
        public bool $wasSourceDraft = false,
        public ?int $previousVersion = null,
        public ?string $previousPdfPath = null,
    ) {}

    public static function cacheKeyFor(int $quotationId, string $idempotencyKey): string
    {
        return "quotation_send_idem:{$quotationId}:{$idempotencyKey}";
    }

    public static function isDuplicateKeyException(\Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }
        $code = (string) $e->getCode();
        $msg = $e->getMessage();

        return $code === '23000'
            || str_contains($msg, 'Duplicate entry')
            || str_contains($msg, 'UNIQUE constraint')
            || str_contains($msg, 'qsa_quote_idem_unique');
    }

    public function handle(
        ClientProjectStatusService $statusService,
        ClientCommunicationLogger $communicationLogger
    ): void {
        if (filled($this->idempotencyKey)) {
            $existing = QuotationSendAudit::query()
                ->where('client_project_quotation_id', $this->quotation->id)
                ->where('idempotency_key', $this->idempotencyKey)
                ->whereIn('status', [QuotationSendAudit::STATUS_SENT, QuotationSendAudit::STATUS_SENDING])
                ->first();
            if ($existing) {
                return;
            }
        }

        $audit = null;
        $emailTransmitted = false;

        try {
            if (mb_strlen($this->subject) > 255) {
                throw new InvalidArgumentException('Subject must not exceed 255 characters.');
            }

            $pdfBytes = Storage::disk('local')->exists($this->pdfPath)
                ? Storage::disk('local')->get($this->pdfPath)
                : null;

            if ($pdfBytes === null || $pdfBytes === '') {
                $pdf = Pdf::loadView('pdf.project_quotation', [
                    'project' => $this->project,
                    'quotation' => $this->quotation,
                ])->setPaper('letter');
                $pdfBytes = $pdf->output();
                Storage::disk('local')->put($this->pdfPath, $pdfBytes);
            }

            $mailable = new ClientProjectQuotationClient(
                $this->project,
                $this->quotation,
                $pdfBytes,
                $this->pdfFilename,
                null,
                $this->subject,
                $this->messageBody
            );

            $now = now();

            // 1. Claim and persist the audit record BEFORE sending the email (committed immediately).
            //    This validates all DB column constraints and claims the unique (quotation_id, idempotency_key)
            //    slot before any email leaves the server.
            try {
                $audit = QuotationSendAudit::create([
                    'client_project_id' => $this->project->id,
                    'client_project_quotation_id' => $this->quotation->id,
                    'quotation_type' => $this->sourceType ?: $this->quotation->source_type,
                    'quotation_id' => $this->sourceId ?: $this->quotation->source_id,
                    'admin_id' => $this->adminId,
                    'admin_name' => $this->adminName,
                    'server_time' => $now,
                    'recipient_email' => $this->recipientEmail,
                    'cc_email' => $this->ccEmail,
                    'subject' => $this->subject,
                    'message' => $this->messageBody,
                    'quote_version' => 'v' . $this->quotation->version,
                    'pdf_hash' => $this->pdfHash ?: hash('sha256', (string) $this->pdfFilename),
                    'status' => QuotationSendAudit::STATUS_SENDING,
                    'idempotency_key' => $this->idempotencyKey,
                ]);
            } catch (\Throwable $insertErr) {
                if (self::isDuplicateKeyException($insertErr)) {
                    return;
                }
                throw $insertErr;
            }

            // 2. Transmit the email outside of any database transaction.
            //    Track transmission via MessageSent so if a post-transmission listener throws,
            //    we still know the email went out and never roll back the sent status.
            Event::listen(MessageSent::class, function () use (&$emailTransmitted): void {
                $emailTransmitted = true;
            });

            try {
                $mailer = Mail::to($this->recipientEmail);
                if (filled($this->ccEmail)) {
                    $mailer->cc($this->ccEmail);
                }
                $mailer->send($mailable);
                $emailTransmitted = true;
            } catch (\Throwable $mailErr) {
                if (! $emailTransmitted) {
                    throw $mailErr;
                }
                Log::warning('Exception after quotation email was already transmitted', [
                    'quotation_id' => $this->quotation->id,
                    'error' => $mailErr->getMessage(),
                ]);
            }

            // 3. Finalize audit record to STATUS_SENT and mark quotation Sent / published.
            DB::table('quotation_send_audits')
                ->where('id', $audit->id)
                ->where('status', QuotationSendAudit::STATUS_SENDING)
                ->update([
                    'status' => QuotationSendAudit::STATUS_SENT,
                    'updated_at' => $now,
                ]);

            if ($this->quotation->status !== ClientProjectQuotation::STATUS_ACCEPTED) {
                $this->quotation->status = ClientProjectQuotation::STATUS_SENT;
            }
            $this->quotation->sent_at = $now;
            $this->quotation->save();

            $sourceModel = $this->quotation->sourceModel();
            if ($sourceModel && isset($sourceModel->is_draft) && $sourceModel->is_draft) {
                $sourceModel->is_draft = false;
                $sourceModel->save();
            }

            // 4. Secondary logs (isolated in try/catch so a post-send failure cannot undo the sent quote)
            try {
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
            } catch (\Throwable $logErr) {
                Log::warning('Failed logging quotation communication entry after email sent', [
                    'quotation_id' => $this->quotation->id,
                    'error' => $logErr->getMessage(),
                ]);
            }

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
            if (! $emailTransmitted) {
                $this->revertFailedSendState($audit, $e);
            }

            throw $e;
        }
    }

    protected function revertFailedSendState(?QuotationSendAudit $audit, \Throwable $e): void
    {
        if (filled($this->idempotencyKey)) {
            Cache::forget(self::cacheKeyFor((int) $this->quotation->id, (string) $this->idempotencyKey));
        }

        // Undo draft publication and version bump if the quote was not already sent
        $freshQuotation = $this->quotation->fresh();
        if ($freshQuotation && $freshQuotation->status !== ClientProjectQuotation::STATUS_SENT) {
            if ($this->wasSourceDraft) {
                $sourceModel = $freshQuotation->sourceModel();
                if ($sourceModel && isset($sourceModel->is_draft)) {
                    $sourceModel->is_draft = true;
                    $sourceModel->save();
                }
            }
            if ($this->previousVersion !== null) {
                $freshQuotation->version = $this->previousVersion;
                $freshQuotation->pdf_path = $this->previousPdfPath;
                $freshQuotation->save();
            }
        }

        Log::error('SendQuotationToClientJob failed', [
            'project_id' => $this->project->id,
            'quotation_id' => $this->quotation->id,
            'exception' => $e,
        ]);

        if ($audit && $audit->exists) {
            DB::table('quotation_send_audits')
                ->where('id', $audit->id)
                ->where('status', QuotationSendAudit::STATUS_SENDING)
                ->update([
                    'status' => QuotationSendAudit::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);
        } else {
            QuotationSendAudit::create([
                'client_project_id' => $this->project->id,
                'client_project_quotation_id' => $this->quotation->id,
                'quotation_type' => $this->sourceType ?: $this->quotation->source_type,
                'quotation_id' => $this->sourceId ?: $this->quotation->source_id,
                'admin_id' => $this->adminId,
                'admin_name' => $this->adminName,
                'server_time' => now(),
                'recipient_email' => mb_substr($this->recipientEmail, 0, 255),
                'cc_email' => $this->ccEmail ? mb_substr($this->ccEmail, 0, 255) : null,
                'subject' => mb_substr($this->subject, 0, 255),
                'message' => $this->messageBody,
                'quote_version' => 'v' . $this->quotation->version,
                'pdf_hash' => $this->pdfHash ?: str_repeat('0', 64),
                'status' => QuotationSendAudit::STATUS_FAILED,
                'error' => $e->getMessage(),
                'idempotency_key' => $this->idempotencyKey,
            ]);
        }
    }
}
