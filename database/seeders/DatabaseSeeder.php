<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Local / CI demo data. Not used in production deploys.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DarkModeAuditSeeder::class);
    }
}
