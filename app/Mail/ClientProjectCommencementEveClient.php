<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Services\GoogleCalendarLink;
use App\Support\EasternStandardTimeDisplay;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client the calendar day before the project's scheduled
 * start_date (Toronto timezone) as a heads-up that work begins tomorrow.
 */
class ClientProjectCommencementEveClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $start = $this->project->start_date
            ? EasternStandardTimeDisplay::formatCalendarLongDateWithWeekday($this->project->start_date)
            : '';

        $subject = 'Reminder: your project starts tomorrow — '.$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.client_commencement_eve', [
                'project' => $this->project,
                'startFormatted' => $start,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
                'calendarLink' => GoogleCalendarLink::forClient($this->project),
            ]);
    }
}
