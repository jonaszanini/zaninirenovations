<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Refund receipt emailed to the client after money is returned on a paid invoice.
 */
class ClientProjectInvoiceRefundClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public ClientProjectInvoice $invoice;

    public string $pdfBytes;

    public string $pdfFilename;

    public float $refundedNow;

    public function __construct(
        ClientProject $project,
        ClientProjectInvoice $invoice,
        string $pdfBytes,
        string $pdfFilename,
        float $refundedNow
    ) {
        $this->project = $project;
        $this->invoice = $invoice;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
        $this->refundedNow = $refundedNow;
    }

    public function build(): self
    {
        $subject = sprintf(
            'Refund — Invoice #%s — %s',
            $this->invoice->invoice_number,
            $this->project->name
        );

        return $this->subject($subject)
            ->view('emails.client_projects.client_invoice_refund', [
                'project' => $this->project,
                'invoice' => $this->invoice,
                'refundedNow' => $this->refundedNow,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
