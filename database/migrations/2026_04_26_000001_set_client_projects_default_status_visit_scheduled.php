<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New projects default to "visit_scheduled" (first workflow step).
 * Existing rows are unchanged.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('client_projects')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE client_projects MODIFY status VARCHAR(255) NOT NULL DEFAULT 'visit_scheduled'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('client_projects')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE client_projects MODIFY status VARCHAR(255) NOT NULL DEFAULT 'quote_pending'");
        }
    }
};
