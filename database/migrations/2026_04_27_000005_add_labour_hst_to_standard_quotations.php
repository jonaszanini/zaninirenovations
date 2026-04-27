<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds labour_hst to standard quotations.
 *
 * Labour on a standard quotation is now HST-taxable (always 13%) so
 * the quotation's grand total reflects the full HST inclusive cost.
 * The HST dollar amount on labour is stored separately so it can be
 * surfaced in the totals box and the grand summary card.
 *
 * Existing rows default to 0; the calculator will recompute and save
 * the correct labour_hst the next time the quotation is updated.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->decimal('labour_hst', 12, 2)->default(0)->after('labour_total');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn('labour_hst');
        });
    }
};
