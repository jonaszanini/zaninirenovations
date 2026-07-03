<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

/**
 * Hard stop that prevents data-destroying artisan commands from ever running
 * against the production database.
 *
 * Why this exists: the local .env intentionally points DB_* at the live
 * Hostinger database, so a stray `migrate:fresh` / `db:wipe` run locally would
 * drop production tables. This guard aborts those commands the moment the
 * resolved connection looks like production.
 *
 * It deliberately does NOT block plain `migrate` (the deploy needs it and it
 * only moves schema forward without dropping data). It only blocks the
 * commands that drop tables or reset/seed data.
 *
 * Override (for genuine, intentional production maintenance) by setting
 * ALLOW_PRODUCTION_DB_COMMANDS=true in the environment for that one run.
 */
final class ProductionDatabaseGuard
{
    /** Commands that drop tables, reset migrations, or wipe/seed data. */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    /** Substrings in a DB host that identify the Hostinger production server. */
    private const PRODUCTION_HOST_FRAGMENTS = [
        'hstgr.io',
        'auth-db',
        'zaninirenovations.ca',
    ];

    /** Substrings in a DB name that identify the production database. */
    private const PRODUCTION_DATABASE_FRAGMENTS = [
        'u372714627',
    ];

    public static function handle(CommandStarting $event): void
    {
        $command = (string) $event->command;

        if ($command === '' || ! in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            return;
        }

        if (self::isOverridden()) {
            return;
        }

        [$host, $database] = self::resolveTarget($event);

        if (! self::looksLikeProduction($host, $database)) {
            return;
        }

        throw new RuntimeException(self::message($command, $host, $database));
    }

    /**
     * @return array{0: string, 1: string} [host, database]
     */
    private static function resolveTarget(CommandStarting $event): array
    {
        $default = (string) config('database.default', 'mysql');

        $connection = $default;
        $input = $event->input;
        if ($input !== null && $input->hasOption('database')) {
            $chosen = (string) ($input->getOption('database') ?? '');
            if ($chosen !== '') {
                $connection = $chosen;
            }
        }

        $host = strtolower((string) config("database.connections.{$connection}.host", ''));
        $database = (string) config("database.connections.{$connection}.database", '');

        return [$host, $database];
    }

    private static function looksLikeProduction(string $host, string $database): bool
    {
        foreach (self::PRODUCTION_HOST_FRAGMENTS as $fragment) {
            if ($host !== '' && str_contains($host, $fragment)) {
                return true;
            }
        }

        // The dedicated local test database (e.g. *_test) is never production.
        if ($database !== '' && ! str_ends_with(strtolower($database), '_test')) {
            foreach (self::PRODUCTION_DATABASE_FRAGMENTS as $fragment) {
                if (str_contains(strtolower($database), strtolower($fragment))) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isOverridden(): bool
    {
        $value = getenv('ALLOW_PRODUCTION_DB_COMMANDS');
        if ($value === false) {
            $value = env('ALLOW_PRODUCTION_DB_COMMANDS');
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function message(string $command, string $host, string $database): string
    {
        return implode("\n", [
            '',
            '  ┌─────────────────────────────────────────────────────────────────┐',
            '  │  BLOCKED: refusing to run a destructive command on PRODUCTION.    │',
            '  └─────────────────────────────────────────────────────────────────┘',
            '',
            "  Command : php artisan {$command}",
            "  Host    : ".($host !== '' ? $host : '(unknown)'),
            "  Database: ".($database !== '' ? $database : '(unknown)'),
            '',
            '  This database looks like the live Hostinger production database.',
            '  Commands that drop or reset tables are not allowed against it.',
            '',
            '  - For local work, run with the test environment:',
            '        php artisan '.$command.' --env=testing',
            '    (.env.testing points at the local test database).',
            '',
            '  - If you REALLY mean to run this on production, set:',
            '        ALLOW_PRODUCTION_DB_COMMANDS=true',
            '    for that single run, and make sure you have a fresh backup first.',
            '',
        ]);
    }
}
