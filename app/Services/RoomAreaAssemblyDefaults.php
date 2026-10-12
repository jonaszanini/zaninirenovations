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
                    ['name' => 'Tape (500 sf)', 'formula' => 'net_wall_sf * 1.12 / 500', 'unit' => 'roll', 'whole_units' => true],
                    ['name' => 'Joint compound', 'formula' => 'net_wall_sf * 1.12 / 450', 'unit' => 'pail (4.5 gal)', 'whole_units' => true],
                    ['name' => 'Corner bead', 'formula' => 'outside_corners * ceil(wall_height / 8)', 'unit' => 'stick', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'LVP floor',
                'notes' => 'Boxes from the room’s floor waste math, plus underlay and a transition per door or opening.',
                'lines' => [
                    ['name' => 'Floor boxes', 'formula' => 'flooring_boxes', 'unit' => 'box', 'whole_units' => true],
                    ['name' => 'Underlay rolls', 'formula' => 'floor_sf * 1.05 / 100', 'unit' => 'roll', 'whole_units' => true],
                    ['name' => 'Transitions', 'formula' => 'door_count + opening_count', 'unit' => 'ea', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'Baseboard',
                'notes' => 'Length from the room’s baseboard lf plus 10%, then caulk and nails.',
                'lines' => [
                    ['name' => 'Baseboard', 'formula' => 'baseboard_lf * 1.10', 'unit' => 'lf', 'whole_units' => false],
                    ['name' => 'Caulk', 'formula' => 'baseboard_lf / 40', 'unit' => 'tube', 'whole_units' => true],
                    ['name' => 'Nails (1000)', 'formula' => 'baseboard_lf * 1.5 / 1000', 'unit' => 'box', 'whole_units' => true],
                ],
            ],
        ];
    }

    public function ensure(int $lockWaitSeconds = 5, int $attempts = 3): void
    {
        if ($this->hasSeedFlag()) {
            return;
        }

        $lockName = 'room_area_kit_seed_'.self::SEED_KEY;
        $got = false;
        $tries = max(1, $attempts);
        $wait = max(0, $lockWaitSeconds);
        for ($i = 0; $i < $tries; $i++) {
            $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS got', [$lockName, $wait]);
            if ((int) ($row->got ?? 0) === 1) {
                $got = true;
                break;
            }
        }
        if (! $got) {
            if ($this->hasSeedFlag() || RoomAreaAssembly::query()->exists()) {
                return;
            }
            throw new RoomAreaKitSeedException('Could not load the default kits. Refresh the page and try again.');
        }

        try {
            if ($this->hasSeedFlag()) {
                return;
            }

            DB::transaction(function () {
                if ($this->hasSeedFlag()) {
                    return;
                }

                foreach (self::definitions() as $index => $def) {
                    DB::table('room_area_assemblies')->insertOrIgnore([
                        'name' => $def['name'],
                        'notes' => $def['notes'],
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $assembly = RoomAreaAssembly::query()->where('name', $def['name'])->first();
                    if ($assembly && $assembly->lines()->doesntExist()) {
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

                $this->writeSeedFlag();
            });
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function hasSeedFlag(): bool
    {
        if (! Schema::hasTable('room_area_kit_seeds')) {
            return RoomAreaAssembly::query()->exists();
        }

        return DB::table('room_area_kit_seeds')->where('key', self::SEED_KEY)->exists();
    }

    private function writeSeedFlag(): void
    {
        if (! Schema::hasTable('room_area_kit_seeds')) {
            return;
        }

        DB::table('room_area_kit_seeds')->insertOrIgnore([
            'owner' => 'app',
            'key' => self::SEED_KEY,
            'seeded_at' => now(),
        ]);
    }
}
