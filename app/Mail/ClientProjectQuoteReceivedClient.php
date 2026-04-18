<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Auto-reply sent to the client the moment they're recorded as a Quote Pending lead:
 * "Thank you for your quote request. We have started working on your [project] quote…"
 */
class ClientProjectQuoteReceivedClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Quote request received — '.config('mail.from.name', 'Zanini Renovations');

        return $this->subject($subject)
            ->view('emails.client_projects.client_quote_received', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ]);
    }
}
