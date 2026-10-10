<?php

namespace App\Console\Commands;

use App\Services\StandardQuotationKitRewriteAuditor;
use Illuminate\Console\Command;

/**
 * Read-only report of Standard quotations likely damaged by the #65
 * Room Area kit-add rewrite. Does not write to the database.
 */
class AuditKitRewriteQuotes extends Command
{
    protected $signature = 'quotes:audit-kit-rewrite';

    protected $description = 'List Standard quotes likely damaged by the Room Area kit-add rewrite (read-only)';

    public function handle(StandardQuotationKitRewriteAuditor $auditor): int
    {
        $findings = $auditor->findings();

        if ($findings === []) {
            $this->info('No Standard quotes look damaged by the kit-add rewrite.');

            return self::SUCCESS;
        }

        $this->warn(count($findings).' Standard quote(s) look damaged by the kit-add rewrite.');
        $this->table(
            ['id', 'quote_number', 'saved_total', 'expected_total', 'issues'],
            array_map(static function (array $row) {
                return [
                    $row['id'],
                    $row['quote_number'],
                    number_format((float) $row['grand_total'], 2, '.', ''),
                    number_format((float) $row['expected_total'], 2, '.', ''),
                    implode(', ', $row['issues']),
                ];
            }, $findings)
        );
        $this->newLine();
        $this->comment('Read-only. Review each quote in the editor before changing anything.');

        return self::SUCCESS;
    }
}
