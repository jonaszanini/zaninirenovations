<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Services\GoogleCalendarLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the admin when a project's start/end dates are saved, with a
 * ready-to-click "Add to Google Calendar" link.
 */
class ClientProjectCalendarEventAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = '[Calendar] Dates saved — '.$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.admin_calendar_event', [
                'project' => $this->project,
                'calendarUrl' => GoogleCalendarLink::forProject($this->project),
            ]);
    }
}
