<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\RoomAreaAssembly;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\RoomAreaAssemblyDefaults;
use App\Services\RoomAreaAssemblyEvaluator;
use App\Services\RoomAreaKitSeedException;
use App\Services\RoomAreaFormulaParser;
use App\Services\RoomAreaLayoutQuoteService;
use App\Services\RoomAreaQuoteLineInserter;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        try {
            $this->defaults->ensure();
        } catch (RoomAreaKitSeedException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'assemblies' => $this->assembliesPayload(false),
            ], 503);
        }

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
        $data = $this->validatedAssembly($request, $room_area_assembly);
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
        try {
            $this->defaults->ensure();
        } catch (RoomAreaKitSeedException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 503);
        }
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
        try {
            $this->defaults->ensure();
        } catch (RoomAreaKitSeedException $e) {
            return redirect()
                ->route('admin.utilities.room-area')
                ->with('error', $e->getMessage());
        }
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

        $this->pruneQuoteTokens();
        $token = (string) Str::uuid();
        DB::table('room_area_quote_tokens')->insert([
            'token' => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        return DB::transaction(function () use ($resolved, $option, $clientProject, $data, $token) {
            $tokenRow = DB::table('room_area_quote_tokens')
                ->where('token', $token)
                ->lockForUpdate()
                ->first();
            if ($token === '' || ! $tokenRow || $this->quoteTokenExpired($tokenRow)) {
                throw ValidationException::withMessages([
                    'idempotency_token' => 'Reload the page and add the kits again.',
                ]);
            }
            if ($tokenRow->consumed_at) {
                return $this->replayQuoteFromId(
                    $tokenRow->quote_id ? (int) $tokenRow->quote_id : null,
                    (string) $data['layout']
                );
            }

            $assemblies = RoomAreaAssembly::query()
                ->with('lines.material')
                ->whereIn('id', array_map('intval', $data['assembly_ids']))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $roomLabel = (string) ($option['short_name'] ?? $option['name'] ?? 'Room Area');
            $layoutId = (string) ($option['id'] ?? $data['layout']);

            $existing = null;
            if (! empty($data['quotation_id'])) {
                $existing = StandardQuotation::query()
                    ->lockForUpdate()
                    ->find((int) $data['quotation_id']);
                if ($existing && $clientProject && (int) $existing->client_project_id !== (int) $clientProject->id) {
                    throw ValidationException::withMessages([
                        'quotation_id' => 'That quotation is not for the linked client project.',
                    ]);
                }
            }
            $alreadyOnQuote = $this->assemblyKeysOnQuote($existing);
            $coveredByLine = $this->coveredRoomLayoutsOnQuote($existing);
            $addedBits = [];
            $skippedBits = [];
            $pendingAddedByLine = [];
            $pendingCoveredByLine = [];
            $isProjectAdd = ($option['kind'] ?? '') === 'project' && $resolved['project'];

            if ($isProjectAdd) {
                $project = $resolved['project'];
                $wanted = array_flip(array_map('intval', $data['assembly_ids']));
                $evaluated = $this->evaluator->evaluateProject($project, function ($room, $line) use ($wanted, $coveredByLine, &$skippedBits, &$pendingAddedByLine, &$pendingCoveredByLine) {
                    $assemblyId = (int) ($line['assembly_id'] ?? 0);
                    if ($assemblyId > 0 && ! isset($wanted[$assemblyId])) {
                        return false;
                    }
                    $lineId = (int) ($line['id'] ?? 0);
                    $kitName = '';
                    foreach ($room->assemblies as $assembly) {
                        if ((int) $assembly->id === $assemblyId) {
                            $kitName = (string) $assembly->name;
                            break;
                        }
                    }
                    $label = trim($kitName.' — '.($room->name ?: 'Room').' '.($line['name'] ?? 'line'));
                    $roomLayout = 'room:'.$room->id;
                    if (isset($coveredByLine[$lineId][$roomLayout])) {
                        $skippedBits[] = $label;

                        return false;
                    }
                    $pendingAddedByLine[$lineId][] = $label;
                    $pendingCoveredByLine[$lineId][$roomLayout] = true;

                    return true;
                });
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
                            'assembly_name' => $kit['name'],
                            'assembly_line_id' => $line['id'],
                            'assembly_layout_id' => $layoutId,
                            'assembly_covered_layouts' => $this->encodeCoveredLayouts($pendingCoveredByLine[(int) $line['id']] ?? []),
                        ];
                    }
                }
            } else {
                $qtyLines = $this->evaluator->quoteLines($assemblies, $option, $roomLabel);
            }

            $built = [];
            $projectForRooms = $resolved['project'] ?? null;
            foreach ($qtyLines as $qtyLine) {
                if (($qtyLine['qty'] ?? 0) <= 0) {
                    continue;
                }
                $assemblyLineId = (int) ($qtyLine['assembly_line_id'] ?? 0);
                $assemblyLayoutId = trim((string) ($qtyLine['assembly_layout_id'] ?? $layoutId));
                $kitName = trim((string) ($qtyLine['assembly_name'] ?? ''));
                $lineName = trim((string) ($qtyLine['label'] ?? 'line'));
                $coveredMap = $this->parseCoveredLayouts([
                    'assembly_covered_layouts' => $qtyLine['assembly_covered_layouts'] ?? '',
                    'assembly_layout_id' => $assemblyLayoutId,
                ]);
                $coveredLayouts = $this->encodeCoveredLayouts($coveredMap);
                $ambiguous = $this->assemblyCoverIsAmbiguous($existing, $assemblyLineId, $projectForRooms);
                $coverLabel = $this->coverLabelForLayouts($coveredMap, $projectForRooms, $roomLabel, $ambiguous);
                $lineLabel = $this->formatAssemblyShortName($lineName, $coverLabel);
                $kitLineLabel = trim($kitName.' — '.$lineLabel);
                if ($this->quoteLineRoomsAlreadyCovered($coveredByLine, $assemblyLineId, $coveredLayouts, $assemblyLayoutId)) {
                    $skippedBits[] = $kitLineLabel !== '' ? $kitLineLabel : $lineLabel;
                    continue;
                }
                $lineId = (string) ($qtyLine['assembly_line_id'] ?? $qtyLine['label']);
                $price = array_key_exists($lineId, $data['unit_price'] ?? [])
                    ? round(max(0.0, (float) $data['unit_price'][$lineId]), 2)
                    : (float) ($qtyLine['unit_price'] ?? 0);
                $catalogId = (int) ($data['catalog_material_id'][$lineId] ?? $qtyLine['catalog_material_id'] ?? 0) ?: null;
                $material = $catalogId ? Material::query()->find($catalogId) : null;
                $shortName = $lineLabel;
                $description = $qtyLine['description'];
                if ($coverLabel !== '' && ! str_contains((string) $description, '('.$coverLabel.')')) {
                    $description = trim((string) ($qtyLine['label'] ?? $description)).' ('.$coverLabel.')';
                    if ($kitName !== '') {
                        $description = $kitName.' — '.$description;
                    }
                }
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
                    'assembly_layout_id' => $assemblyLayoutId !== '' ? $assemblyLayoutId : null,
                    'assembly_covered_layouts' => $coveredLayouts !== '' ? $coveredLayouts : null,
                    'assembly_quoted_at' => now()->toIso8601String(),
                ]);
                $addedBits[] = $kitLineLabel !== '' ? $kitLineLabel : $lineLabel;
            }

            if ($built === []) {
                if ($existing && $alreadyOnQuote !== []) {
                    $this->consumeQuoteToken($token, $existing->id);

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

            $quote = $this->inserter->insert(
                $built,
                $existing,
                $clientProject,
                $clientName,
                $displayTitle,
                $roomLabel
            );
            $this->consumeQuoteToken($token, $quote->id);

            $message = $this->quoteSuccessMessage($quote, (bool) $existing, $addedBits, $skippedBits);

            return redirect()
                ->route('admin.quotations.standard.edit', $quote)
                ->with('success', $message);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAssembly(Request $request, ?RoomAreaAssembly $assembly = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('room_area_assemblies', 'name')->ignore($assembly?->id),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.name' => ['required', 'string', 'max:120'],
            'lines.*.formula' => ['required', 'string', 'max:200'],
            'lines.*.unit' => ['required', 'string', 'max:20'],
            'lines.*.whole_units' => ['nullable', 'boolean'],
            'lines.*.material_id' => ['nullable', 'integer', 'exists:materials,id'],
        ], [
            'name.unique' => 'A kit with that name already exists.',
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
    private function assembliesPayload(bool $ensure = true): array
    {
        if ($ensure) {
            $this->defaults->ensure();
        }

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

    private function pruneQuoteTokens(): void
    {
        DB::table('room_area_quote_tokens')
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('expires_at')->where('expires_at', '<', now());
                })->orWhere(function ($inner) {
                    $inner->whereNull('expires_at')->where('created_at', '<', now()->subDay());
                });
            })
            ->delete();
    }

    private function quoteTokenExpired(object $tokenRow): bool
    {
        if (! empty($tokenRow->expires_at)) {
            return \Carbon\Carbon::parse($tokenRow->expires_at)->lt(now());
        }

        return ! empty($tokenRow->created_at) && \Carbon\Carbon::parse($tokenRow->created_at)->lt(now()->subDay());
    }

    private function consumeQuoteToken(string $token, ?int $quoteId): void
    {
        DB::table('room_area_quote_tokens')->where('token', $token)->update([
            'quote_id' => $quoteId,
            'consumed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function replayQuoteFromId(?int $quoteId, string $layout)
    {
        $replay = $quoteId ? StandardQuotation::query()->find($quoteId) : null;
        if (! $replay) {
            return redirect()
                ->route('admin.utilities.room-area.assemblies.quote', ['layout' => $layout])
                ->with('success', 'That add-to-quote request was already submitted.');
        }

        return redirect()
            ->route('admin.quotations.standard.edit', $replay)
            ->with('success', 'Kit lines were already added to '.$replay->quote_number.'.');
    }

    private function assemblySourceKey(int $lineId, string $layoutId): ?string
    {
        $layoutId = trim($layoutId);
        if ($lineId <= 0 || $layoutId === '') {
            return null;
        }

        return $layoutId.':'.$lineId;
    }

    /**
     * @return array<int, array<string, true>>
     */
    private function coveredRoomLayoutsOnQuote(?StandardQuotation $quote): array
    {
        if (! $quote) {
            return [];
        }
        $byLine = [];
        foreach ($quote->resolvedWorks() as $work) {
            foreach (($work['materials'] ?? []) as $row) {
                $lineId = (int) ($row['assembly_line_id'] ?? 0);
                if ($lineId <= 0) {
                    continue;
                }
                foreach (array_keys($this->parseCoveredLayouts($row, $quote)) as $layout) {
                    $byLine[$lineId][$layout] = true;
                }
            }
        }

        return $byLine;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, true>
     */
    private function parseCoveredLayouts(array $row, ?StandardQuotation $quote = null): array
    {
        $out = [];
        $raw = $row['assembly_covered_layouts'] ?? '';
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $raw = trim((string) $raw);
            $parts = [];
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $parts = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $raw) ?: []);
            }
        }
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if (str_starts_with($part, 'room:')) {
                $out[$part] = true;
            }
        }
        $layout = trim((string) ($row['assembly_layout_id'] ?? ''));
        if (str_starts_with($layout, 'room:')) {
            $out[$layout] = true;
        }
        if ($out === [] && preg_match('/^project:(\d+)$/', $layout, $match)) {
            $asOf = $this->coveredLayoutsAsOf($row, $quote);
            $rooms = RoomAreaRoom::query()->where('room_area_project_id', (int) $match[1]);
            if ($asOf) {
                $rooms->where('created_at', '<=', $asOf);
            }
            foreach ($rooms->pluck('id') as $roomId) {
                $out['room:'.$roomId] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function coveredLayoutsAsOf(array $row, ?StandardQuotation $quote = null): ?\Carbon\Carbon
    {
        $raw = trim((string) ($row['assembly_quoted_at'] ?? ''));
        if ($raw !== '') {
            try {
                return \Carbon\Carbon::parse($raw);
            } catch (\Throwable $e) {
                // Fall through to the quote timestamps used by older lines.
            }
        }

        return $quote?->updated_at ?? $quote?->created_at;
    }

    /**
     * @param  array<string, true>  $layouts
     */
    private function encodeCoveredLayouts(array $layouts): string
    {
        $rooms = [];
        foreach (array_keys($layouts) as $id) {
            $id = trim((string) $id);
            if (str_starts_with($id, 'room:')) {
                $rooms[] = $id;
            }
        }
        $rooms = array_values(array_unique($rooms));
        sort($rooms);
        if (count($rooms) > 2000) {
            throw ValidationException::withMessages([
                'assembly_ids' => 'This quote cannot cover more than 2000 rooms in one line.',
            ]);
        }

        return json_encode($rooms, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /**
     * @param  array<string, true>  $coveredMap
     */
    private function coverLabelForLayouts(array $coveredMap, ?RoomAreaProject $project, string $fallback, bool $ambiguous = false): string
    {
        $ids = [];
        foreach (array_keys($coveredMap) as $layout) {
            if (preg_match('/^room:(\d+)$/', (string) $layout, $match)) {
                $ids[] = (int) $match[1];
            }
        }
        $ids = array_values(array_unique($ids));
        $names = [];
        if ($ids !== []) {
            $names = RoomAreaRoom::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name) ?: 'Room')
                ->values()
                ->all();
        }
        if ($names === []) {
            $fallbackName = trim($fallback);
            $names = [$fallbackName !== '' ? $fallbackName : 'Room'];
        }
        $shown = count($names) <= 2 ? $names : array_slice($names, 0, 2);
        $hidden = count($names) - count($shown);
        $rooms = implode(', ', $shown);
        if ($hidden > 0) {
            $rooms .= ' +'.$hidden;
        }
        $projectName = trim((string) ($project?->name ?? ''));
        if ($ambiguous && $projectName !== '') {
            return $projectName.' · '.$rooms;
        }

        return $rooms;
    }

    private function formatAssemblyShortName(string $lineName, string $coverLabel): string
    {
        $line = trim($lineName) !== '' ? trim($lineName) : 'Line';
        $cover = trim($coverLabel);
        $label = $cover !== '' ? $line.' ('.$cover.')' : $line;
        if (mb_strlen($label) <= 60) {
            return $label;
        }
        $budget = 60 - mb_strlen($line.' ()');
        if ($budget >= 1 && $cover !== '') {
            return $line.' ('.$this->truncateAssemblyLabel($cover, $budget).')';
        }
        $keep = max(1, 60 - ($cover !== '' ? mb_strlen(' (…)') : 0));

        return $cover !== ''
            ? $this->truncateAssemblyLabel($line, $keep).' (…)'
            : $this->truncateAssemblyLabel($line, 60);
    }

    private function truncateAssemblyLabel(string $text, int $max): string
    {
        if ($max < 1) {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        if ($max === 1) {
            return mb_substr($text, 0, 1);
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private function assemblyCoverIsAmbiguous(?StandardQuotation $quote, int $assemblyLineId, ?RoomAreaProject $project): bool
    {
        if (! $quote || ! $project || $assemblyLineId <= 0) {
            return false;
        }
        $projectId = (int) $project->id;
        foreach ($quote->resolvedWorks() as $work) {
            foreach (($work['materials'] ?? []) as $row) {
                if ((int) ($row['assembly_line_id'] ?? 0) !== $assemblyLineId) {
                    continue;
                }
                $ids = [];
                foreach (array_keys($this->parseCoveredLayouts($row, $quote)) as $layout) {
                    if (preg_match('/^room:(\d+)$/', (string) $layout, $match)) {
                        $ids[] = (int) $match[1];
                    }
                }
                if ($ids === []) {
                    continue;
                }
                if (RoomAreaRoom::query()->whereIn('id', $ids)->where('room_area_project_id', '!=', $projectId)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, true>>  $coveredByLine
     */
    private function quoteLineRoomsAlreadyCovered(array $coveredByLine, int $assemblyLineId, string $coveredLayouts, string $layoutId): bool
    {
        if ($assemblyLineId <= 0) {
            return false;
        }
        $wanted = $this->parseCoveredLayouts([
            'assembly_covered_layouts' => $coveredLayouts,
            'assembly_layout_id' => $layoutId,
        ], null);
        if ($wanted === []) {
            return false;
        }
        $already = $coveredByLine[$assemblyLineId] ?? [];
        foreach (array_keys($wanted) as $roomLayout) {
            if (! isset($already[$roomLayout])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    private function assemblyKeysOnQuote(?StandardQuotation $quote): array
    {
        if (! $quote) {
            return [];
        }
        $keys = [];
        foreach ($quote->resolvedWorks() as $work) {
            foreach (($work['materials'] ?? []) as $row) {
                $key = $this->assemblySourceKey(
                    (int) ($row['assembly_line_id'] ?? 0),
                    (string) ($row['assembly_layout_id'] ?? '')
                );
                if ($key !== null) {
                    $keys[$key] = true;
                }
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $addedBits
     * @param  list<string>  $skippedBits
     */
    private function quoteSuccessMessage($quote, bool $existing, array $addedBits, array $skippedBits): string
    {
        $added = $this->joinQuoteParts($addedBits);
        $skipped = $this->formatSkippedParts($skippedBits);
        if ($added !== '' && $skipped !== '') {
            return 'Added '.$added.' to '.$quote->quote_number.'. '.$skipped;
        }
        if ($added !== '' && $existing) {
            return 'Added '.$added.' to '.$quote->quote_number.'.';
        }
        if ($added !== '') {
            return 'Quotation '.$quote->quote_number.' created with '.$added.'.';
        }
        if ($existing) {
            return 'Kit lines added to '.$quote->quote_number.'.';
        }

        return 'Quotation '.$quote->quote_number.' created from Room Area kits.';
    }

    /**
     * @param  list<string>  $parts
     */
    private function formatSkippedParts(array $parts): string
    {
        $parts = array_values(array_unique(array_filter(
            array_map(static fn ($part) => trim((string) $part), $parts),
            static fn ($part) => $part !== ''
        )));
        $count = count($parts);
        if ($count === 0) {
            return '';
        }
        $shown = array_slice($parts, 0, 3);

        return 'Skipped '.$count.': '.implode(', ', $shown).'. Existing lines were left alone.';
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinQuoteParts(array $parts): string
    {
        $parts = array_values(array_unique(array_filter(
            array_map(static fn ($part) => trim((string) $part), $parts),
            static fn ($part) => $part !== ''
        )));
        $count = count($parts);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $parts[0];
        }
        if ($count === 2) {
            return $parts[0].' and '.$parts[1];
        }
        $last = array_pop($parts);

        return implode(', ', $parts).', and '.$last;
    }

    private function lineOverlapsQuote(array $alreadyOnQuote, int $assemblyLineId, string $layoutId, $project): bool
    {
        if ($assemblyLineId <= 0 || $alreadyOnQuote === []) {
            return false;
        }
        if (str_starts_with($layoutId, 'project:') && $project) {
            foreach ($project->rooms as $room) {
                $key = $this->assemblySourceKey($assemblyLineId, 'room:'.$room->id);
                if ($key !== null && isset($alreadyOnQuote[$key])) {
                    return true;
                }
            }
        }
        if (str_starts_with($layoutId, 'room:') && $project) {
            $key = $this->assemblySourceKey($assemblyLineId, 'project:'.$project->id);
            if ($key !== null && isset($alreadyOnQuote[$key])) {
                return true;
            }
        }

        return false;
    }
}
