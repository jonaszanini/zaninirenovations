<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Support\PrivateAppFile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Email sent to the client with the freshly generated invoice PDF attached.
 *
 * When requested, also attaches expense receipt files that exist on disk
 * (WebP or PDF), using the same Receipt-# names as the expenses email.
 * Total attachment size, including the invoice PDF, is capped.
 *
 * Subject: "Invoice #INV-YYYY-NNNN — {Project Name} – Zanini Renovations"
 */
class ClientProjectInvoiceClient extends Mailable
{
    use Queueable, SerializesModels;

    /** Invoice PDF plus receipt files. Further receipts are left off the email. */
    public const ATTACHMENT_BYTE_CAP = 15 * 1024 * 1024;

    public ClientProject $project;

    public ClientProjectInvoice $invoice;

    public string $pdfBytes;

    public string $pdfFilename;

    public bool $attachExpenseReceipts;

    public int $receiptsAttached = 0;

    public bool $receiptsOmittedForSize = false;

    public function __construct(
        ClientProject $project,
        ClientProjectInvoice $invoice,
        string $pdfBytes,
        string $pdfFilename = 'invoice.pdf',
        bool $attachExpenseReceipts = false
    ) {
        $this->project = $project;
        $this->invoice = $invoice;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
        $this->attachExpenseReceipts = $attachExpenseReceipts;
    }

    public function build(): self
    {
        $subject = sprintf(
            'Invoice #%s — %s – Zanini Renovations',
            $this->invoice->invoice_number,
            $this->project->name
        );

        $receiptFiles = [];
        if ($this->attachExpenseReceipts) {
            [$receiptFiles, $this->receiptsOmittedForSize] = $this->expenseReceiptAttachments();
            $this->receiptsAttached = count($receiptFiles);
        }

        $mail = $this->subject($subject)
            ->view('emails.client_projects.client_invoice', [
                'project' => $this->project,
                'invoice' => $this->invoice,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
                'receiptsAttached' => $this->receiptsAttached,
                'receiptsOmittedForSize' => $this->receiptsOmittedForSize,
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);

        foreach ($receiptFiles as $file) {
            $mail->attach($file['path'], [
                'as' => $file['as'],
                'mime' => $file['mime'],
            ]);
        }

        return $mail;
    }

    /**
     * Receipts in expense order (occurred_on, then id). Missing files are
     * skipped. A file that would push the message over the cap is skipped
     * and later receipts are still considered, so a large file does not
     * block a smaller one that still fits.
     *
     * @return array{0: list<array{path: string, as: string, mime: string}>, 1: bool}
     */
    private function expenseReceiptAttachments(): array
    {
        $this->project->loadMissing('expenses');

        $used = strlen($this->pdfBytes);
        $attached = [];
        $omittedForSize = false;

        foreach ($this->project->expenses as $expense) {
            if (! $expense instanceof ClientProjectExpense || ! filled($expense->receipt_path)) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', (string) $expense->receipt_path), '/');
            $absolute = PrivateAppFile::absolutePath($relative);
            $size = ($absolute !== null && is_file($absolute)) ? filesize($absolute) : false;
            if ($absolute === null || $size === false) {
                Log::warning('Invoice email skipped a missing expense receipt', [
                    'project_id' => $this->project->id,
                    'invoice_id' => $this->invoice->id,
                    'expense_id' => $expense->id,
                    'receipt_path' => $relative,
                ]);

                continue;
            }

            if ($used + $size > self::ATTACHMENT_BYTE_CAP) {
                $omittedForSize = true;

                continue;
            }

            $attached[] = [
                'path' => $absolute,
                'as' => $expense->receiptAttachmentBasename(),
                'mime' => $expense->receiptAttachmentMime(),
            ];
            $used += $size;
        }

        return [$attached, $omittedForSize];
    }
}
