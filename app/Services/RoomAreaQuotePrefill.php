<?php

namespace App\Services;

use App\Models\ClientProject;
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
     * @return array{id: string, label: string, note: string, kind: string, inputs: array<string, mixed>, client_project: ClientProject|null, client_name: string}|null
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
     * @return array{id: string, label: string, note: string, kind: string, inputs: array<string, mixed>, client_project: ClientProject|null, client_name: string}|null
     */
    public function forLayoutId(string $id, string $wizard): ?array
    {
        $resolved = $this->layouts->resolve($id);
        if ($resolved === null) {
            return null;
        }

        $option = $resolved['option'];
        $clientProject = $resolved['client_project'];

        return [
            'id' => (string) $option['id'],
            'label' => (string) ($option['name'] ?? $option['label'] ?? 'Room Area'),
            'note' => (string) ($option['meta'] ?? ''),
            'kind' => (string) ($option['kind'] ?? 'room'),
            'inputs' => $this->wizardInputs($wizard, $option),
            'client_project' => $clientProject,
            'client_name' => trim((string) ($clientProject?->client_name ?? '')),
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
        $baseboard = round(max(0.0, (float) (
            ($option['finish_baseboard_lf'] ?? 0) > 0
                ? $option['finish_baseboard_lf']
                : ($option['baseboard_lf'] ?? $perimeter)
        )), 2);
        $netWall = round(max(0.0, (float) ($option['net_wall_area_sqft'] ?? $option['wall_area_sqft'] ?? 0)), 2);
        $ceilingArea = round(max(0.0, (float) ($option['ceiling_area_sqft'] ?? $area)), 2);
        $waste = round(max(0.0, (float) ($option['flooring_waste_percent'] ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT)), 2);
        $boxes = (int) ($option['flooring_boxes'] ?? 0);
        $boxSqFt = round(max(0.0, (float) ($option['flooring_box_sqft'] ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT)), 2);
        $flooringWithWaste = round(max(0.0, (float) ($option['flooring_with_waste_sqft'] ?? 0)), 2);
        if ($flooringWithWaste <= 0 && $area > 0) {
            $flooringWithWaste = round($area * (1.0 + ($waste / 100.0)), 2);
        }
        $casing = round(max(0.0, (float) ($option['casing_lf'] ?? 0)), 2);
        $doors = (int) ($option['door_count'] ?? 0);
        $windows = (int) ($option['window_count'] ?? 0);

        return match ($wizard) {
            'flooring' => [
                'square_footage' => $area,
                'square_footage_with_waste' => $flooringWithWaste,
                'waste_percent' => $waste,
                'flooring_boxes' => $boxes,
                'box_sqft' => $boxSqFt,
                'baseboard_linear_ft' => $baseboard,
                'install_baseboard' => $baseboard > 0,
            ],
            'painting' => [
                'total_property_sqf' => round($netWall + $ceilingArea, 2),
            ],
            'trimming' => [
                'enabled_families' => ['floor_wall'],
                'include_baseboard' => true,
                'floor_lf' => $baseboard > 0 ? $baseboard : $perimeter,
                'baseboard_lf' => $baseboard > 0 ? $baseboard : $perimeter,
                'door_qty' => $doors,
                'window_qty' => $windows,
                'casing_lf' => $casing,
            ],
            'tile' => [
                'total_area_sqft' => $area,
                'edge_lf' => $perimeter,
            ],
            'millwork' => $this->millworkInputs($option),
            'stairs' => $this->stairsInputs($option),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function millworkInputs(array $option): array
    {
        $pieces = [];
        $baseLf = (float) ($option['cabinet_base_lf'] ?? 0);
        $upperLf = (float) ($option['cabinet_upper_lf'] ?? 0);
        if ($baseLf > 0) {
            $piece = MillworkQuotationCalculator::defaultPiece('cabinet');
            $piece['width'] = round($baseLf * 12.0, 2);
            $piece['height'] = 34.5;
            $piece['depth'] = 24.0;
            $piece['qty'] = 1;
            $piece['unit'] = 'in';
            $pieces[] = $piece;
        }
        if ($upperLf > 0) {
            $piece = MillworkQuotationCalculator::defaultPiece('cabinet');
            $piece['width'] = round($upperLf * 12.0, 2);
            $piece['height'] = 30.0;
            $piece['depth'] = 12.0;
            $piece['qty'] = 1;
            $piece['unit'] = 'in';
            $pieces[] = $piece;
        }

        return $pieces === [] ? [] : ['pieces' => $pieces];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function stairsInputs(array $option): array
    {
        $treads = (int) ($option['stairs_treads'] ?? 0);
        if ($treads <= 0) {
            return [];
        }
        $type = (string) ($option['stairs_quote_type'] ?? 'standard');
        if (! in_array($type, ['standard', 'winder', 'single_landing', 'double_landing'], true)) {
            $type = 'standard';
        }

        return [
            'total_steps' => $treads,
            'segments' => [[
                'stair_type' => $type,
                'qty' => $treads,
                'open_sides' => StairsQuotationCalculator::OPEN_NONE,
            ]],
        ];
    }
}
