<?php

namespace App\Services;

use App\Models\RoomAreaAssembly;

class RoomAreaAssemblyDefaults
{
    /**
     * @return list<array{name: string, notes: string, lines: list<array{name: string, formula: string, unit: string, whole_units: bool}>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Drywall wall',
                'notes' => 'Sheets from net wall sf plus 10% waste, then screws, mud, and tape.',
                'lines' => [
                    ['name' => 'Drywall sheets', 'formula' => 'ceil(net_wall_sf * 1.1 / 32)', 'unit' => 'sheet', 'whole_units' => true],
                    ['name' => 'Screws', 'formula' => 'ceil(net_wall_sf * 1.1 / 32) * 32', 'unit' => 'ea', 'whole_units' => true],
                    ['name' => 'Joint compound', 'formula' => 'net_wall_sf / 100', 'unit' => 'pail', 'whole_units' => true],
                    ['name' => 'Tape', 'formula' => 'net_wall_sf / 20', 'unit' => 'roll', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'LVP floor',
                'notes' => 'Boxes from the room’s floor waste math, plus underlay and a transition per doorway.',
                'lines' => [
                    ['name' => 'Floor boxes', 'formula' => 'flooring_boxes', 'unit' => 'box', 'whole_units' => true],
                    ['name' => 'Underlay rolls', 'formula' => 'ceil(floor_sf / 100)', 'unit' => 'roll', 'whole_units' => true],
                    ['name' => 'Transitions', 'formula' => 'door_count', 'unit' => 'ea', 'whole_units' => true],
                ],
            ],
            [
                'name' => 'Baseboard',
                'notes' => 'Length from perimeter minus door widths, plus caulk and paint.',
                'lines' => [
                    ['name' => 'Baseboard', 'formula' => 'max(0, perimeter_lf - door_count * 2.5)', 'unit' => 'lf', 'whole_units' => false],
                    ['name' => 'Caulk', 'formula' => 'ceil(max(0, perimeter_lf - door_count * 2.5) / 30)', 'unit' => 'tube', 'whole_units' => true],
                    ['name' => 'Paint', 'formula' => 'ceil(max(0, perimeter_lf - door_count * 2.5) / 150)', 'unit' => 'qt', 'whole_units' => true],
                ],
            ],
        ];
    }

    public function ensure(): void
    {
        if (RoomAreaAssembly::query()->exists()) {
            return;
        }

        foreach (self::definitions() as $index => $def) {
            $assembly = RoomAreaAssembly::create([
                'name' => $def['name'],
                'notes' => $def['notes'],
                'sort_order' => $index,
            ]);
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
