<?php

namespace App\Mail;

use App\Models\ClientProject;
use App\Services\GoogleCalendarLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client for hourly projects to confirm that the service is
 * officially on the calendar. The rate was agreed verbally up front,
 * so this email doubles as the "we are good to go" confirmation.
 */
class ClientProjectServiceConfirmationClient extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProject $project;

    public function __construct(ClientProject $project)
    {
        $this->project = $project;
    }

    public function build(): self
    {
        $subject = 'Service Confirmation: Your project with Zanini Renovations is on the calendar';

        return $this->subject($subject)
            ->view('emails.client_projects.client_service_confirmation', [
                'project' => $this->project,
                'siteName' => (string) config('mail.from.name', 'Zanini Renovations'),
                // Null when the project doesn't have both dates set.
                // The template hides the "Add to calendar" button in that case.
                'calendarLink' => GoogleCalendarLink::forClient($this->project),
            ]);
    }
}
