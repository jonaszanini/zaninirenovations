<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\RoomAreaAssemblyDefaults;
use App\Services\RoomAreaAssemblyEvaluator;
use App\Services\RoomAreaFormulaParser;
use App\Services\RoomAreaLayoutQuoteService;
use App\Services\RoomAreaQuoteLineInserter;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomAreaAssemblyController extends Controller
{
    public function __construct(
        private RoomAreaAssemblyDefaults $defaults,
        private RoomAreaAssemblyEvaluator $evaluator,
        private RoomAreaFormulaParser $parser,
        private RoomAreaLayoutQuoteService $layouts,
        private RoomAreaQuoteLineInserter $inserter,
    ) {}

    public function index()
    {
        $this->defaults->ensure();

        return response()->json([
            'ok' => true,
            'assemblies' => $this->assembliesPayload(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedAssembly($request);
        $assembly = RoomAreaAssembly::create([
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'sort_order' => (int) (RoomAreaAssembly::query()->max('sort_order') ?? 0) + 1,
        ]);
        $this->syncLines($assembly, $data['lines']);

        return response()->json([
            'ok' => true,
            'assembly' => $assembly->fresh('lines.material')->toEditorArray(),
            'assemblies' => $this->assembliesPayload(),
        ], 201);
    }

    public function update(Request $request, RoomAreaAssembly $room_area_assembly)
    {
        $data = $this->validatedAssembly($request);
        $room_area_assembly->update([
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
        ]);
        $this->syncLines($room_area_assembly, $data['lines']);

        return response()->json([
            'ok' => true,
            'assembly' => $room_area_assembly->fresh('lines.material')->toEditorArray(),
            'assemblies' => $this->assembliesPayload(),
        ]);
    }

    public function destroy(RoomAreaAssembly $room_area_assembly)
    {
        $room_area_assembly->delete();

        return response()->json([
            'ok' => true,
            'assemblies' => $this->assembliesPayload(),
        ]);
    }

    public function syncRoom(Request $request, RoomAreaRoom $room_area_room)
    {
        $data = $request->validate([
            'assembly_ids' => ['present', 'array'],
            'assembly_ids.*' => ['integer', 'exists:room_area_assemblies,id'],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['assembly_ids'])));
        $room_area_room->assemblies()->sync($ids);

        return response()->json([
            'ok' => true,
            'assembly_ids' => $room_area_room->assemblies()->pluck('room_area_assemblies.id')->values()->all(),
        ]);
    }

    public function evaluate(Request $request)
    {
        $resolved = $this->requireLayout($request);
        $this->defaults->ensure();
        $ids = $request->input('assembly_ids');
        $query = RoomAreaAssembly::query()->with('lines.material')->orderBy('sort_order')->orderBy('id');
        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', array_map('intval', $ids));
        }
        $assemblies = $query->get();
        $option = $resolved['option'];
        $evaluated = $this->evaluator->evaluateMany($assemblies, $option);
        $projectTotals = $resolved['project']
            ? $this->evaluator->evaluateProject($resolved['project'])
            : [];

        return response()->json([
            'ok' => true,
            'layout' => $option['id'],
            'assemblies' => $evaluated,
            'project' => $projectTotals,
        ]);
    }

    public function quoteShow(Request $request)
    {
        $resolved = $this->requireLayout($request);
        $this->defaults->ensure();
        $option = $resolved['option'];
        $clientProject = $resolved['client_project'];
        $room = $resolved['room'];

        $attachedIds = $room
            ? $room->assemblies()->pluck('room_area_assemblies.id')->all()
            : [];
        $assemblies = collect();
        if ($room) {
            if ($attachedIds !== []) {
                $assemblies = RoomAreaAssembly::query()
                    ->with('lines.material')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->whereIn('id', $attachedIds)
                    ->get();
            }
        } elseif ($resolved['project']) {
            $assemblies = RoomAreaAssembly::query()
                ->with('lines.material')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->whereIn('id', $resolved['project']->rooms->flatMap->assemblies->pluck('id')->unique()->all() ?: [0])
                ->get();
        }

        $evaluated = $this->evaluator->evaluateMany($assemblies, $option);
        if (($option['kind'] ?? '') === 'project' && $resolved['project']) {
            $evaluated = $this->evaluator->evaluateProject($resolved['project']);
        }

        $existingQuotes = StandardQuotation::query()
            ->when(
                $clientProject,
                fn ($query) => $query->where('client_project_id', $clientProject->id),
                fn ($query) => $query->whereNull('client_project_id')
            )
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'quote_number', 'display_title', 'client_name', 'grand_total', 'client_project_id']);

        $backUrl = $clientProject
            ? route('admin.client-projects.utilities.room-area', array_filter([
                'client_project' => $clientProject,
                'room_area_room' => $room?->id,
            ]))
            : route('admin.utilities.room-area', $resolved['project'] ? ['ra_project' => $resolved['project']->id] : []);

        $token = (string) Str::uuid();
        Cache::put($this->quoteTokenIssuedKey($token), 1, now()->addHours(6));

        return view('admin.utilities.room_area_assembly_quote', [
            'layoutId' => $option['id'],
            'option' => $option,
            'assemblies' => $evaluated,
            'idempotencyToken' => $token,
            'roomAreaProject' => $resolved['project'],
            'clientProject' => $clientProject,
            'backUrl' => $backUrl,
            'existingQuotes' => $existingQuotes,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
            'defaultClientName' => $clientProject?->client_name
                ?: (string) ($resolved['project']?->name ?? ''),
            'defaultTitle' => trim((string) ($option['short_name'] ?? $option['name'] ?? 'Room Area')).' kits',
        ]);
    }

    public function quoteStore(Request $request)
    {
        $resolved = $this->requireLayout($request);
        $option = $resolved['option'];
        $clientProject = $resolved['client_project'];

        $data = $request->validate([
            'layout' => ['required', 'string', 'max:40'],
            'idempotency_token' => ['required', 'string', 'max:80'],
            'assembly_ids' => ['required', 'array', 'min:1'],
            'assembly_ids.*' => ['required', 'integer', 'exists:room_area_assemblies,id'],
            'unit_price' => ['nullable', 'array'],
            'unit_price.*' => ['nullable', 'numeric', 'min:0'],
            'catalog_material_id' => ['nullable', 'array'],
            'catalog_material_id.*' => ['nullable', 'integer', 'exists:materials,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:standard_quotations,id'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'display_title' => ['nullable', 'string', 'max:255'],
        ], [
            'assembly_ids.required' => 'Pick at least one kit.',
            'assembly_ids.min' => 'Pick at least one kit.',
            'idempotency_token.required' => 'Reload the page and add the kits again.',
        ]);

        $token = trim((string) $data['idempotency_token']);
        if ($token === '' || ! Cache::get($this->quoteTokenIssuedKey($token))) {
            throw ValidationException::withMessages([
                'idempotency_token' => 'Reload the page and add the kits again.',
            ]);
        }

        if ($replay = $this->replayQuoteFromToken($token, (string) $data['layout'])) {
            return $replay;
        }

        $assemblies = RoomAreaAssembly::query()
            ->with('lines.material')
            ->whereIn('id', array_map('intval', $data['assembly_ids']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $roomLabel = (string) ($option['short_name'] ?? $option['name'] ?? 'Room Area');
        if (($option['kind'] ?? '') === 'project' && $resolved['project']) {
            $evaluated = $this->evaluator->evaluateProject($resolved['project']);
            $wanted = array_flip(array_map('intval', $data['assembly_ids']));
            $evaluated = array_values(array_filter($evaluated, fn ($row) => isset($wanted[(int) $row['id']])));
            $qtyLines = [];
            foreach ($evaluated as $kit) {
                foreach ($kit['lines'] as $line) {
                    $qtyLines[] = [
                        'trade' => 'assembly',
                        'label' => $line['name'],
                        'short_name' => $line['material_name'] ?: $line['name'],
                        'description' => $kit['name'].' — '.$line['name'].' — '.$roomLabel,
                        'unit' => $line['unit'],
                        'qty' => $line['qty'],
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'catalog_material_id' => $line['material_id'],
                        'apply_hst' => true,
                        'assembly_line_id' => $line['id'],
                    ];
                }
            }
        } else {
            $qtyLines = $this->evaluator->quoteLines($assemblies, $option, $roomLabel);
        }

        $existing = null;
        if (! empty($data['quotation_id'])) {
            $existing = StandardQuotation::query()->find((int) $data['quotation_id']);
            if ($existing && $clientProject && (int) $existing->client_project_id !== (int) $clientProject->id) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'That quotation is not for the linked client project.',
                ]);
            }
        }
        $alreadyOnQuote = $this->assemblyLineIdsOnQuote($existing);

        $built = [];
        foreach ($qtyLines as $qtyLine) {
            if (($qtyLine['qty'] ?? 0) <= 0) {
                continue;
            }
            $assemblyLineId = (int) ($qtyLine['assembly_line_id'] ?? 0);
            if ($assemblyLineId > 0 && isset($alreadyOnQuote[$assemblyLineId])) {
                continue;
            }
            $lineId = (string) ($qtyLine['assembly_line_id'] ?? $qtyLine['label']);
            $price = array_key_exists($lineId, $data['unit_price'] ?? [])
                ? round(max(0.0, (float) $data['unit_price'][$lineId]), 2)
                : (float) ($qtyLine['unit_price'] ?? 0);
            $catalogId = (int) ($data['catalog_material_id'][$lineId] ?? $qtyLine['catalog_material_id'] ?? 0) ?: null;
            $material = $catalogId ? Material::query()->find($catalogId) : null;
            $shortName = $material?->displayName() ?: $qtyLine['short_name'];
            $description = $qtyLine['description'];
            if ($material && ! str_contains($description, $material->displayName())) {
                $description .= ' · '.$material->displayName();
            }

            $built[] = $this->inserter->enrichLineFromCatalog([
                'trade' => 'assembly',
                'label' => $qtyLine['label'],
                'short_name' => $shortName,
                'description' => $description,
                'unit' => $qtyLine['unit'],
                'qty' => $qtyLine['qty'],
                'unit_price' => $price,
                'catalog_material_id' => $catalogId,
                'apply_hst' => true,
                'assembly_line_id' => $assemblyLineId > 0 ? $assemblyLineId : null,
            ]);
        }

        if ($built === []) {
            if ($existing && $alreadyOnQuote !== []) {
                return redirect()
                    ->route('admin.quotations.standard.edit', $existing)
                    ->with('success', 'Those kit lines are already on '.$existing->quote_number.'.');
            }
            throw ValidationException::withMessages([
                'assembly_ids' => 'None of the selected kits have a quantity from this layout.',
            ]);
        }

        $clientName = trim((string) ($data['client_name'] ?? ''));
        if ($clientName === '') {
            $clientName = trim((string) ($clientProject?->client_name ?? $existing?->client_name ?? $resolved['project']?->name ?? ''));
        }
        if ($clientName === '') {
            throw ValidationException::withMessages([
                'client_name' => 'Enter a client name for the new quotation.',
            ]);
        }

        $displayTitle = trim((string) ($data['display_title'] ?? ''));
        if ($displayTitle === '') {
            $displayTitle = $existing?->display_title ?: $roomLabel.' kits';
        }

        if (! Cache::add($this->quoteTokenOnceKey($token), 1, now()->addHours(6))) {
            if ($replay = $this->replayQuoteFromToken($token, (string) $data['layout'])) {
                return $replay;
            }

            return redirect()
                ->route('admin.utilities.room-area.assemblies.quote', ['layout' => $data['layout']])
                ->with('success', 'That add-to-quote request was already submitted.');
        }

        $quote = $this->inserter->insert(
            $built,
            $existing,
            $clientProject,
            $clientName,
            $displayTitle,
            $roomLabel
        );
        Cache::put($this->quoteTokenResultKey($token), $quote->id, now()->addHours(6));

        $message = $existing
            ? 'Kit lines added to '.$quote->quote_number.'.'
            : 'Quotation '.$quote->quote_number.' created from Room Area kits.';

        return redirect()
            ->route('admin.quotations.standard.edit', $quote)
            ->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAssembly(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.name' => ['required', 'string', 'max:120'],
            'lines.*.formula' => ['required', 'string', 'max:200'],
            'lines.*.unit' => ['required', 'string', 'max:20'],
            'lines.*.whole_units' => ['nullable', 'boolean'],
            'lines.*.material_id' => ['nullable', 'integer', 'exists:materials,id'],
        ], [
            'lines.required' => 'Add at least one material line.',
            'lines.min' => 'Add at least one material line.',
        ]);

        $dummy = RoomAreaFormulaParser::variablesFromMetrics([
            'area_sqft' => 100,
            'net_wall_area_sqft' => 320,
            'ceiling_area_sqft' => 100,
            'perimeter_lf' => 40,
            'baseboard_lf' => 37,
            'casing_lf' => 20,
            'door_count' => 1,
            'window_count' => 1,
            'opening_count' => 0,
            'wall_height_ft' => 8,
            'flooring_boxes' => 6,
            'flooring_waste_percent' => 10,
            'outside_corners' => 0,
            'inside_corners' => 4,
        ]);
        foreach ($data['lines'] as $i => $line) {
            $result = $this->parser->evaluate((string) $line['formula'], $dummy);
            if (! $result['ok']) {
                throw ValidationException::withMessages([
                    'lines.'.$i.'.formula' => $result['error'],
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(RoomAreaAssembly $assembly, array $lines): void
    {
        $keep = [];
        foreach (array_values($lines) as $index => $line) {
            $attrs = [
                'name' => $line['name'],
                'formula' => $line['formula'],
                'unit' => $line['unit'],
                'whole_units' => array_key_exists('whole_units', $line)
                    ? filter_var($line['whole_units'], FILTER_VALIDATE_BOOLEAN)
                    : true,
                'material_id' => ! empty($line['material_id']) ? (int) $line['material_id'] : null,
                'sort_order' => $index,
            ];
            $id = (int) ($line['id'] ?? 0);
            $existing = $id > 0 ? $assembly->lines()->whereKey($id)->first() : null;
            if ($existing) {
                $existing->update($attrs);
                $keep[] = $existing->id;
            } else {
                $keep[] = $assembly->lines()->create($attrs)->id;
            }
        }
        $assembly->lines()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assembliesPayload(): array
    {
        $this->defaults->ensure();

        return RoomAreaAssembly::query()
            ->with('lines.material')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (RoomAreaAssembly $assembly) => $assembly->toEditorArray())
            ->values()
            ->all();
    }

    /**
     * @return array{option: array<string, mixed>, project: \App\Models\RoomAreaProject|null, room: \App\Models\RoomAreaRoom|null, client_project: \App\Models\ClientProject|null}
     */
    private function requireLayout(Request $request): array
    {
        $id = trim((string) $request->input('layout', $request->query('layout', '')));
        $resolved = $this->layouts->resolve($id);
        abort_if($resolved === null, 404, 'Saved room or layout not found.');

        return $resolved;
    }

    private function quoteTokenIssuedKey(string $token): string
    {
        return 'ra_kit_quote_issued_'.$token;
    }

    private function quoteTokenOnceKey(string $token): string
    {
        return 'ra_kit_quote_once_'.$token;
    }

    private function quoteTokenResultKey(string $token): string
    {
        return 'ra_kit_quote_result_'.$token;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|null
     */
    private function replayQuoteFromToken(string $token, string $layout)
    {
        $quoteId = Cache::get($this->quoteTokenResultKey($token));
        if (! $quoteId) {
            return null;
        }
        $replay = StandardQuotation::query()->find((int) $quoteId);
        if (! $replay) {
            return redirect()
                ->route('admin.utilities.room-area.assemblies.quote', ['layout' => $layout])
                ->with('success', 'That add-to-quote request was already submitted.');
        }

        return redirect()
            ->route('admin.quotations.standard.edit', $replay)
            ->with('success', 'Kit lines were already added to '.$replay->quote_number.'.');
    }

    /**
     * @return array<int, true>
     */
    private function assemblyLineIdsOnQuote(?StandardQuotation $quote): array
    {
        if (! $quote) {
            return [];
        }
        $ids = [];
        $works = $quote->resolvedWorks();
        foreach ($works as $work) {
            foreach (($work['materials'] ?? []) as $row) {
                $id = (int) ($row['assembly_line_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }
}
