<?php

namespace App\Console\Commands;

use App\Mail\ClientProjectCommencementEveAdmin;
use App\Mail\ClientProjectCommencementEveClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Once per calendar day (Toronto), notify the day before each project's
 * scheduled start_date — unless we already sent for this start_date.
 *
 * Client channels (heads-up that work starts tomorrow):
 *   - Email to client_email (+ optional secondary CC)
 *   - SMS via Mocean when configured and client_phone is valid
 *
 * Optional admin email: CLIENT_PROJECTS_COMMENCEMENT_EVE_TO
 *
 * Runs via `automations:run-daily` (HTTP cron at 9am EST).
 */
class NotifyClientProjectCommencementEve extends Command
{
    protected $signature = 'client-projects:notify-commencement-eve {--dry-run : List matches without sending}';

    protected $description = 'Day-before commencement: email + SMS to the client (+ optional admin email).';

    public function handle(
        ClientProjectStatusService $statusService,
        MoceanSmsService $moceanSms,
        ClientSmsService $clientSms,
        ClientCommunicationLogger $logger,
    ): int {
        $tz = 'America/Toronto';
        $tomorrow = Carbon::tomorrow($tz)->toDateString();

        $adminEmailTo = (string) config('client_projects.commencement_eve_email_to');
        $smsReady = $moceanSms->isConfigured();

        $projects = ClientProject::query()
            ->whereDate('start_date', $tomorrow)
            ->whereNotIn('status', [
                ClientProject::STATUS_CANCELLED,
                ClientProject::STATUS_REJECTED,
                ClientProject::STATUS_COMPLETED,
            ])
            ->where(function ($q) {
                $q->whereNull('day_before_start_sent_for_date')
                    ->orWhereColumn('day_before_start_sent_for_date', '!=', 'start_date');
            })
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with start date '.$tomorrow.' (or all already notified for current start date).');

            return self::SUCCESS;
        }

        if (! $this->option('dry-run') && $adminEmailTo === '' && ! $smsReady && ! $this->anyProjectHasClientEmail($projects)) {
            $this->warn('No delivery channels available (admin email, client email, or Mocean SMS); skipping.');

            return self::SUCCESS;
        }

        $this->info('Projects starting tomorrow ('.$tomorrow.'): '.$projects->count());

        foreach ($projects as $project) {
            if ($this->option('dry-run')) {
                $this->line($this->dryRunLine($project, $adminEmailTo, $smsReady));

                continue;
            }

            $startLabel = optional($project->start_date)->format('M j, Y') ?: 'tomorrow';

            $clientEmail = $this->clientEmailFor($project);
            $clientEmailOk = $clientEmail === null;
            $smsResolvable = false;
            $smsOk = ! $smsReady;

            if ($clientEmail !== null) {
                $clientEmailOk = $this->sendClientEmail($project, $clientEmail, $startLabel, $logger);
            }

            if ($smsReady) {
                [$smsResolvable, $smsOk] = $this->sendClientSms($project, $clientSms, $startLabel);
            }

            if ($adminEmailTo !== '') {
                $this->sendAdminEmail($project, $adminEmailTo, $startLabel, $logger);
            }

            if ($this->shouldMarkCommencementEveComplete($clientEmail !== null, $clientEmailOk, $smsReady, $smsResolvable, $smsOk)) {
                $project->day_before_start_sent_for_date = $project->start_date;
                $project->save();

                $parts = [];
                if ($clientEmail !== null && $clientEmailOk) {
                    $parts[] = 'email to '.$project->clientEmailRecipientsLabel();
                }
                if ($smsReady && $smsResolvable && $smsOk) {
                    $parts[] = 'SMS to '.$project->clientSmsRecipientsLabel();
                }
                if ($parts !== []) {
                    $statusService->recordEvent(
                        $project,
                        'Day-before commencement reminder sent ('.implode(', ', $parts).').'
                    );
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ClientProject>  $projects
     */
    private function anyProjectHasClientEmail($projects): bool
    {
        foreach ($projects as $project) {
            if ($this->clientEmailFor($project) !== null) {
                return true;
            }
        }

        return false;
    }

    private function dryRunLine(ClientProject $project, string $adminEmailTo, bool $smsReady): string
    {
        $line = '  [dry-run] #'.$project->id.' '.$project->name;
        $channels = [];

        $clientEmail = $this->clientEmailFor($project);
        if ($clientEmail !== null) {
            $channels[] = 'email → '.$project->clientEmailRecipientsLabel();
        }

        if ($smsReady) {
            $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
            $channels[] = $smsTo !== null
                ? 'SMS → '.$project->clientSmsRecipientsLabel()
                : 'SMS skipped (no valid client_phone)';
        }

        if ($adminEmailTo !== '') {
            $channels[] = 'admin email → '.$adminEmailTo;
        }

        $line .= $channels !== [] ? ' → '.implode('; ', $channels) : ' → no channels';

        return $line;
    }

    private function clientEmailFor(ClientProject $project): ?string
    {
        $email = trim((string) $project->client_email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function sendClientEmail(
        ClientProject $project,
        string $clientEmail,
        string $startLabel,
        ClientCommunicationLogger $logger,
    ): bool {
        $cc = $project->secondaryClientEmail();
        $subject = 'Reminder: your project starts tomorrow — '.($project->name ?? 'your project');
        $description = "Day-before-start email to client — project starts {$startLabel}";

        try {
            $mailer = Mail::to($clientEmail, $project->client_name);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectCommencementEveClient($project));

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_CLIENT,
                $clientEmail,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
            );

            $this->info('Client email sent for project #'.$project->id.' '.$project->name);

            return true;
        } catch (\Throwable $e) {
            Log::error('Commencement eve client email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_CLIENT,
                $clientEmail,
                $cc,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            $this->error('Client email failed for project #'.$project->id.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array{0: bool, 1: bool} [smsResolvable, smsOk]
     */
    private function sendClientSms(
        ClientProject $project,
        ClientSmsService $clientSms,
        string $startLabel,
    ): array {
        $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
        $smsDescription = "Day-before-start SMS to client — project starts {$startLabel}";

        if ($smsTo === null) {
            Log::warning('Commencement eve SMS skipped: no valid client_phone', [
                'client_project_id' => $project->id,
            ]);
            $this->warn('SMS skipped for project #'.$project->id.' (no valid client_phone).');

            return [false, true];
        }

        $smsBody = $this->commencementSmsBody($project);
        $result = $clientSms->sendToClient(
            $project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_COMMENCEMENT_EVE_SMS,
            $smsDescription.' — "'.Str::limit($smsBody, 140).'"',
        );

        if ($result['ok']) {
            $introNote = ! empty($result['intro_sent']) ? ' (compliance intro sent first)' : '';
            $this->info('SMS sent to client for project #'.$project->id.' '.$project->name.$introNote);

            return [true, true];
        }

        Log::error('Commencement eve SMS failed', [
            'client_project_id' => $project->id,
            'detail' => $result['detail'],
        ]);

        $this->error('SMS failed for project #'.$project->id.': '.$result['detail']);

        return [true, false];
    }

    private function sendAdminEmail(
        ClientProject $project,
        string $adminEmailTo,
        string $startLabel,
        ClientCommunicationLogger $logger,
    ): void {
        $emailDescription = "Day-before-start admin reminder — project starts {$startLabel}";
        $subject = 'Project starts tomorrow — '.($project->name ?? '#'.$project->id);

        try {
            Mail::to($adminEmailTo)->send(new ClientProjectCommencementEveAdmin($project));

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                $adminEmailTo,
                null,
                $subject,
                $emailDescription,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
            );

            $this->info('Admin email sent for project #'.$project->id.' '.$project->name);
        } catch (\Throwable $e) {
            Log::error('Commencement eve admin email failed', [
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            $logger->logEmail(
                $project,
                ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                $adminEmailTo,
                null,
                $subject,
                $emailDescription,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
                false,
                $e->getMessage(),
            );

            $this->error('Admin email failed for project #'.$project->id.': '.$e->getMessage());
        }
    }

    private function shouldMarkCommencementEveComplete(
        bool $clientEmailResolvable,
        bool $clientEmailOk,
        bool $smsConfigured,
        bool $smsResolvable,
        bool $smsOk,
    ): bool {
        if ($clientEmailResolvable && ! $clientEmailOk) {
            return false;
        }

        if ($smsConfigured && $smsResolvable && ! $smsOk) {
            return false;
        }

        $clientNotified = ($clientEmailResolvable && $clientEmailOk)
            || ($smsConfigured && $smsResolvable && $smsOk);

        if ($clientNotified) {
            return true;
        }

        return ! $clientEmailResolvable && ! ($smsConfigured && $smsResolvable);
    }

    private function commencementSmsBody(ClientProject $project): string
    {
        $name = Str::limit(trim((string) $project->name), 80, '...');

        $address = trim((string) ($project->client_address ?? ''));
        $atLocation = $address !== ''
            ? ' at '.Str::limit($address, 100, '...')
            : '';

        return 'Hi, this is Zanini Renovations. Just a reminder that we are scheduled to start your '
            .$name.$atLocation.' tomorrow. See you then!';
    }
}
