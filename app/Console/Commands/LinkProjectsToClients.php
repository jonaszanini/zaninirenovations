<?php

namespace App\Console\Commands;

use App\Models\ClientProject;
use App\Services\ClientProjectContactService;
use Illuminate\Console\Command;

/**
 * One-time (or repeatable) backfill: ensure every project has a valid
 * client_id, matching existing directory rows by email or name+phone before
 * creating new clients.
 */
class LinkProjectsToClients extends Command
{
    protected $signature = 'clients:link-projects
                            {--dry-run : Report actions without writing to the database}';

    protected $description = 'Link all projects to the clients directory (match by email, then name+phone, else create).';

    public function handle(ClientProjectContactService $contacts): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be saved.');
        }

        $stats = [
            'already_linked' => 0,
            'linked_existing' => 0,
            'created' => 0,
            'skipped_no_name' => 0,
        ];

        $total = ClientProject::query()->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        ClientProject::query()
            ->orderBy('id')
            ->chunkById(100, function ($projects) use ($contacts, $dryRun, &$stats, $bar): void {
                foreach ($projects as $project) {
                    $result = $contacts->linkProjectToClientDirectory($project, $dryRun);
                    $stats[$result] = ($stats[$result] ?? 0) + 1;

                    if ($result !== 'already_linked') {
                        $this->logProjectAction($project, $result, $dryRun);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Outcome', 'Count'],
            [
                ['Already linked (valid client_id)', $stats['already_linked']],
                ['Linked to existing client', $stats['linked_existing']],
                [$dryRun ? 'Would create new client' : 'Created new client', $stats['created']],
                ['Skipped (no client name on project)', $stats['skipped_no_name']],
            ]
        );

        $processed = array_sum($stats);
        $verb = $dryRun ? 'Dry run complete' : 'Done';
        $this->info("{$verb}: {$processed} project(s) processed.");

        return self::SUCCESS;
    }

    protected function logProjectAction(ClientProject $project, string $result, bool $dryRun): void
    {
        $prefix = $dryRun ? '[dry-run] ' : '';
        $label = match ($result) {
            'linked_existing' => 'linked to existing client',
            'created' => $dryRun ? 'would create client' : 'created client',
            'skipped_no_name' => 'skipped (no client name)',
            default => $result,
        };

        $this->line(sprintf(
            '%sProject #%d (%s) — %s',
            $prefix,
            $project->id,
            $project->getRawOriginal('client_name') ?: '—',
            $label
        ));
    }
}
