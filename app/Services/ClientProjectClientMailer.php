<?php

namespace App\Services;

use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a client-facing notification email (with CC to the secondary email
 * when set), logs it to the communications log either way, and reports
 * failures without throwing — every ClientProjectController "send X to the
 * client" action (service confirmation, schedule dates, time summary, etc.)
 * follows this exact send → log → catch → log-failure shape.
 */
class ClientProjectClientMailer
{
    /**
     * @return string|null The exception message on failure, or null on success.
     */
    public function send(
        ClientProject $project,
        Mailable $mailable,
        string $reason,
        string $subject,
        string $description,
        string $logContext,
    ): ?string {
        $cc = $project->secondaryClientEmail();

        try {
            $mailer = Mail::to($project->client_email, $project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send($mailable);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                $reason,
                $project->client_email,
                $cc,
                $subject,
                $description,
            );

            return null;
        } catch (\Throwable $e) {
            Log::error("Failed to email {$logContext}", [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                $reason,
                $project->client_email,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return $e->getMessage();
        }
    }
}
