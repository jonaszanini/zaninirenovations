<?php

namespace App\Console\Commands;

use App\Services\StandardQuotationKitRewriteAuditor;
use Illuminate\Console\Command;

/**
 * Read-only report of Standard quotations that are candidates for review
 * after the #65 Room Area kit-add rewrite. Does not write to the database.
 */
class AuditKitRewriteQuotes extends Command
{
    protected $signature = 'quotes:audit-kit-rewrite
                            {--since= : Only quotes updated on/after this time (default 2026-10-10 02:07 America/Toronto)}';

    protected $description = 'List candidate Standard quotes that may have been damaged by the Room Area kit-add rewrite (read-only)';

    public function handle(StandardQuotationKitRewriteAuditor $auditor): int
    {
        $since = StandardQuotationKitRewriteAuditor::parseSince($this->option('since'));
        $findings = $auditor->findings($since);

        $this->comment('Candidates only — not proof of damage. Read-only; no writes.');
        $this->comment('Since '.$since->timezone(StandardQuotationKitRewriteAuditor::DEFAULT_SINCE_TZ)->toDateTimeString().' America/Toronto.');

        if ($findings === []) {
            $this->info('No candidate Standard quotes in that window.');

            return self::SUCCESS;
        }

        $this->warn(count($findings).' candidate Standard quote(s). Review each in the editor before changing anything.');
        $this->table(
            ['quote_number', 'draft', 'linked_project', 'updated_at', 'signals'],
            array_map(static function (array $row) {
                $signals = $row['issues'];
                if (in_array('hub_amount_mismatch', $signals, true) && $row['hub_total'] !== null) {
                    $signals = array_map(static function (string $signal) use ($row) {
                        if ($signal !== 'hub_amount_mismatch') {
                            return $signal;
                        }

                        return 'hub_amount_mismatch (hub '.number_format((float) $row['hub_total'], 2, '.', '')
                            .' vs quote '.number_format((float) $row['grand_total'], 2, '.', '').')';
                    }, $signals);
                }

                return [
                    $row['quote_number'],
                    $row['is_draft'] ? 'yes' : 'no',
                    $row['linked_project'],
                    $row['updated_at'],
                    implode(', ', $signals),
                ];
            }, $findings)
        );

        return self::SUCCESS;
    }
}
