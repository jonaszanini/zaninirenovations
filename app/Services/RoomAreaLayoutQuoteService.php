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
        if (str_starts_with($id, 'project:')) {
            $projectId = (int) substr($id, 8);
            $project = RoomAreaProject::query()->with('rooms')->find($projectId);

            return $project ? $this->optionForProject($project) : null;
        }

        $roomId = str_starts_with($id, 'room:') ? (int) substr($id, 5) : (int) $id;
        if ($roomId <= 0) {
            return null;
        }

        $room = RoomAreaRoom::query()->with('project')->find($roomId);

        return $room ? $this->optionForRoom($room) : null;
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
        $metrics = $room->layoutMetrics();
        if ($metrics['perimeter_lf'] <= 0 && $metrics['area_sqft'] <= 0) {
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
            $room->name
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
            $row = $room->layoutMetrics();
            $metrics['area_sqft'] += $row['area_sqft'];
            $metrics['perimeter_lf'] += $row['perimeter_lf'];
            $metrics['inside_corners'] += $row['inside_corners'];
            $metrics['outside_corners'] += $row['outside_corners'];
            $metrics['polygon_count'] += $row['polygon_count'];
        }
        $metrics['area_sqft'] = round($metrics['area_sqft'], 2);
        $metrics['perimeter_lf'] = round($metrics['perimeter_lf'], 2);

        if ($metrics['perimeter_lf'] <= 0 && $metrics['area_sqft'] <= 0) {
            return null;
        }

        return $this->optionPayload(
            'project:'.$project->id,
            $project->name.' (all rooms)',
            'project',
            $metrics,
            (bool) $project->client_project_id,
            $project->name
        );
    }

    /**
     * @param  array{area_sqft: float, perimeter_lf: float, inside_corners: int, outside_corners: int, polygon_count: int}  $metrics
     * @return array<string, mixed>
     */
    private function optionPayload(
        string $id,
        string $name,
        string $kind,
        array $metrics,
        bool $linked,
        string $shortName,
    ): array {
        $area = $this->formatQty($metrics['area_sqft']);
        $peri = $this->formatQty($metrics['perimeter_lf']);

        return [
            'id' => $id,
            'name' => $name,
            'label' => $name,
            'kind' => $kind,
            'linked' => $linked,
            'short_name' => $shortName,
            'area_sqft' => $metrics['area_sqft'],
            'perimeter_lf' => $metrics['perimeter_lf'],
            'inside_corners' => $metrics['inside_corners'],
            'outside_corners' => $metrics['outside_corners'],
            'polygon_count' => $metrics['polygon_count'],
            'meta' => $area.' sq ft floor · '.$peri.' lf walls',
        ];
    }

    private function formatQty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
