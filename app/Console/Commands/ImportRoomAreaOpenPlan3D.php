<?php

namespace App\Console\Commands;

use App\Services\RoomAreaDrawingImporter;
use Illuminate\Console\Command;

class ImportRoomAreaOpenPlan3D extends Command
{
    protected $signature = 'room-area:import-openplan3d
                            {--dry-run : Simulate conversion without modifying the database}
                            {--force : Force re-conversion of rooms already in openPlan3D format}';

    protected $description = 'Import legacy 2D Room Area canvas drawings into openPlan3D format while backing up legacy data';

    public function handle(RoomAreaDrawingImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be written to the database.');
        } else {
            $this->info('Starting conversion of Room Area drawings to openPlan3D format...');
        }

        $report = $importer->run(dryRun: $dryRun, force: $force);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rooms Examined', $report['total']],
                ['Successfully Converted', $report['converted']],
                ['Skipped (Already openPlan3D)', $report['skipped']],
                ['Failed', $report['failed']],
            ]
        );

        if ($report['results'] !== []) {
            $this->newLine();
            $this->info('Conversion Details:');
            $this->table(
                ['ID', 'Name', 'Status', 'Old Area (sqft)', 'New Area (sqft)', 'Notes'],
                array_map(static fn (array $r) => [
                    $r['room_id'],
                    $r['room_name'],
                    $r['status'],
                    $r['old_area'] !== null ? number_format($r['old_area'], 2) : 'N/A',
                    $r['new_area'] !== null ? number_format($r['new_area'], 2) : 'N/A',
                    $r['message'],
                ], $report['results'])
            );
        }

        if ($report['failed'] > 0) {
            $this->newLine();
            $this->error("Encountered {$report['failed']} failure(s) during conversion:");
            $this->table(
                ['Room ID', 'Room Name', 'Failure Reason'],
                array_map(static fn (array $f) => [
                    $f['room_id'],
                    $f['room_name'],
                    $f['message'],
                ], $report['failures'])
            );

            return self::FAILURE;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info('Dry run completed successfully. Run without --dry-run to commit changes.');
        } else {
            $this->info('All candidate rooms successfully imported to openPlan3D!');
        }

        return self::SUCCESS;
    }
}
