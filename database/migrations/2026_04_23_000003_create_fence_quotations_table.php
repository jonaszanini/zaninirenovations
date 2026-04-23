<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fence_quotations — persisted fence estimates. Stores the raw inputs
 * (sides, height, gate count, board style) AND a snapshot of the full
 * breakdown so an older quote's PDF keeps rendering the same even if
 * pricing or formula settings change afterwards.
 *
 * A quote can stand alone (for a walk-in lead) or be "linked" to a
 * client project — linking copies the grand total as a single line
 * item into ClientProjectQuotation and sets client_project_id here
 * so we can back-reference in the UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fence_quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();

            $table->string('quote_number', 40)->unique();
            $table->string('client_name', 160);

            // Inputs (stored so quotes can be re-calculated / edited).
            $table->decimal('fence_height_ft', 4, 2)->default(6);
            $table->string('board_style', 20)->default('standard');   // standard | overlapping
            $table->decimal('overlap_in', 5, 3)->default(0);
            $table->unsignedTinyInteger('gate_count')->default(1);
            $table->json('sides'); // [{length_ft: 20}, ...]

            // Snapshot of the calculator output at save time: materials
            // list with per-row qty + unit price + subtotal, totals, and
            // labour. Keeps old PDFs stable even after config changes.
            $table->json('calculation');

            $table->decimal('materials_subtotal', 10, 2)->default(0);
            $table->decimal('hst', 10, 2)->default(0);
            $table->decimal('labour_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            $table->unsignedInteger('version')->default(1);

            $table->timestamp('linked_at')->nullable();

            $table->timestamps();

            $table->index('client_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fence_quotations');
    }
};
