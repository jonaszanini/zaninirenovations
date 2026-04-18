<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Thank-you + review request sent to the client when the project status is
 * changed to Completed.
 */
class ClientProjectThankYouClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Thank you for trusting us — '.config('mail.from.name', 'Zanini Renovations');

        return $this->subject($subject)
            ->view('emails.client_projects.client_thank_you', [
                'project' => $this->project,
                'reviewUrl' => (string) config('client_projects.review_url'),
            ]);
    }
}
