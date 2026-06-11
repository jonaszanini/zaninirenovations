<?php

namespace App\Services;

use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Support\Logging\AppLogger;

/**
 * Single funnel for writing to client_communication_logs.
 *
 * Every place in the app that sends an email or an SMS calls one of
 * the helpers here right after the send attempt (success or failure)
 * so the admin "Email / SMS log" page has a complete picture.
 *
 * Logging never throws — if the audit row itself can't be persisted
 * we swallow the exception and write to laravel.log so the surrounding
 * business logic isn't taken down by an audit-trail failure.
 */
class ClientCommunicationLogger
{
    /**
     * @param  ClientProject|null  $project  The project the message
     *   relates to (optional — admin-only ops may have none).
     * @param  string  $reason  One of ClientCommunicationLog::REASON_*.
     * @param  string  $recipient  Email address.
     * @param  string|null  $cc
     * @param  string  $subject  Email subject.
     * @param  string  $description  Human-readable summary including
     *   amounts / numbers / dates.
     * @param  string  $direction  ClientCommunicationLog::DIRECTION_*
     * @param  bool  $ok  Whether the send succeeded.
     * @param  string|null  $error  Error detail when $ok === false.
     */
    public function logEmail(
        ?ClientProject $project,
        string $reason,
        string $recipient,
        ?string $cc,
        string $subject,
        string $description,
        string $direction = ClientCommunicationLog::DIRECTION_TO_CLIENT,
        bool $ok = true,
        ?string $error = null,
    ): ?ClientCommunicationLog {
        return $this->write([
            'client_project_id' => $project?->id,
            'project_name_snapshot' => $project?->name,
            'client_name_snapshot' => $project?->client_name,
            'channel' => ClientCommunicationLog::CHANNEL_EMAIL,
            'direction' => $direction,
            'reason' => $reason,
            'recipient' => $recipient,
            'cc' => $cc !== '' ? $cc : null,
            'subject' => $subject !== '' ? $subject : null,
            'description' => $description,
            'status' => $ok ? ClientCommunicationLog::STATUS_SENT : ClientCommunicationLog::STATUS_FAILED,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  string  $recipient  Phone digits (e.164 with country code).
     */
    public function logSms(
        ?ClientProject $project,
        string $reason,
        string $recipient,
        string $description,
        string $direction = ClientCommunicationLog::DIRECTION_TO_CLIENT,
        bool $ok = true,
        ?string $error = null,
    ): ?ClientCommunicationLog {
        return $this->write([
            'client_project_id' => $project?->id,
            'project_name_snapshot' => $project?->name,
            'client_name_snapshot' => $project?->client_name,
            'channel' => ClientCommunicationLog::CHANNEL_SMS,
            'direction' => $direction,
            'reason' => $reason,
            'recipient' => $recipient,
            'cc' => null,
            'subject' => null,
            'description' => $description,
            'status' => $ok ? ClientCommunicationLog::STATUS_SENT : ClientCommunicationLog::STATUS_FAILED,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }

    private function write(array $attrs): ?ClientCommunicationLog
    {
        try {
            return ClientCommunicationLog::create($attrs);
        } catch (\Throwable $e) {
            AppLogger::error('client_communication.log.persist_failed', [
                'project_id' => $attrs['client_project_id'] ?? null,
                'channel' => $attrs['channel'] ?? null,
                'reason' => $attrs['reason'] ?? null,
            ], $e);

            return null;
        }
    }
}
