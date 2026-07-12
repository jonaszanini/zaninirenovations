<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'rate_key' => 'helper_hourly_rate',
                'label' => 'Helper hourly rate',
                'unit' => 'hr',
                'price_before_tax' => 21.00,
                'sort_order' => 12,
            ],
            [
                'rate_key' => 'owner_hourly_rate',
                'label' => 'Owner hourly rate',
                'unit' => 'hr',
                'price_before_tax' => 65.00,
                'sort_order' => 13,
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('paving_material_rates')->where('rate_key', $row['rate_key'])->exists()) {
                continue;
            }

            DB::table('paving_material_rates')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('paving_material_rates')
            ->whereIn('rate_key', ['helper_hourly_rate', 'owner_hourly_rate'])
            ->delete();
    }
};
