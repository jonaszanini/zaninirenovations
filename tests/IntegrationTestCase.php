<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature / integration tests that require a migrated MySQL database.
 *
 * Create the database once:
 *   CREATE DATABASE zanini_renovations_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 *
 * Then run: php artisan test --testsuite=Feature
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Integration database unavailable: '.$e->getMessage());
        }
    }
}
