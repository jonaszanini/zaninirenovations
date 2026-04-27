<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional Contingency Allowance to standard quotations.
 *
 * The user can toggle a percentage that's applied on top of the
 * materials subtotal (before HST). When enabled, the resulting
 * dollar amount + its HST are baked into materials_hst /
 * materials_with_hst / grand_total so downstream consumers (the
 * project quotation line) inherit the right number with no
 * additional plumbing.
 *
 * contingency_amount stores the pre-HST contingency dollars; the
 * HST on it is rolled into materials_hst on save.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->boolean('contingency_enabled')->default(true)->after('grand_total');
            $table->decimal('contingency_percent', 5, 2)->default(10.00)->after('contingency_enabled');
            $table->decimal('contingency_amount', 12, 2)->default(0)->after('contingency_percent');
            $table->decimal('contingency_hst', 12, 2)->default(0)->after('contingency_amount');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'contingency_enabled',
                'contingency_percent',
                'contingency_amount',
                'contingency_hst',
            ]);
        });
    }
};
