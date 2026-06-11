<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GuardsProductionDatabase;

/**
 * Feature / integration tests that require a migrated MySQL database.
 *
 * Tests never use your local .env database — phpunit.xml forces
 * 127.0.0.1 / zanini_renovations_test. If you develop against prod .env
 * (no local MySQL), these tests skip locally and run in GitHub Actions instead.
 *
 * Optional local setup:
 *   CREATE DATABASE zanini_renovations_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 */
abstract class IntegrationTestCase extends TestCase
{
    use GuardsProductionDatabase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->guardAgainstProductionDatabase();

        try {
            parent::setUp();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Integration database unavailable: '.$e->getMessage());
        }
    }
}
