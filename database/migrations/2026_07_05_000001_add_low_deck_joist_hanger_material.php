<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('low_deck_materials')->updateOrInsert(
            ['key' => 'joist_hanger_2x6'],
            [
                'name' => '2x6 Joist Hanger',
                'price_cad' => 0.00,
                'url' => null,
                'quantity_per_box' => null,
                'sort_order' => 95,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('low_deck_materials')->where('key', 'joist_hanger_2x6')->delete();
    }
};
