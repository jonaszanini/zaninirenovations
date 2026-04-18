<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Daily reminder to the admin ("create and send the quote") for projects
 * still in Quote Pending status.
 */
class ClientProjectQuoteReminderAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = '[Reminder] Quote still pending — '.$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.admin_quote_reminder', [
                'project' => $this->project,
            ]);
    }
}
