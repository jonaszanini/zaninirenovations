<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Support\EasternStandardTimeDisplay;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the calendar day before the project's scheduled start_date
 * (Toronto timezone) so the admin gets a heads-up that work begins tomorrow.
 */
class ClientProjectCommencementEveAdmin extends Mailable
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

        $subject = '[Tomorrow] Work begins — '.$this->project->name;

        return $this->subject($subject)
            ->view('emails.client_projects.admin_commencement_eve', [
                'project' => $this->project,
                'startFormatted' => $start,
            ]);
    }
}
