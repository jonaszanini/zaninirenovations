<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * low_deck_quotations — persisted low floating deck estimates. Stores the
 * raw inputs (width, length, joist spacing, per-quote overrides) AND a
 * snapshot of the full breakdown so an older quote keeps the same totals
 * even after material prices or formula settings change afterwards.
 *
 * A quote can stand alone or be "linked" to a client project — linking
 * copies the pre-tax materials + labour as a line item into a
 * ClientProjectQuotation and sets client_project_id here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('low_deck_quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();

            $table->string('quote_number', 40)->unique();
            $table->string('client_name', 160);
            $table->text('work_description')->nullable();

            // Inputs.
            $table->decimal('width_ft', 7, 2)->default(0);
            $table->decimal('length_ft', 7, 2)->default(0);
            $table->unsignedSmallInteger('joist_spacing_in')->default(16);

            // Per-quote overrides (null = fall back to formula settings).
            $table->decimal('labour_rate_per_sqft', 8, 2)->nullable();
            $table->decimal('waste_dump_fee_cad', 8, 2)->nullable();

            // Include the three layout diagrams on the client-facing PDF.
            $table->boolean('include_diagrams_on_pdf')->default(false);

            // Full calculator snapshot (geometry, packing, materials, totals).
            $table->json('calculation');

            // Denormalised totals for list/index display.
            $table->decimal('materials_subtotal', 10, 2)->default(0);
            $table->decimal('hst', 10, 2)->default(0);
            $table->decimal('labour_total', 10, 2)->default(0);
            $table->decimal('labour_hst', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            // Commute expense (shared trait, mirrors fence_quotations).
            $table->boolean('include_commute')->default(false);
            $table->decimal('commute_trip_percent', 5, 2)->nullable();
            $table->unsignedInteger('commute_estimated_trips')->nullable();
            $table->decimal('commute_round_trip_cost', 10, 2)->nullable();
            $table->decimal('commute_amount', 10, 2)->default(0);

            $table->unsignedInteger('version')->default(1);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_deck_quotations');
    }
};
