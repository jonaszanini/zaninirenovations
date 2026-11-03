<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds searchable Room Area layout options for basement (and similar) quotes.
 */
class RoomAreaLayoutQuoteService
{
    public function __construct(
        private RoomAreaCanvasData $canvasData,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $q = '', ?int $clientProjectId = null, ?string $id = null): array
    {
        $id = trim((string) $id);
        if ($id !== '') {
            $option = $this->optionById($id);
            return $option !== null ? [$option] : [];
        }

        $q = trim($q);
        $rooms = $this->roomQuery($q, $clientProjectId)
            ->limit(40)
            ->get();

        $options = [];
        $seenProjects = [];

        foreach ($rooms as $room) {
            $option = $this->optionForRoom($room);
            if ($option === null) {
                continue;
            }
            $options[] = $option;

            $project = $room->project;
            if ($project && (int) ($project->rooms_count ?? $project->rooms()->count()) >= 2 && ! isset($seenProjects[$project->id])) {
                $seenProjects[$project->id] = true;
                $all = $this->optionForProject($project);
                if ($all !== null) {
                    $options[] = $all;
                }
            }
        }

        usort($options, function (array $a, array $b) use ($clientProjectId): int {
            $aLinked = ! empty($a['linked']) ? 0 : 1;
            $bLinked = ! empty($b['linked']) ? 0 : 1;
            if ($aLinked !== $bLinked) {
                return $aLinked <=> $bLinked;
            }
            $aAll = (($a['kind'] ?? '') === 'project') ? 0 : 1;
            $bAll = (($b['kind'] ?? '') === 'project') ? 0 : 1;
            if ($clientProjectId && $aAll !== $bAll) {
                return $aAll <=> $bAll;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return array_values($options);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function optionById(string $id): ?array
    {
        $resolved = $this->resolve($id);

        return $resolved['option'] ?? null;
    }

    /**
     * @return array{option: array<string, mixed>, project: RoomAreaProject|null, room: RoomAreaRoom|null, client_project: \App\Models\ClientProject|null}|null
     */
    public function resolve(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        if (str_starts_with($id, 'project:')) {
            $projectId = (int) substr($id, 8);
            $project = RoomAreaProject::query()->with(['rooms', 'clientProject'])->find($projectId);
            if (! $project) {
                return null;
            }
            $option = $this->optionForProject($project);
            if ($option === null) {
                return null;
            }

            return [
                'option' => $option,
                'project' => $project,
                'room' => null,
                'client_project' => $project->clientProject,
            ];
        }

        $roomId = str_starts_with($id, 'room:') ? (int) substr($id, 5) : (int) $id;
        if ($roomId <= 0) {
            return null;
        }

        $room = RoomAreaRoom::query()->with('project.clientProject')->find($roomId);
        if (! $room) {
            return null;
        }
        $option = $this->optionForRoom($room);
        if ($option === null) {
            return null;
        }

        return [
            'option' => $option,
            'project' => $room->project,
            'room' => $room,
            'client_project' => $room->project?->clientProject,
        ];
    }

    /**
     * @return Builder<RoomAreaRoom>
     */
    private function roomQuery(string $q, ?int $clientProjectId): Builder
    {
        return RoomAreaRoom::query()
            ->with(['project' => fn ($projectQuery) => $projectQuery->withCount('rooms')])
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $sub) use ($q) {
                    $sub->where('name', 'like', '%'.$q.'%')
                        ->orWhereHas('project', function (Builder $projectQuery) use ($q) {
                            $projectQuery->where('name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($clientProjectId, function (Builder $query) use ($clientProjectId) {
                $query->orderByRaw(
                    'CASE WHEN (SELECT client_project_id FROM room_area_projects WHERE room_area_projects.id = room_area_rooms.room_area_project_id) = ? THEN 0 ELSE 1 END',
                    [$clientProjectId]
                );
            })
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionForRoom(RoomAreaRoom $room): ?array
    {
        $metrics = $room->layoutMetrics(tolerant: true);
        $unmeasurable = ! empty($metrics['geometry_unmeasurable']);
        if (! $unmeasurable && $metrics['perimeter_lf'] <= 0 && $metrics['area_sqft'] <= 0) {
            return null;
        }

        $project = $room->project;
        $projectName = $project?->name ?: 'Room Area';
        $name = $projectName.' — '.$room->name;
        $linked = $project && $project->client_project_id;

        return $this->optionPayload(
            'room:'.$room->id,
            $name,
            'room',
            $metrics,
            (bool) $linked,
            $room->name,
            $unmeasurable
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionForProject(RoomAreaProject $project): ?array
    {
        $project->loadMissing('rooms');
        $rooms = $project->rooms;
        if ($rooms->count() < 2) {
            return null;
        }

        $metrics = $this->canvasData->emptyLayoutMetrics();
        foreach ($rooms as $room) {
            $row = $room->layoutMetrics(tolerant: true);
            if (! empty($row['geometry_unmeasurable'])) {
                continue;
            }
            $metrics['area_sqft'] += $row['area_sqft'];
            $metrics['perimeter_lf'] += $row['perimeter_lf'];
            $metrics['baseboard_lf'] += $row['baseboard_lf'];
            $metrics['wall_area_sqft'] += $row['wall_area_sqft'];
            $metrics['net_wall_area_sqft'] += $row['net_wall_area_sqft'] ?? $row['wall_area_sqft'];
            $metrics['ceiling_area_sqft'] += $row['ceiling_area_sqft'] ?? $row['area_sqft'];
            $metrics['ceiling_height_ft'] = max(
                (float) ($metrics['ceiling_height_ft'] ?? 0),
                (float) ($row['ceiling_height_ft'] ?? $row['wall_height_ft'] ?? 0)
            );
            $metrics['casing_lf'] += $row['casing_lf'] ?? 0;
            $metrics['flooring_boxes'] += $row['flooring_boxes'] ?? 0;
            $metrics['flooring_with_waste_sqft'] += $row['flooring_with_waste_sqft'] ?? 0;
            $metrics['flooring_waste_percent'] = max(
                (float) ($metrics['flooring_waste_percent'] ?? 0),
                (float) ($row['flooring_waste_percent'] ?? 0)
            );
            $metrics['flooring_box_sqft'] = max(
                (float) ($metrics['flooring_box_sqft'] ?? 0),
                (float) ($row['flooring_box_sqft'] ?? 0)
            );
            $metrics['door_count'] += $row['door_count'];
            $metrics['window_count'] += $row['window_count'];
            $metrics['opening_count'] += $row['opening_count'];
            $metrics['drywall_openings'] += $row['drywall_openings'];
            $metrics['inside_corners'] += $row['inside_corners'];
            $metrics['outside_corners'] += $row['outside_corners'];
            $metrics['polygon_count'] += $row['polygon_count'];
            $metrics['inside_area_sqft'] += $row['inside_area_sqft'] ?? $row['area_sqft'];
            $metrics['outside_area_sqft'] += $row['outside_area_sqft'] ?? $row['area_sqft'];
            $metrics['shared_wall_lf'] += $row['shared_wall_lf'] ?? 0;
            $metrics['framing_lf'] += $row['framing_lf'] ?? $row['perimeter_lf'];
            $metrics['cabinet_base_lf'] += $row['cabinet_base_lf'] ?? 0;
            $metrics['cabinet_upper_lf'] += $row['cabinet_upper_lf'] ?? 0;
            $metrics['stairs_count'] += $row['stairs_count'] ?? 0;
            $metrics['stairs_treads'] += $row['stairs_treads'] ?? 0;
            $metrics['stairs_run_lf'] += $row['stairs_run_lf'] ?? 0;
            if (($row['stairs_quote_type'] ?? 'standard') === 'double_landing') {
                $metrics['stairs_quote_type'] = 'double_landing';
            } elseif (($row['stairs_quote_type'] ?? 'standard') === 'single_landing'
                && ($metrics['stairs_quote_type'] ?? 'standard') !== 'double_landing') {
                $metrics['stairs_quote_type'] = 'single_landing';
            }
            $thick = (float) ($row['wall_thickness_in'] ?? 0);
            if ($thick > (float) ($metrics['wall_thickness_in'] ?? 0)) {
                $metrics['wall_thickness_in'] = $thick;
            }
        }
        $metrics['area_sqft'] = round($metrics['area_sqft'], 2);
        $metrics['perimeter_lf'] = round($metrics['perimeter_lf'], 2);
        $metrics['baseboard_lf'] = round($metrics['baseboard_lf'], 2);
        $metrics['wall_area_sqft'] = round($metrics['wall_area_sqft'], 2);
        $metrics['net_wall_area_sqft'] = round((float) ($metrics['net_wall_area_sqft'] ?? $metrics['wall_area_sqft']), 2);
        $metrics['ceiling_area_sqft'] = round((float) ($metrics['ceiling_area_sqft'] ?? $metrics['area_sqft']), 2);
        $metrics['casing_lf'] = round((float) ($metrics['casing_lf'] ?? 0), 2);
        $metrics['flooring_with_waste_sqft'] = round((float) ($metrics['flooring_with_waste_sqft'] ?? 0), 2);
        $metrics['inside_area_sqft'] = round((float) ($metrics['inside_area_sqft'] ?? $metrics['area_sqft']), 2);
        $metrics['outside_area_sqft'] = round((float) ($metrics['outside_area_sqft'] ?? $metrics['area_sqft']), 2);
        $metrics['shared_wall_lf'] = round((float) ($metrics['shared_wall_lf'] ?? 0), 2);
        $metrics['framing_lf'] = round((float) ($metrics['framing_lf'] ?? $metrics['perimeter_lf']), 2);
        $metrics['cabinet_base_lf'] = round((float) ($metrics['cabinet_base_lf'] ?? 0), 2);
        $metrics['cabinet_upper_lf'] = round((float) ($metrics['cabinet_upper_lf'] ?? 0), 2);
        $metrics['stairs_run_lf'] = round((float) ($metrics['stairs_run_lf'] ?? 0), 2);

        if ($metrics['perimeter_lf'] <= 0 && $metrics['area_sqft'] <= 0) {
            return null;
        }

        return $this->optionPayload(
            'project:'.$project->id,
            $project->name.' (all rooms)',
            'project',
            $metrics,
            (bool) $project->client_project_id,
            $project->name,
            false
        );
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function optionPayload(
        string $id,
        string $name,
        string $kind,
        array $metrics,
        bool $linked,
        string $shortName,
        bool $geometryUnmeasurable = false,
    ): array {
        $area = $this->formatQty((float) $metrics['area_sqft']);
        $peri = $this->formatQty((float) $metrics['perimeter_lf']);
        $base = $this->formatQty((float) ($metrics['baseboard_lf'] ?? $metrics['perimeter_lf']));
        $openings = (int) ($metrics['drywall_openings'] ?? 0);
        $cabinetBase = (float) ($metrics['cabinet_base_lf'] ?? 0);
        $cabinetUpper = (float) ($metrics['cabinet_upper_lf'] ?? 0);
        $stairsTreads = (int) ($metrics['stairs_treads'] ?? 0);
        $netWall = $this->formatQty((float) ($metrics['net_wall_area_sqft'] ?? $metrics['wall_area_sqft'] ?? 0));
        $ceiling = $this->formatQty((float) ($metrics['ceiling_area_sqft'] ?? $metrics['area_sqft'] ?? 0));
        $casing = $this->formatQty((float) ($metrics['casing_lf'] ?? 0));
        $boxes = (int) ($metrics['flooring_boxes'] ?? 0);
        $meta = $area.' sq ft floor · '.$peri.' lf perimeter · '.$base.' lf baseboard'
            .' · '.$netWall.' sq ft net walls · '.$ceiling.' sq ft ceiling'
            .($boxes > 0 ? ' · '.$boxes.' boxes' : '')
            .((float) ($metrics['casing_lf'] ?? 0) > 0 ? ' · '.$casing.' lf casing' : '')
            .($openings > 0 ? ' · '.$openings.' openings' : '');
        if ($cabinetBase > 0 || $cabinetUpper > 0) {
            $meta .= ' · '.$this->formatQty($cabinetBase + $cabinetUpper).' lf cabinets';
        }
        if ($stairsTreads > 0) {
            $meta .= ' · '.$stairsTreads.' stair treads';
        }
        $geometryMessage = trim((string) ($metrics['geometry_message'] ?? ''));
        if ($geometryUnmeasurable || ! empty($metrics['geometry_unmeasurable'])) {
            if ($geometryMessage === '') {
                $geometryMessage = 'Fix the room outline in Room Area before trusting measurements or quotes.';
            }
            $meta = 'ⓘ '.$geometryMessage.' · '.$meta;
        }

        return [
            'id' => $id,
            'name' => $name,
            'label' => $name,
            'kind' => $kind,
            'linked' => $linked,
            'short_name' => $shortName,
            'geometry_unmeasurable' => $geometryUnmeasurable || ! empty($metrics['geometry_unmeasurable']),
            'geometry_message' => $geometryMessage !== '' ? $geometryMessage : null,
            'area_sqft' => $metrics['area_sqft'],
            'perimeter_lf' => $metrics['perimeter_lf'],
            'baseboard_lf' => $metrics['baseboard_lf'] ?? $metrics['perimeter_lf'],
            'wall_height_ft' => $metrics['wall_height_ft'] ?? RoomAreaCanvasData::DEFAULT_WALL_HEIGHT_FT,
            'ceiling_height_ft' => $metrics['ceiling_height_ft'] ?? ($metrics['wall_height_ft'] ?? RoomAreaCanvasData::DEFAULT_WALL_HEIGHT_FT),
            'ceiling_area_sqft' => $metrics['ceiling_area_sqft'] ?? $metrics['area_sqft'],
            'wall_area_sqft' => $metrics['net_wall_area_sqft'] ?? $metrics['wall_area_sqft'] ?? 0,
            'net_wall_area_sqft' => $metrics['net_wall_area_sqft'] ?? $metrics['wall_area_sqft'] ?? 0,
            'flooring_waste_percent' => $metrics['flooring_waste_percent'] ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT,
            'flooring_box_sqft' => $metrics['flooring_box_sqft'] ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT,
            'flooring_boxes' => (int) ($metrics['flooring_boxes'] ?? 0),
            'flooring_with_waste_sqft' => $metrics['flooring_with_waste_sqft'] ?? 0,
            'casing_lf' => $metrics['casing_lf'] ?? 0,
            'door_count' => (int) ($metrics['door_count'] ?? 0),
            'window_count' => (int) ($metrics['window_count'] ?? 0),
            'opening_count' => (int) ($metrics['opening_count'] ?? 0),
            'drywall_openings' => $openings,
            'inside_corners' => $metrics['inside_corners'],
            'outside_corners' => $metrics['outside_corners'],
            'polygon_count' => $metrics['polygon_count'],
            'wall_thickness_in' => (float) ($metrics['wall_thickness_in'] ?? 0),
            'inside_area_sqft' => $metrics['inside_area_sqft'] ?? $metrics['area_sqft'],
            'outside_area_sqft' => $metrics['outside_area_sqft'] ?? $metrics['area_sqft'],
            'shared_wall_lf' => (float) ($metrics['shared_wall_lf'] ?? 0),
            'framing_lf' => (float) ($metrics['framing_lf'] ?? $metrics['perimeter_lf']),
            'cabinet_base_lf' => $cabinetBase,
            'cabinet_upper_lf' => $cabinetUpper,
            'stairs_count' => (int) ($metrics['stairs_count'] ?? 0),
            'stairs_treads' => $stairsTreads,
            'stairs_run_lf' => (float) ($metrics['stairs_run_lf'] ?? 0),
            'stairs_quote_type' => (string) ($metrics['stairs_quote_type'] ?? 'standard'),
            'meta' => $meta,
        ];
    }

    private function formatQty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
