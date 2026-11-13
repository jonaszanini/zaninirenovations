<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the business inbox that a client accepted a quote from the
 * signed link used by the quotation email and PDF.
 */
class ClientProjectQuoteAcceptedAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientProjectQuotation $quotation,
        public ClientProject $project,
    ) {
    }

    public function subjectLine(): string
    {
        $client = trim((string) $this->project->client_name) !== ''
            ? $this->project->client_name
            : 'Client';

        return 'Quote accepted, '.$this->quotation->displayQuoteNumber().', '.$client;
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine())
            ->view('emails.client_projects.admin_quote_accepted', [
                'quotation' => $this->quotation,
                'project' => $this->project,
            ]);
    }
}
