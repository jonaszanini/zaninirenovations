<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to the client with the freshly generated contract PDF attached.
 *
 * Subject: "Contract – {Project Name}"
 * Body: short professional message with project details.
 * Attachment: contract.pdf (raw bytes passed in to avoid touching disk twice).
 */
class ClientProjectContractClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;
    public string $pdfBytes;
    public string $pdfFilename;

    public function __construct(ClientProject $project, string $pdfBytes, string $pdfFilename = 'contract.pdf')
    {
        $this->project = $project;
        $this->pdfBytes = $pdfBytes;
        $this->pdfFilename = $pdfFilename;
    }

    public function build(): self
    {
        $subject = 'Contract – '.$this->project->name;

        // The email body references the quotation number + date so the
        // client can paste the acceptance phrase. Load it lazily so we
        // don't second-guess whatever the caller already preloaded.
        $quotation = $this->project->relationLoaded('quotation')
            ? $this->project->quotation
            : $this->project->quotation()->first();

        return $this->subject($subject)
            ->view('emails.client_projects.client_contract', [
                'project' => $this->project,
                'quotation' => $quotation,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
