<?php

namespace Tests\Support;

/**
 * Blocks integration tests from ever running against the production Hostinger database.
 *
 * PHPUnit sets DB_* via phpunit.xml (127.0.0.1 + zanini_renovations_test). A local
 * .env that points at prod must not be used for tests — this guard fails fast if
 * something is misconfigured.
 */
trait GuardsProductionDatabase
{
    protected function guardAgainstProductionDatabase(): void
    {
        if (getenv('APP_ENV') !== 'testing' && config('app.env') !== 'testing') {
            $this->markTestSkipped('Integration tests require APP_ENV=testing.');
        }

        $connection = (string) (getenv('DB_CONNECTION') ?: config('database.default', 'mysql'));
        $host = strtolower((string) (getenv('DB_HOST') ?: config("database.connections.{$connection}.host", '')));
        $database = (string) (getenv('DB_DATABASE') ?: config("database.connections.{$connection}.database", ''));

        foreach (['hstgr.io', 'auth-db', 'zaninirenovations.ca'] as $fragment) {
            if ($host !== '' && str_contains($host, $fragment)) {
                $this->fail("Refusing integration tests: database host [{$host}] looks like production.");
            }
        }

        if ($database !== '' && ! str_ends_with($database, '_test')) {
            $this->fail("Refusing integration tests: database [{$database}] must be a dedicated *_test database.");
        }

        if ($host !== '' && $host !== '127.0.0.1' && $host !== 'localhost') {
            $this->markTestSkipped(
                "Integration tests expect a local test database (127.0.0.1). Current host: [{$host}]."
            );
        }
    }
}
