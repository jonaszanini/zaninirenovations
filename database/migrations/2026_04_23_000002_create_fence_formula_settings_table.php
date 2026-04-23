<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fence_formula_settings — singleton row storing the tunable parameters
 * of the fence quotation calculator. One row, id = 1, updated in place.
 *
 * Everything here can be tweaked from the admin UI without a code
 * deploy (labour rate, concrete bags-per-post for each height, rails
 * per section, etc.).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fence_formula_settings', function (Blueprint $table) {
            $table->id();

            // Extra 2x4x8' rails added per gate (frame + diagonal brace).
            $table->unsignedSmallInteger('gate_rails')->default(5);

            // Concrete bags per post, per fence height. Stored as decimals
            // because the 6' default (1.78) is intentionally fractional.
            $table->decimal('concrete_per_post_6ft', 6, 3)->default(1.780);
            $table->decimal('concrete_per_post_8ft', 6, 3)->default(2.500);

            // Labour charged per linear foot (no HST applied on labour).
            $table->decimal('labour_rate_per_ft', 8, 2)->default(55.00);

            // Horizontal rails between two adjacent posts, per fence height.
            // 6' fences traditionally use top + bottom (2). 8' adds a middle
            // rail for rigidity (3).
            $table->unsignedTinyInteger('rails_per_section_6ft')->default(2);
            $table->unsignedTinyInteger('rails_per_section_8ft')->default(3);

            // Nails driven into every picket (typically 2 per rail level).
            $table->unsignedTinyInteger('nails_per_picket')->default(6);

            // Picket geometry used when computing picket count along a span.
            // 5.5" is the actual width of nominal 1x6 lumber; the 0.125"
            // (1/8") gap is the Ontario-standard picket spacing.
            $table->decimal('board_width_in', 5, 3)->default(5.500);
            $table->decimal('standard_gap_in', 5, 3)->default(0.125);

            // Maximum post-to-post span (feet). Structural rule, rarely
            // changes, but exposing it avoids hard-coding magic numbers.
            $table->decimal('max_post_spacing_ft', 5, 2)->default(8.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fence_formula_settings');
    }
};
