<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Receipt email sent to the client right after an invoice is marked paid.
 * The attached PDF is the Receipt generated at mark-paid time.
 *
 * Subject: "Receipt — Payment received for Invoice #… — …"
 */
class ClientProjectInvoiceReceiptClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;
    public ClientProjectInvoice $invoice;
    public string $pdfBytes;
    public string $pdfFilename;

    public function __construct(
        ClientProject $project,
        ClientProjectInvoice $invoice,
        string $pdfBytes,
        string $pdfFilename = 'receipt.pdf'
    ) {
        $this->project = $project;
        $this->invoice = $invoice;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
    }

    public function build(): self
    {
        $subject = sprintf(
            'Receipt — Payment received for Invoice #%s — %s',
            $this->invoice->invoice_number,
            $this->project->name
        );

        return $this->subject($subject)
            ->view('emails.client_projects.client_invoice_receipt', [
                'project' => $this->project,
                'invoice' => $this->invoice,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
