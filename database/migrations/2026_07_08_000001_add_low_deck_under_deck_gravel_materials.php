<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'key' => 'under_deck_gravel_30kg',
                'name' => '30kg Gravel bag',
                'sort_order' => 52,
            ],
            [
                'key' => 'weed_fabric_3x50',
                'name' => '3 ft. x 50 ft. Weed Control Fabric',
                'sort_order' => 54,
            ],
            [
                'key' => 'weed_fabric_3x100',
                'name' => '3 ft. x 100 ft. Weed Control Fabric',
                'sort_order' => 55,
            ],
            [
                'key' => 'fabric_spikes_30pack',
                'name' => 'Fabric Spikes (30-Pack)',
                'sort_order' => 56,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('low_deck_materials')->updateOrInsert(
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
        DB::table('low_deck_materials')->whereIn('key', [
            'under_deck_gravel_30kg',
            'weed_fabric_3x50',
            'weed_fabric_3x100',
            'fabric_spikes_30pack',
        ])->delete();
    }
};
