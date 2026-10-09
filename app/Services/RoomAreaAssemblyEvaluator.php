<?php

namespace App\Services;

use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaAssemblyLine;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Illuminate\Support\Collection;

class RoomAreaAssemblyEvaluator
{
    public function __construct(
        private RoomAreaFormulaParser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{id: int, name: string, notes: ?string, lines: list<array<string, mixed>>}
     */
    public function evaluateAssembly(RoomAreaAssembly $assembly, array $metrics): array
    {
        $vars = RoomAreaFormulaParser::variablesFromMetrics($metrics);
        $lines = [];
        foreach ($assembly->lines as $line) {
            $lines[] = $this->evaluateLine($line, $vars);
        }

        return [
            'id' => $assembly->id,
            'name' => $assembly->name,
            'notes' => $assembly->notes,
            'lines' => $lines,
        ];
    }

    /**
     * @param  iterable<RoomAreaAssembly>  $assemblies
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    public function evaluateMany(iterable $assemblies, array $metrics): array
    {
        $out = [];
        foreach ($assemblies as $assembly) {
            $out[] = $this->evaluateAssembly($assembly, $metrics);
        }

        return $out;
    }

    /**
     * Sum attached-kit quantities across every room in the project.
     * Raw per-room values are added first, then rounding is applied once.
     *
     * @param  (callable(RoomAreaRoom, array<string, mixed>): bool)|null  $includeRoom
     * @return list<array<string, mixed>>
     */
    public function evaluateProject(RoomAreaProject $project, ?callable $includeRoom = null): array
    {
        $project->loadMissing(['rooms.assemblies.lines.material']);
        $byAssembly = [];

        foreach ($project->rooms as $room) {
            $metrics = $room->layoutMetrics();
            foreach ($room->assemblies as $assembly) {
                $evaluated = $this->evaluateAssembly($assembly, $metrics);
                if (! isset($byAssembly[$assembly->id])) {
                    $byAssembly[$assembly->id] = [
                        'id' => $assembly->id,
                        'name' => $assembly->name,
                        'notes' => $assembly->notes,
                        'lines' => [],
                    ];
                }
                foreach ($evaluated['lines'] as $line) {
                    if ($includeRoom && ! $includeRoom($room, $line)) {
                        continue;
                    }
                    $key = (string) ($line['id'] ?? $line['name']);
                    if (! isset($byAssembly[$assembly->id]['lines'][$key])) {
                        $byAssembly[$assembly->id]['lines'][$key] = $line;
                        $byAssembly[$assembly->id]['lines'][$key]['raw'] = $line['raw'];
                        $byAssembly[$assembly->id]['lines'][$key]['qty'] = null;
                        continue;
                    }
                    if ($line['error']) {
                        $byAssembly[$assembly->id]['lines'][$key]['error'] = $line['error'];
                        continue;
                    }
                    if ($byAssembly[$assembly->id]['lines'][$key]['raw'] === null) {
                        $byAssembly[$assembly->id]['lines'][$key]['raw'] = $line['raw'];
                        continue;
                    }
                    if ($line['raw'] !== null) {
                        $byAssembly[$assembly->id]['lines'][$key]['raw'] += $line['raw'];
                    }
                }
            }
        }

        return array_values(array_map(function (array $row) {
            $row['lines'] = array_values(array_map(function (array $line) {
                if ($line['error'] || $line['raw'] === null) {
                    $line['qty'] = null;

                    return $line;
                }
                $line['qty'] = $this->parser->applyRounding((float) $line['raw'], (bool) $line['whole_units']);

                return $line;
            }, $row['lines']));

            return $row;
        }, $byAssembly));
    }

    /**
     * @param  array<string, float>  $variables
     * @return array<string, mixed>
     */
    public function evaluateLine(RoomAreaAssemblyLine $line, array $variables): array
    {
        $result = $this->parser->evaluate((string) $line->formula, $variables);
        $qty = null;
        $error = null;
        if ($result['ok']) {
            $qty = $this->parser->applyRounding((float) $result['value'], (bool) $line->whole_units);
        } else {
            $error = $result['error'];
        }

        $material = $line->relationLoaded('material') ? $line->material : $line->material()->first();

        return [
            'id' => $line->id,
            'assembly_id' => $line->room_area_assembly_id,
            'name' => $line->name,
            'formula' => $line->formula,
            'unit' => $line->unit,
            'whole_units' => (bool) $line->whole_units,
            'qty' => $qty,
            'raw' => $result['ok'] ? (float) $result['value'] : null,
            'error' => $error,
            'material_id' => $line->material_id,
            'material_name' => $material?->displayName(),
            'unit_price' => $material && is_numeric($material->price_cad)
                ? (float) $material->price_cad
                : null,
        ];
    }

    /**
     * @param  Collection<int, RoomAreaAssembly>|iterable<RoomAreaAssembly>  $assemblies
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    public function quoteLines(iterable $assemblies, array $metrics, string $roomLabel): array
    {
        $built = [];
        foreach ($this->evaluateMany($assemblies, $metrics) as $kit) {
            foreach ($kit['lines'] as $line) {
                if ($line['error'] || $line['qty'] === null || $line['qty'] <= 0) {
                    continue;
                }
                $built[] = [
                    'trade' => 'assembly',
                    'label' => $line['name'],
                    'short_name' => $line['material_name'] ?: $line['name'],
                    'description' => $kit['name'].' — '.$line['name'].' — '.$roomLabel,
                    'unit' => $line['unit'],
                    'qty' => $line['qty'],
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'catalog_material_id' => $line['material_id'],
                    'apply_hst' => true,
                    'assembly_id' => $kit['id'],
                    'assembly_line_id' => $line['id'],
                    'assembly_layout_id' => (string) ($metrics['id'] ?? ''),
                ];
            }
        }

        return $built;
    }
}
