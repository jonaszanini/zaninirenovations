<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 6×6 fence post catalogue rows for quotes that use post_size = 6x6.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $rows = [
            ['key' => 'post_6x6_10', 'name' => "Post 6 x 6 x 10'", 'sort_order' => 25],
            ['key' => 'post_6x6_12', 'name' => "Post 6 x 6 x 12'", 'sort_order' => 26],
        ];

        foreach ($rows as $row) {
            DB::table('fence_materials')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'price_cad' => 0.00,
                    'url' => null,
                    'quantity_per_box' => null,
                    'sort_order' => $row['sort_order'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('fence_materials')->whereIn('key', ['post_6x6_10', 'post_6x6_12'])->delete();
    }
};
