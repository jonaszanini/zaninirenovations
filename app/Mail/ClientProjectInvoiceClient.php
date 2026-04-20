<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the client with the freshly generated invoice PDF attached.
 *
 * Subject: "Invoice #INV-YYYY-NNNN — {Project Name} – Zanini Renovations"
 */
class ClientProjectInvoiceClient extends Mailable
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
        string $pdfFilename = 'invoice.pdf'
    ) {
        $this->project = $project;
        $this->invoice = $invoice;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
    }

    public function build(): self
    {
        $subject = sprintf(
            'Invoice #%s — %s – Zanini Renovations',
            $this->invoice->invoice_number,
            $this->project->name
        );

        return $this->subject($subject)
            ->view('emails.client_projects.client_invoice', [
                'project' => $this->project,
                'invoice' => $this->invoice,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
