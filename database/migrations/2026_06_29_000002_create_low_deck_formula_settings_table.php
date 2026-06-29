<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * low_deck_formula_settings — singleton row (id = 1) storing the tunable
 * parameters of the Low Deck floating quotation calculator. One row,
 * updated in place, so the engineering and retail assumptions can be
 * tweaked from the admin UI without a code deploy.
 *
 * Defaults follow the Ontario Building Code conventions used in the
 * project spec (max 55 m² / 592 sq ft, 5 ft block grid, 9' 4" max 2x6
 * span at 12" O.C., 5.5" boards with a 1/8" gap, etc.).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('low_deck_formula_settings')) {
            return;
        }

        Schema::create('low_deck_formula_settings', function (Blueprint $table) {
            $table->id();

            // Guardrail: reject decks larger than this (square feet).
            $table->decimal('max_area_sq_ft', 8, 2)->default(592.00);

            // Foundation block grid: maximum spacing on centre (feet).
            $table->decimal('block_spacing_ft', 5, 2)->default(5.00);

            // Joist spacing on centre (inches). The default the form offers.
            $table->unsignedSmallInteger('default_joist_spacing_in')->default(16);

            // Maximum clear joist span before an interior flush beam is added
            // (inches). 112 in = 9' 4" for 2x6 at 12" O.C.
            $table->decimal('max_joist_span_in', 6, 2)->default(112.00);

            // Decking board geometry.
            $table->decimal('board_width_in', 5, 3)->default(5.500);
            $table->decimal('board_gap_in', 5, 3)->default(0.125);

            // Linear feet of 4x4 post allocated per block location.
            $table->decimal('post_length_per_block_ft', 5, 2)->default(2.00);

            // Gravel: 3" depth inside a 12"x12" footprint per block.
            $table->decimal('gravel_depth_in', 5, 2)->default(3.00);
            $table->decimal('gravel_footprint_in', 5, 2)->default(12.00);
            // Usable volume per gravel bag (cubic feet). A 0.5 cu ft bag is
            // the common Home Depot retail size.
            $table->decimal('gravel_cubic_ft_per_bag', 6, 3)->default(0.500);

            // Fasteners.
            $table->unsignedSmallInteger('structural_screws_per_post')->default(2);
            $table->unsignedSmallInteger('structural_screws_per_corner')->default(4);
            // Deck board screws: per inch of board length (2 screws / 10 in).
            $table->decimal('board_screws_per_inch', 6, 4)->default(0.2000);
            $table->unsignedSmallInteger('frame_screws_per_t_joint')->default(3);
            $table->unsignedSmallInteger('frame_screws_per_cross_joint')->default(6);

            // Joist tape (shield tops of joists).
            $table->decimal('joist_tape_roll_length_ft', 6, 2)->default(50.00);
            $table->decimal('joist_tape_overlap_in', 5, 2)->default(2.00);

            // Saw kerf used by the bin-packing optimiser (inches).
            $table->decimal('kerf_in', 5, 3)->default(0.125);

            // Labour charged per square foot of deck surface.
            $table->decimal('labour_rate_per_sqft', 8, 2)->default(12.00);

            // Flat per-project waste material dump / disposal fee.
            $table->decimal('waste_dump_fee_cad', 8, 2)->default(60.00);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_deck_formula_settings');
    }
};
