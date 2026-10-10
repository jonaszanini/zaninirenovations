<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Illuminate\Support\Collection;

class RoomAreaPhaseTotals
{
    public const KEYS = [
        'area_sqft',
        'perimeter_lf',
        'net_wall_area_sqft',
        'flooring_with_waste_sqft',
        'casing_lf',
    ];

    /**
     * @return array{
     *   area_sqft: float,
     *   perimeter_lf: float,
     *   net_wall_area_sqft: float,
     *   flooring_with_waste_sqft: float,
     *   casing_lf: float,
     *   room_count: int
     * }
     */
    public function empty(): array
    {
        return [
            'area_sqft' => 0.0,
            'perimeter_lf' => 0.0,
            'net_wall_area_sqft' => 0.0,
            'flooring_with_waste_sqft' => 0.0,
            'casing_lf' => 0.0,
            'room_count' => 0,
        ];
    }

    /**
     * @param  iterable<int, RoomAreaRoom|array<string, mixed>>  $rooms
     * @return array{
     *   area_sqft: float,
     *   perimeter_lf: float,
     *   net_wall_area_sqft: float,
     *   flooring_with_waste_sqft: float,
     *   casing_lf: float,
     *   room_count: int
     * }
     */
    public function sum(iterable $rooms): array
    {
        $totals = $this->empty();
        foreach ($rooms as $room) {
            $metrics = $room instanceof RoomAreaRoom
                ? $room->layoutMetrics()
                : (is_array($room) ? ($room['metrics'] ?? $room) : []);
            if (! is_array($metrics)) {
                continue;
            }
            foreach (self::KEYS as $key) {
                $totals[$key] += (float) ($metrics[$key] ?? 0);
            }
            $totals['room_count']++;
        }
        foreach (self::KEYS as $key) {
            $totals[$key] = round($totals[$key], 2);
        }

        return $totals;
    }

    /**
     * @return array{
     *   project: array<string, float|int>,
     *   phases: list<array{id: int, name: string, sort_order: int, totals: array<string, float|int>, rooms: Collection<int, RoomAreaRoom>}>,
     *   unassigned: array{id: null, name: string, totals: array<string, float|int>, rooms: Collection<int, RoomAreaRoom>}
     * }
     */
    public function grouped(RoomAreaProject $project): array
    {
        $project->loadMissing(['phases', 'rooms']);
        $phases = [];
        foreach ($project->phases as $phase) {
            $rooms = $project->rooms->where('phase_id', $phase->id)->values();
            $phases[] = [
                'id' => (int) $phase->id,
                'name' => (string) $phase->name,
                'sort_order' => (int) $phase->sort_order,
                'rooms' => $rooms,
                'totals' => $this->sum($rooms),
            ];
        }
        $unassigned = $project->rooms->whereNull('phase_id')->values();
        $unassignedTotals = $this->sum($unassigned);
        $projectTotals = $this->empty();
        foreach ($phases as $phase) {
            $projectTotals = $this->addRounded($projectTotals, $phase['totals']);
        }
        $projectTotals = $this->addRounded($projectTotals, $unassignedTotals);

        return [
            'project' => $projectTotals,
            'phases' => $phases,
            'unassigned' => [
                'id' => null,
                'name' => $project->phases->isEmpty() ? 'All rooms' : 'Unassigned',
                'rooms' => $unassigned,
                'totals' => $unassignedTotals,
            ],
        ];
    }

    /**
     * @param  array<string, float|int>  $base
     * @param  array<string, float|int>  $add
     * @return array<string, float|int>
     */
    private function addRounded(array $base, array $add): array
    {
        foreach (self::KEYS as $key) {
            $base[$key] = round(((float) ($base[$key] ?? 0)) + ((float) ($add[$key] ?? 0)), 2);
        }
        $base['room_count'] = (int) ($base['room_count'] ?? 0) + (int) ($add['room_count'] ?? 0);

        return $base;
    }
}
