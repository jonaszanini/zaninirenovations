<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Services\GoogleCalendarLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Manual on-demand "here are your project dates" email — sendable from the
 * Calendar tab any time the admin wants to remind the client about the
 * scheduled start/end. Unlike the fixed-schedule confirmation, this isn't
 * tied to a payment milestone or billing type and can be re-sent freely.
 */
class ClientProjectScheduleDatesClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Your project dates with Zanini Renovations';

        return $this->subject($subject)
            ->view('emails.client_projects.client_schedule_dates', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
                'calendarLink' => GoogleCalendarLink::forClient($this->project),
            ]);
    }
}
