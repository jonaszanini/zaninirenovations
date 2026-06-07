<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use Illuminate\Support\Str;

/**
 * Outbound SMS to clients (Mocean) with a one-time compliance intro per
 * recipient before the first project-related text.
 *
 * Intro state is stored on the Client directory when linked; otherwise we
 * fall back to prior successful client SMS rows in the communication log
 * (same normalized phone digits).
 */
class ClientSmsService
{
    public function __construct(
        private MoceanSmsService $mocean,
        private ClientCommunicationLogger $logger,
    ) {}

    /**
     * First-time notice sent before any quote / invoice / project SMS.
     */
    public static function introMessage(): string
    {
        return 'Zanini Renovations: Hi — this is an automated message from Zanini Renovations. '
            .'For support, email zaninirenovations@gmail.com or call/text (647) 834-7570. '
            .'Do not reply to this number. '
            .'You may receive essential texts about your quotes, invoices, and project updates only. '
            .'We do not send promotions or spam.';
    }

    public function isConfigured(): bool
    {
        return $this->mocean->isConfigured();
    }

    /**
     * Send SMS to a client, delivering the intro first when required.
     *
     * @return array{ok: bool, detail: string, intro_sent: bool}
     */
    public function sendToClient(
        ClientProject $project,
        string $phoneDigits,
        string $body,
        string $reason,
        string $logDescription,
    ): array {
        if (! $this->mocean->isConfigured()) {
            return ['ok' => false, 'detail' => 'Mocean is not configured (missing MOCEAN_API_TOKEN).', 'intro_sent' => false];
        }

        $introSent = false;
        if (! $this->hasReceivedIntro($project, $phoneDigits)) {
            $introResult = $this->sendIntro($project, $phoneDigits);
            if (! $introResult['ok']) {
                return ['ok' => false, 'detail' => $introResult['detail'], 'intro_sent' => false];
            }
            $introSent = true;
        }

        $result = $this->mocean->send($phoneDigits, $body);

        $this->logger->logSms(
            $project,
            $reason,
            $phoneDigits,
            $logDescription,
            ClientCommunicationLog::DIRECTION_TO_CLIENT,
            $result['ok'],
            $result['ok'] ? null : $result['detail'],
        );

        return [
            'ok' => $result['ok'],
            'detail' => $result['detail'],
            'intro_sent' => $introSent,
        ];
    }

    /**
     * Whether this phone has already received any client-facing SMS (intro
     * or project message) from us.
     */
    public function hasReceivedIntro(ClientProject $project, string $phoneDigits): bool
    {
        $client = Client::resolveForProject($project);
        if ($client && $client->sms_intro_sent_at !== null) {
            return true;
        }

        return ClientCommunicationLog::query()
            ->where('channel', ClientCommunicationLog::CHANNEL_SMS)
            ->where('direction', ClientCommunicationLog::DIRECTION_TO_CLIENT)
            ->where('recipient', $phoneDigits)
            ->where('status', ClientCommunicationLog::STATUS_SENT)
            ->exists();
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function sendIntro(ClientProject $project, string $phoneDigits): array
    {
        $body = self::introMessage();
        $result = $this->mocean->send($phoneDigits, $body);

        $this->logger->logSms(
            $project,
            ClientCommunicationLog::REASON_SMS_INTRO,
            $phoneDigits,
            'SMS compliance intro — "'.Str::limit($body, 120).'"',
            ClientCommunicationLog::DIRECTION_TO_CLIENT,
            $result['ok'],
            $result['ok'] ? null : $result['detail'],
        );

        if ($result['ok']) {
            $this->markIntroSent($project, $phoneDigits);
        }

        return $result;
    }

    private function markIntroSent(ClientProject $project, string $phoneDigits): void
    {
        $client = Client::resolveForProject($project);

        if ($client && $client->sms_intro_sent_at === null) {
            $client->sms_intro_sent_at = now();
            $client->save();
        }

        // When there is no directory client, the successful intro log row
        // above is what prevents duplicate intros on future sends.
    }
}
