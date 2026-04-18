<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Two-day follow-up to the admin after a quote has been sent, reminding
 * them to check for client feedback.
 */
class ClientProjectQuoteFollowUpAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = '[Follow-up] Check client feedback — '.$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.admin_quote_follow_up', [
                'project' => $this->project,
            ]);
    }
}
