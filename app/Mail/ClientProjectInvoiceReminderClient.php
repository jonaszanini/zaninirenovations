<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment reminder for a previously-sent invoice. Re-attaches the same
 * PDF that was originally emailed so the client doesn't have to dig
 * through their inbox for it.
 *
 * Subject: "Friendly reminder: Invoice #… outstanding — …"
 */
class ClientProjectInvoiceReminderClient extends Mailable
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
            'Friendly reminder: Invoice #%s outstanding — %s',
            $this->invoice->invoice_number,
            $this->project->name
        );

        return $this->subject($subject)
            ->view('emails.client_projects.client_invoice_reminder', [
                'project' => $this->project,
                'invoice' => $this->invoice,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
