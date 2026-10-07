<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Prefills existing quotation wizards from a saved Room Area layout.
 *
 * Same source id format as the basement import: room:{id} or project:{id}.
 */
class RoomAreaQuotePrefill
{
    public function __construct(
        private RoomAreaLayoutQuoteService $layouts,
    ) {}

    /**
     * @return array{id: string, label: string, note: string, kind: string, inputs: array<string, mixed>}|null
     */
    public function forRequest(Request $request, string $wizard): ?array
    {
        $id = trim((string) $request->query('room_area_import_id', $request->query('layout', '')));
        if ($id === '') {
            return null;
        }

        return $this->forLayoutId($id, $wizard);
    }

    /**
     * @return array{id: string, label: string, note: string, kind: string, inputs: array<string, mixed>}|null
     */
    public function forLayoutId(string $id, string $wizard): ?array
    {
        $option = $this->layouts->optionById($id);
        if ($option === null) {
            return null;
        }

        return [
            'id' => (string) $option['id'],
            'label' => (string) ($option['name'] ?? $option['label'] ?? 'Room Area'),
            'note' => (string) ($option['meta'] ?? ''),
            'kind' => (string) ($option['kind'] ?? 'room'),
            'inputs' => $this->wizardInputs($wizard, $option),
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    public function wizardInputs(string $wizard, array $option): array
    {
        $area = round(max(0.0, (float) ($option['area_sqft'] ?? 0)), 2);
        $perimeter = round(max(0.0, (float) ($option['perimeter_lf'] ?? 0)), 2);
        $baseboard = round(max(0.0, (float) ($option['baseboard_lf'] ?? $perimeter)), 2);
        $wallArea = round(max(0.0, (float) ($option['wall_area_sqft'] ?? 0)), 2);
        $doors = (int) ($option['door_count'] ?? 0);
        $windows = (int) ($option['window_count'] ?? 0);

        return match ($wizard) {
            'flooring' => [
                'square_footage' => $area,
                'baseboard_linear_ft' => $baseboard,
                'install_baseboard' => $baseboard > 0,
            ],
            'painting' => [
                'total_property_sqf' => round($wallArea + $area, 2),
            ],
            'trimming' => [
                'enabled_families' => ['floor_wall'],
                'include_baseboard' => true,
                'floor_lf' => $baseboard > 0 ? $baseboard : $perimeter,
                'baseboard_lf' => $baseboard > 0 ? $baseboard : $perimeter,
                'door_qty' => $doors,
                'window_qty' => $windows,
            ],
            'tile' => [
                'total_area_sqft' => $area,
                'edge_lf' => $perimeter,
            ],
            default => [],
        };
    }
}
