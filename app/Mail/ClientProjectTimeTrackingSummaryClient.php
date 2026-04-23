<?php

namespace App\Mail;

use App\Models\ClientProject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Hourly projects: sends the client a readable summary of every closed
 * time entry (date, start/end, hours, notes), plus roll-ups before/after
 * HST at the agreed rate.
 */
class ClientProjectTimeTrackingSummaryClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Time log summary: '.$this->project->name.' — '.config('mail.from.name', 'Zanini Renovations');

        return $this->subject($subject)
            ->view('emails.client_projects.client_time_tracking_summary', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
            ]);
    }
}
