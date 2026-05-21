<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Daily reminder to the admin ("create and send the quote") for projects
 * in Visit required, Visit scheduled, or Quote Pending.
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
        $suffix = match ($this->project->status) {
            ClientProject::STATUS_VISIT_REQUIRED => 'Visit required',
            ClientProject::STATUS_VISIT_SCHEDULED => 'Visit / quote prep',
            default => 'Quote still pending',
        };
        $subject = "[Reminder] {$suffix} — ".$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.admin_quote_reminder', [
                'project' => $this->project,
            ]);
    }
}
