<?php

namespace App\Console\Commands;

use App\Mail\ClientProjectCommencementEveAdmin;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\MoceanSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Once per calendar day (Toronto), notify the admin the day before each
 * project's scheduled start_date — unless we already sent for this start_date.
 *
 * Channels (configure one or both):
 *   - Email: CLIENT_PROJECTS_COMMENCEMENT_EVE_TO (admin)
 *   - SMS:   MOCEAN_API_TOKEN + optional MOCEAN_FROM; sends to the project's
 *            client_phone (10-digit NANP stored as 1647… for Mocean).
 *
 * Requires `php artisan schedule:run` every minute from cron.
 */
class NotifyClientProjectCommencementEve extends Command
{
    protected $signature = 'client-projects:notify-commencement-eve {--dry-run : List matches without sending}';

    protected $description = 'Day-before commencement: optional admin email + SMS to the client (Mocean).';

    public function handle(ClientProjectStatusService $statusService, MoceanSmsService $moceanSms, ClientCommunicationLogger $logger): int
    {
        $tz = 'America/Toronto';
        $tomorrow = Carbon::tomorrow($tz)->toDateString();

        $emailTo = (string) config('client_projects.commencement_eve_email_to');
        $smsReady = $moceanSms->isConfigured();

        if (!$this->option('dry-run') && $emailTo === '' && !$smsReady) {
            $this->warn('Neither commencement email nor Mocean SMS is configured; skipping.');

            return self::SUCCESS;
        }

        $projects = ClientProject::query()
            ->whereDate('start_date', $tomorrow)
            ->whereNotIn('status', [
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

        $this->info('Projects starting tomorrow ('.$tomorrow.'): '.$projects->count());

        foreach ($projects as $project) {
            if ($this->option('dry-run')) {
                $line = '  [dry-run] #'.$project->id.' '.$project->name;
                if ($smsReady) {
                    $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
                    $line .= $smsTo !== null ? ' → SMS '.$smsTo : ' → SMS skipped (no valid client_phone)';
                }
                $this->line($line);

                continue;
            }

            $emailOk = $emailTo === '';
            $smsOk = !$smsReady;

            $startLabel = optional($project->start_date)->format('M j, Y') ?: 'tomorrow';
            $emailDescription = "Day-before-start admin reminder — project starts {$startLabel}";

            if ($emailTo !== '') {
                try {
                    Mail::to($emailTo)->send(new ClientProjectCommencementEveAdmin($project));
                    $emailOk = true;
                    $this->info('Email sent for project #'.$project->id.' '.$project->name);

                    $logger->logEmail(
                        $project,
                        ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                        $emailTo,
                        null,
                        'Project starts tomorrow — '.($project->name ?? '#'.$project->id),
                        $emailDescription,
                        ClientCommunicationLog::DIRECTION_TO_ADMIN,
                    );
                } catch (\Throwable $e) {
                    Log::error('Commencement eve email failed', [
                        'client_project_id' => $project->id,
                        'message' => $e->getMessage(),
                    ]);

                    $logger->logEmail(
                        $project,
                        ClientCommunicationLog::REASON_COMMENCEMENT_EVE_ADMIN,
                        $emailTo,
                        null,
                        'Project starts tomorrow — '.($project->name ?? '#'.$project->id),
                        $emailDescription,
                        ClientCommunicationLog::DIRECTION_TO_ADMIN,
                        false,
                        $e->getMessage(),
                    );

                    $this->error('Email failed for project #'.$project->id.': '.$e->getMessage());
                }
            }

            if ($smsReady) {
                $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
                $smsDescription = "Day-before-start SMS to client — project starts {$startLabel}";

                if ($smsTo === null) {
                    Log::warning('Commencement eve SMS skipped: no valid client_phone', [
                        'client_project_id' => $project->id,
                    ]);
                    $this->warn('SMS skipped for project #'.$project->id.' (no valid client_phone).');
                    $smsOk = false;
                } else {
                    $smsBody = $this->commencementSmsBody($project);
                    $result = $moceanSms->send($smsTo, $smsBody);
                    if ($result['ok']) {
                        $smsOk = true;
                        $this->info('SMS sent to client for project #'.$project->id.' '.$project->name);

                        $logger->logSms(
                            $project,
                            ClientCommunicationLog::REASON_COMMENCEMENT_EVE_SMS,
                            $smsTo,
                            $smsDescription.' — "'.Str::limit($smsBody, 140).'"',
                        );
                    } else {
                        Log::error('Commencement eve SMS failed', [
                            'client_project_id' => $project->id,
                            'detail' => $result['detail'],
                        ]);

                        $logger->logSms(
                            $project,
                            ClientCommunicationLog::REASON_COMMENCEMENT_EVE_SMS,
                            $smsTo,
                            $smsDescription.' — "'.Str::limit($smsBody, 140).'"',
                            ClientCommunicationLog::DIRECTION_TO_CLIENT,
                            false,
                            $result['detail'],
                        );

                        $this->error('SMS failed for project #'.$project->id.': '.$result['detail']);
                    }
                }
            }

            if ($emailOk && $smsOk) {
                $project->day_before_start_sent_for_date = $project->start_date;
                $project->save();

                $parts = [];
                if ($emailTo !== '') {
                    $parts[] = 'email to '.$emailTo;
                }
                if ($smsReady) {
                    $parts[] = 'SMS to client (Mocean)';
                }
                $statusService->recordEvent(
                    $project,
                    'Day-before commencement reminder sent ('.implode(', ', $parts).').'
                );
            }
        }

        return self::SUCCESS;
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
