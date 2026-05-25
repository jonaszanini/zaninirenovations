<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net when commute columns were not created on production
 * (e.g. deploy without migrate, or a failed after() clause).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['standard_quotations', 'fence_quotations', 'paving_quotations', 'stairs_quotations'] as $name) {
            if (! Schema::hasTable($name) || Schema::hasColumn($name, 'include_commute')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->boolean('include_commute')->default(true);
                $table->decimal('commute_trip_percent', 5, 2)->default(100);
                $table->unsignedSmallInteger('commute_estimated_trips')->default(1);
                $table->decimal('commute_round_trip_cost', 10, 2)->nullable();
                $table->decimal('commute_amount', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        // Forward-only safety migration.
    }
};
