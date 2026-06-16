<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\MeasurementProject;
use App\Models\RoomAreaProject;
use App\Models\UtilityCalculation;

/**
 * Aggregates admin utility tools linked to a client project for display
 * on the project Utilities tab.
 */
class ClientProjectLinkedUtilitiesService
{
    /**
     * @return array{
     *     has_any: bool,
     *     total_count: int,
     *     room_area: list<array<string, mixed>>,
     *     measurement: list<array<string, mixed>>,
     *     lumber: list<array<string, mixed>>,
     *     pillar: list<array<string, mixed>>,
     *     tool_links: array<string, string>
     * }
     */
    public function forProject(ClientProject $project): array
    {
        $roomArea = $this->roomAreaItems($project);
        $measurement = $this->measurementItems($project);
        $lumber = $this->calculationItems($project, UtilityCalculation::TOOL_LUMBER_BOARD);
        $pillar = $this->calculationItems($project, UtilityCalculation::TOOL_PILLAR_SPINDLE);

        $total = count($roomArea) + count($measurement) + count($lumber) + count($pillar);

        return [
            'has_any' => $total > 0,
            'total_count' => $total,
            'room_area' => $roomArea,
            'measurement' => $measurement,
            'lumber' => $lumber,
            'pillar' => $pillar,
            'tool_links' => [
                'room_area' => route('admin.utilities.room-area'),
                'measurement' => route('admin.utilities.measurement-conversion'),
                'lumber' => route('admin.utilities.lumber-board', ['project' => $project->id]),
                'pillar' => route('admin.utilities.pillar-spindle'),
                'index' => route('admin.utilities.index'),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roomAreaItems(ClientProject $project): array
    {
        return $project->roomAreaProjects()
            ->with('rooms')
            ->orderBy('name')
            ->get()
            ->map(function (RoomAreaProject $utilityProject) {
                $rooms = $utilityProject->rooms;
                $totalSqft = round((float) $rooms->sum('area_sqft'), 2);

                return [
                    'id' => $utilityProject->id,
                    'name' => $utilityProject->name,
                    'room_count' => $rooms->count(),
                    'total_sqft' => $totalSqft,
                    'total_sqft_label' => $totalSqft > 0
                        ? rtrim(rtrim(number_format($totalSqft, 2, '.', ''), '0'), '.').' sq ft'
                        : null,
                    'rooms' => $rooms->map(fn ($room) => [
                        'name' => $room->name,
                        'area_sqft' => (float) $room->area_sqft,
                        'area_label' => $room->area_sqft
                            ? rtrim(rtrim(number_format((float) $room->area_sqft, 2, '.', ''), '0'), '.').' sq ft'
                            : '—',
                    ])->values()->all(),
                    'open_url' => route('admin.utilities.room-area', ['ra_project' => $utilityProject->id]),
                    'updated_label' => $utilityProject->updated_at?->format('M j, Y'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function measurementItems(ClientProject $project): array
    {
        return $project->measurementProjects()
            ->withCount('rows')
            ->orderBy('name')
            ->get()
            ->map(function (MeasurementProject $utilityProject) {
                return [
                    'id' => $utilityProject->id,
                    'name' => $utilityProject->name,
                    'row_count' => (int) $utilityProject->rows_count,
                    'open_url' => route('admin.utilities.measurement-conversion', ['mc_project' => $utilityProject->id]),
                    'updated_label' => $utilityProject->updated_at?->format('M j, Y'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calculationItems(ClientProject $project, string $tool): array
    {
        return $project->utilityCalculations()
            ->where('tool', $tool)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (UtilityCalculation $calculation) use ($project, $tool) {
                $payload = $calculation->payload ?? [];
                $summary = trim((string) ($payload['summary'] ?? ''));

                $openUrl = $tool === UtilityCalculation::TOOL_LUMBER_BOARD
                    ? route('admin.utilities.lumber-board', [
                        'project' => $project->id,
                        'calculation' => $calculation->id,
                    ])
                    : route('admin.utilities.pillar-spindle', ['calculation' => $calculation->id]);

                return [
                    'id' => $calculation->id,
                    'name' => $calculation->name,
                    'summary' => $summary !== '' ? $summary : null,
                    'open_url' => $openUrl,
                    'updated_label' => $calculation->updated_at?->format('M j, Y g:i A'),
                ];
            })
            ->values()
            ->all();
    }
}
