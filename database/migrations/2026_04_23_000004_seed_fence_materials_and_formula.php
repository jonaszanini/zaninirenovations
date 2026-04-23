<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the fixed list of fence materials and a single formula-settings
 * row. Lives in a migration (not a seeder) so it also runs on prod
 * deploys where seeders aren't invoked automatically.
 *
 * Running this migration twice is safe — rows are upserted by `key`
 * for materials and by `id = 1` for the settings row.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // -------- Materials catalogue ------------------------------------
        $materials = [
            ['key' => 'post_10',        'name' => "Post 4 x 4 x 10'",               'sort_order' => 10],
            ['key' => 'post_12',        'name' => "Post 4 x 4 x 12'",               'sort_order' => 20],
            ['key' => 'board_6',        'name' => "Fence Board 1 x 6 x 6'",         'sort_order' => 30],
            ['key' => 'board_8',        'name' => "Fence Board 1 x 6 x 8'",         'sort_order' => 40],
            ['key' => 'rail',           'name' => "Rails 2 x 4 x 8'",               'sort_order' => 50],
            ['key' => 'concrete',       'name' => 'Concrete bag, 30 Kg',            'sort_order' => 60],
            // Nails are the only material with a "quantity per box" field;
            // it lets the calculator price per nail from a bulk-box cost.
            ['key' => 'nails',          'name' => 'Ring Nails, 2-Inch 22 Degree',   'sort_order' => 70, 'quantity_per_box' => 3000],
            ['key' => 'bracket',        'name' => 'Fence Rail Bracket',             'sort_order' => 80],
            ['key' => 'post_cap',       'name' => 'Post Cap',                       'sort_order' => 90],
            ['key' => 'gate_hardware',  'name' => 'Gate Hardware',                  'sort_order' => 100],
        ];

        foreach ($materials as $row) {
            DB::table('fence_materials')->updateOrInsert(
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

        // -------- Formula settings (singleton) ---------------------------
        DB::table('fence_formula_settings')->updateOrInsert(
            ['id' => 1],
            [
                'gate_rails' => 5,
                'concrete_per_post_6ft' => 1.780,
                'concrete_per_post_8ft' => 2.500,
                'labour_rate_per_ft' => 55.00,
                'rails_per_section_6ft' => 2,
                'rails_per_section_8ft' => 3,
                'nails_per_picket' => 6,
                'board_width_in' => 5.500,
                'standard_gap_in' => 0.125,
                'max_post_spacing_ft' => 8.00,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        // Down-migration is a no-op: the tables themselves are dropped by
        // their own migrations in reverse order, which wipes these rows.
    }
};
