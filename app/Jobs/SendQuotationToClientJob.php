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
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    ) {}

    public static function cacheKeyFor(int $quotationId, string $idempotencyKey): string
    {
        return "quotation_send_idem:{$quotationId}:{$idempotencyKey}";
    }

    public function handle(
        ClientProjectStatusService $statusService,
        ClientCommunicationLogger $communicationLogger
    ): void {
        if (filled($this->idempotencyKey)) {
            $existing = QuotationSendAudit::query()
                ->where('client_project_quotation_id', $this->quotation->id)
                ->where('idempotency_key', $this->idempotencyKey)
                ->where('status', QuotationSendAudit::STATUS_SENT)
                ->first();
            if ($existing) {
                return;
            }
        }

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

            DB::transaction(function () use ($statusService, $communicationLogger, $mailable): void {
                $now = now();

                // 1. Save audit record BEFORE sending the email so an email never goes out without being recorded
                QuotationSendAudit::create([
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
                    'status' => QuotationSendAudit::STATUS_SENT,
                    'idempotency_key' => $this->idempotencyKey,
                ]);

                // 2. Mark quotation Sent and update sent_at BEFORE sending the email
                if ($this->quotation->status !== ClientProjectQuotation::STATUS_ACCEPTED) {
                    $this->quotation->status = ClientProjectQuotation::STATUS_SENT;
                }
                $this->quotation->sent_at = $now;
                $this->quotation->save();

                // 3. Publish source quote if it was a draft
                $sourceModel = $this->quotation->sourceModel();
                if ($sourceModel && isset($sourceModel->is_draft) && $sourceModel->is_draft) {
                    $sourceModel->is_draft = false;
                    $sourceModel->save();
                }

                // 4. Communication log
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

                // 5. Project status / history
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

                // 6. Send email last inside transaction — if sending fails, transaction rolls back
                $mailer = Mail::to($this->recipientEmail);
                if (filled($this->ccEmail)) {
                    $mailer->cc($this->ccEmail);
                }
                $mailer->send($mailable);
            });
        } catch (\Throwable $e) {
            if (filled($this->idempotencyKey)) {
                Cache::forget(self::cacheKeyFor((int) $this->quotation->id, (string) $this->idempotencyKey));
            }

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

            throw $e;
        }
    }
}
