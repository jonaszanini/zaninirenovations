<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the client with the freshly generated quotation/estimate
 * PDF attached.
 *
 * Subject: "[Quotation/Estimate]: {Client Name} - {Project Name} – Zanini Renovations"
 * Body: short professional message with project + scope context.
 * Attachment: the PDF (raw bytes passed in to avoid touching disk twice).
 */
class ClientProjectQuotationClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;
    public ClientProjectQuotation $quotation;
    public string $pdfBytes;
    public string $pdfFilename;

    public function __construct(
        ClientProject $project,
        ClientProjectQuotation $quotation,
        string $pdfBytes,
        string $pdfFilename = 'quotation.pdf'
    ) {
        $this->project = $project;
        $this->quotation = $quotation;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
    }

    public function build(): self
    {
        $type = $this->quotation->typeLabel(); // "Quotation" or "Estimate"
        $subject = sprintf(
            '%s: %s - %s – Zanini Renovations',
            $type,
            $this->project->client_name,
            $this->project->name
        );

        return $this->subject($subject)
            ->view('emails.client_projects.client_quotation', [
                'project' => $this->project,
                'quotation' => $this->quotation,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
