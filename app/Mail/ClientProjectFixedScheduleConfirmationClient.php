<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Services\GoogleCalendarLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once to the client on fixed-price projects after the first installment
 * is paid and start/end dates are set — confirms the work window and service
 * address (separate from invoice / receipt emails).
 */
class ClientProjectFixedScheduleConfirmationClient extends Mailable
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
            ->view('emails.client_projects.client_fixed_schedule_confirmation', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
                'calendarLink' => GoogleCalendarLink::forClient($this->project),
            ]);
    }
}
