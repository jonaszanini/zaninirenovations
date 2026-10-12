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
 * Subject includes project number (PRJ…) and quote number (SQ… / QUO…) so
 * replies can be matched. Attachment: the PDF (raw bytes).
 */
class ClientProjectQuotationClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;
    public ClientProjectQuotation $quotation;
    public string $pdfBytes;
    public string $pdfFilename;
    public string $acceptUrl;

    public function __construct(
        ClientProject $project,
        ClientProjectQuotation $quotation,
        string $pdfBytes,
        string $pdfFilename = 'quotation.pdf',
        ?string $acceptUrl = null,
    ) {
        $this->project = $project;
        $this->quotation = $quotation;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
        $this->acceptUrl = $acceptUrl ?? $quotation->signedAcceptUrl();
    }

    public static function subjectFor(ClientProject $project, ClientProjectQuotation $quotation): string
    {
        return sprintf(
            '%s %s · %s – Zanini Renovations',
            $quotation->typeLabel(),
            $quotation->displayQuoteNumber(),
            $project->referenceCode()
        );
    }

    public function build(): self
    {
        return $this->subject(self::subjectFor($this->project, $this->quotation))
            ->view('emails.client_projects.client_quotation', [
                'project' => $this->project,
                'quotation' => $this->quotation,
                'acceptUrl' => $this->acceptUrl,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
