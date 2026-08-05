<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the fixed list of low deck materials and a single formula-settings
 * row. Lives in a migration (not a seeder) so it also runs on prod
 * deploys where seeders aren't invoked automatically.
 *
 * Running this migration twice is safe — rows are upserted by `key` for
 * materials and by `id = 1` for the settings row.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $materials = [
            // Foundation
            ['key' => 'block_4x4',          'name' => 'Concrete Deck Block',                          'sort_order' => 10],
            ['key' => 'post_4x4_8',         'name' => "Pressure-Treated Post 4 x 4 x 8'",             'sort_order' => 20],
            ['key' => 'post_4x4_10',        'name' => "Pressure-Treated Post 4 x 4 x 10'",            'sort_order' => 30],
            ['key' => 'post_4x4_12',        'name' => "Pressure-Treated Post 4 x 4 x 12'",            'sort_order' => 40],
            ['key' => 'gravel_bag',         'name' => 'Gravel bag, 0.5 cu ft',                        'sort_order' => 50],

            // Framing
            ['key' => 'joist_2x6_8',        'name' => "Pressure-Treated 2 x 6 x 8'",                  'sort_order' => 60],
            ['key' => 'joist_2x6_10',       'name' => "Pressure-Treated 2 x 6 x 10'",                 'sort_order' => 70],
            ['key' => 'joist_2x6_12',       'name' => "Pressure-Treated 2 x 6 x 12'",                 'sort_order' => 80],
            ['key' => 'joist_2x6_16',       'name' => "Pressure-Treated 2 x 6 x 16'",                 'sort_order' => 90],

            // Waterproofing
            ['key' => 'joist_tape_50ft',    'name' => "Trex Protect 1-5/8 inch x 50 ft. Butyl Joist Tape for Decking", 'sort_order' => 100, 'quantity_per_box' => 1],

            // Decking
            ['key' => 'board_5_4x6_8',      'name' => "5/4 x 6 x 8' Deck Board",                      'sort_order' => 110],
            ['key' => 'board_5_4x6_10',     'name' => "5/4 x 6 x 10' Deck Board",                     'sort_order' => 120],
            ['key' => 'board_5_4x6_12',     'name' => "5/4 x 6 x 12' Deck Board",                     'sort_order' => 130],

            // Fasteners
            ['key' => 'screw_structural_6in', 'name' => '6 in. Structural Screws',                    'sort_order' => 140, 'quantity_per_box' => 50],
            ['key' => 'screw_deck_2_5in',     'name' => '2-1/2 in. Deck Screws',                      'sort_order' => 150, 'quantity_per_box' => 350],
            ['key' => 'screw_frame_3in',      'name' => '3 in. Frame Screws',                         'sort_order' => 160, 'quantity_per_box' => 100],
        ];

        foreach ($materials as $row) {
            DB::table('low_deck_materials')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'price_cad' => 0.00,
                    'url' => null,
                    'quantity_per_box' => $row['quantity_per_box'] ?? null,
                    'sort_order' => $row['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $settings = [
            'max_area_sq_ft' => 592.00,
            'block_spacing_ft' => 5.00,
            'default_joist_spacing_in' => 16,
            'max_joist_span_in' => 112.00,
            'board_width_in' => 5.500,
            'board_gap_in' => 0.125,
            'post_length_per_block_ft' => 2.00,
            'gravel_depth_in' => 3.00,
            'gravel_footprint_in' => 12.00,
            'gravel_cubic_ft_per_bag' => 0.500,
            'structural_screws_per_post' => 2,
            'structural_screws_per_corner' => 4,
            'board_screws_per_inch' => 0.2000,
            'frame_screws_per_t_joint' => 3,
            'frame_screws_per_cross_joint' => 6,
            'joist_tape_roll_length_ft' => 50.00,
            'joist_tape_overlap_in' => 2.00,
            'kerf_in' => 0.125,
            'labour_rate_per_sqft' => 12.00,
            'waste_dump_fee_cad' => 60.00,
            'updated_at' => $now,
            'created_at' => $now,
        ];

        // Column added later (2026_07_08_...) — on a fresh install this
        // migration runs before that one exists, so only set it when
        // it's already there. The later migration backfills it either way.
        if (Schema::hasColumn('low_deck_formula_settings', 'under_deck_gravel_sq_ft_per_bag')) {
            $settings['under_deck_gravel_sq_ft_per_bag'] = 8.00;
        }

        DB::table('low_deck_formula_settings')->updateOrInsert(['id' => 1], $settings);
    }

    public function down(): void
    {
        // No-op: tables are dropped by their own migrations, which wipes rows.
    }
};
