<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $underDeck = DB::table('low_deck_materials')
            ->where('key', 'under_deck_gravel_30kg')
            ->first();

        $gravelBag = DB::table('low_deck_materials')
            ->where('key', 'gravel_bag')
            ->first();

        $payload = [
            'name' => '30kg 1/2in Gravel',
            'updated_at' => $now,
        ];

        // Prefer the under-deck catalogue link if the block gravel row is still unlinked.
        if ($underDeck && filled($underDeck->material_id ?? null)
            && $gravelBag && empty($gravelBag->material_id)) {
            $payload['material_id'] = $underDeck->material_id;
        }

        if ($gravelBag) {
            DB::table('low_deck_materials')
                ->where('key', 'gravel_bag')
                ->update($payload);
        } else {
            DB::table('low_deck_materials')->insert(array_merge($payload, [
                'key' => 'gravel_bag',
                'price_cad' => 0.00,
                'url' => null,
                'quantity_per_box' => null,
                'sort_order' => 50,
                'created_at' => $now,
            ]));
        }

        DB::table('low_deck_materials')->where('key', 'under_deck_gravel_30kg')->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('low_deck_materials')
            ->where('key', 'gravel_bag')
            ->update([
                'name' => 'Gravel bag, 0.5 cu ft',
                'updated_at' => $now,
            ]);

        DB::table('low_deck_materials')->updateOrInsert(
            ['key' => 'under_deck_gravel_30kg'],
            [
                'name' => '30kg Gravel bag',
                'price_cad' => 0.00,
                'url' => null,
                'quantity_per_box' => null,
                'sort_order' => 52,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
};
