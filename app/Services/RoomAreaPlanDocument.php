<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;

class RoomAreaPlanDocument
{
    private RoomAreaPhaseTotals $totals;

    private RoomAreaPlanSvg $svg;

    private RoomAreaSignatureStorage $signatures;

    public function __construct(
        ?RoomAreaPhaseTotals $totals = null,
        ?RoomAreaPlanSvg $svg = null,
        ?RoomAreaSignatureStorage $signatures = null
    ) {
        $this->totals = $totals ?? new RoomAreaPhaseTotals;
        $this->svg = $svg ?? new RoomAreaPlanSvg;
        $this->signatures = $signatures ?? new RoomAreaSignatureStorage;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(RoomAreaProject $project): array
    {
        $project->loadMissing(['phases', 'rooms', 'clientProject']);
        $grouped = $this->totals->grouped($project);
        $sections = [];
        foreach ($grouped['phases'] as $phase) {
            $sections[] = $this->section($phase['name'], $phase['totals'], $phase['rooms']);
        }
        if ($grouped['unassigned']['rooms']->isNotEmpty() || $sections === []) {
            $sections[] = $this->section(
                (string) $grouped['unassigned']['name'],
                $grouped['unassigned']['totals'],
                $grouped['unassigned']['rooms']
            );
        }

        return [
            'brand' => 'Zanini Renovations',
            'logo_data_uri' => $this->logoDataUri(),
            'project_name' => (string) $project->name,
            'client_name' => (string) ($project->clientProject?->client_name ?: ''),
            'client_project_name' => (string) ($project->clientProject?->name ?: ''),
            'generated_at' => now()->format('M j, Y g:i A'),
            'totals' => $grouped['project'],
            'sections' => $sections,
            'signature' => [
                'name' => (string) ($project->signer_name ?: ''),
                'date' => $project->signed_at?->format('F j, Y'),
                'image' => $this->signatures->dataUri($project),
                'signed' => filled($project->signer_name) || filled($project->signed_at) || filled($project->signature_path),
            ],
        ];
    }

    /**
     * @param  array<string, float|int>  $totals
     * @param  iterable<int, RoomAreaRoom>  $rooms
     * @return array{name: string, totals: array<string, float|int>, rooms: list<array<string, mixed>>}
     */
    private function section(string $name, array $totals, iterable $rooms): array
    {
        $rows = [];
        foreach ($rooms as $room) {
            $metrics = $room->layoutMetrics();
            $rows[] = [
                'name' => (string) $room->name,
                'svg' => $this->svg->forRoom($room),
                'dimensions' => $this->svg->edgeLabels($room),
                'metrics' => [
                    'area_sqft' => (float) ($metrics['area_sqft'] ?? 0),
                    'perimeter_lf' => (float) ($metrics['perimeter_lf'] ?? 0),
                    'net_wall_area_sqft' => (float) ($metrics['net_wall_area_sqft'] ?? 0),
                    'flooring_with_waste_sqft' => (float) ($metrics['flooring_with_waste_sqft'] ?? 0),
                    'casing_lf' => (float) ($metrics['casing_lf'] ?? 0),
                ],
            ];
        }

        return [
            'name' => $name,
            'totals' => $totals,
            'rooms' => $rows,
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }
        $binary = file_get_contents($path);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
