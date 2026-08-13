<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 16' 5/4x6 deck boards — needed so 13–16 ft runs (e.g. a 15' deck) buy
 * full-length boards instead of splicing 8' stock.
 */
return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('low_deck_materials')->updateOrInsert(
            ['key' => 'board_5_4x6_16'],
            [
                'name' => "5/4 x 6 x 16' Deck Board",
                'price_cad' => 0.00,
                'url' => null,
                'quantity_per_box' => null,
                'sort_order' => 135,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('low_deck_materials')->where('key', 'board_5_4x6_16')->delete();
    }
};
