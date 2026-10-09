<?php

namespace App\Services;

use App\Models\RoomAreaAssembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoomAreaAssemblyDefaults
{
    public const SEED_KEY = 'defaults_v1';

    /**
     * @return list<array{name: string, notes: string, lines: list<array{name: string, formula: string, unit: string, whole_units: bool}>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Drywall wall',
                'notes' => 'Sheets from net wall sf plus 12% waste, then screws, tape, mud, and outside-corner bead.',
                'lines' => [
                    ['name' => 'Drywall sheets', 'formula' => 'ceil(net_wall_sf * 1.12 / 32)', 'unit' => 'sheet', 'whole_units' => true],
                    ['name' => 'Screws', 'formula' => 'ceil(net_wall_sf * 1.12 / 32) * 32', 'unit' => 'ea', 'whole_units' => true],
                    ['name' => 'Tape', 'formula' => 'ceil(net_wall_sf * 1.12 / 500)', 'unit' => 'roll', 'whole_units' => true],
                    ['name' => 'Joint compound', 'formula' => 'ceil(net_wall_sf * 1.12 / 450)', 'unit' => '4.5 gal pail', 'whole_units' => true],
                    ['name' => 'Corner bead', 'formula' => 'ceil(outside_corners * wall_height / 8)', 'unit' => 'stick', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'LVP floor',
                'notes' => 'Boxes from the room’s floor waste math, plus underlay and a transition per door or opening.',
                'lines' => [
                    ['name' => 'Floor boxes', 'formula' => 'flooring_boxes', 'unit' => 'box', 'whole_units' => true],
                    ['name' => 'Underlay rolls', 'formula' => 'ceil(floor_sf * 1.05 / 100)', 'unit' => 'roll', 'whole_units' => true],
                    ['name' => 'Transitions', 'formula' => 'door_count + opening_count', 'unit' => 'ea', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'Baseboard',
                'notes' => 'Length from the room’s baseboard lf plus 10%, then caulk and nails.',
                'lines' => [
                    ['name' => 'Baseboard', 'formula' => 'baseboard_lf * 1.10', 'unit' => 'lf', 'whole_units' => false],
                    ['name' => 'Caulk', 'formula' => 'ceil(baseboard_lf / 40)', 'unit' => 'tube', 'whole_units' => true],
                    ['name' => 'Nails', 'formula' => 'ceil(baseboard_lf * 1.5 / 1000)', 'unit' => 'box', 'whole_units' => true],
                ],
            ],
        ];
    }

    public function ensure(): void
    {
        if ($this->hasSeedFlag()) {
            return;
        }

        if (! $this->claimSeedFlag()) {
            return;
        }

        foreach (self::definitions() as $index => $def) {
            $assembly = RoomAreaAssembly::query()->firstOrCreate(
                ['name' => $def['name']],
                [
                    'notes' => $def['notes'],
                    'sort_order' => $index,
                ]
            );
            if ($assembly->wasRecentlyCreated) {
                foreach ($def['lines'] as $lineIndex => $line) {
                    $assembly->lines()->create([
                        'name' => $line['name'],
                        'formula' => $line['formula'],
                        'unit' => $line['unit'],
                        'whole_units' => $line['whole_units'],
                        'sort_order' => $lineIndex,
                    ]);
                }
            }
        }
    }

    public function hasSeedFlag(): bool
    {
        if (! Schema::hasTable('room_area_kit_seeds')) {
            return RoomAreaAssembly::query()->exists();
        }

        return DB::table('room_area_kit_seeds')->where('key', self::SEED_KEY)->exists();
    }

    private function claimSeedFlag(): bool
    {
        if (! Schema::hasTable('room_area_kit_seeds')) {
            return ! RoomAreaAssembly::query()->exists();
        }

        return DB::table('room_area_kit_seeds')->insertOrIgnore([
            'key' => self::SEED_KEY,
            'seeded_at' => now(),
        ]) > 0;
    }
}
