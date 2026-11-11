<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\StandardQuotation;
use App\Services\RoomAreaLayoutQuoteService;
use App\Services\RoomAreaQuoteLineInserter;
use App\Services\RoomAreaQuoteQuantities;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomAreaQuoteController extends Controller
{
    public function __construct(
        private RoomAreaLayoutQuoteService $layouts,
        private RoomAreaQuoteQuantities $quantities,
        private RoomAreaQuoteLineInserter $inserter,
    ) {}

    public function show(Request $request)
    {
        $resolved = $this->requireLayout($request);
        $option = $resolved['option'];
        $clientProject = $resolved['client_project'];

        $wizardQuery = array_filter([
            'room_area_import_id' => $option['id'],
            'project' => $clientProject?->id,
        ]);

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
                'room_area_room' => $resolved['room']?->id,
            ]))
            : route('admin.utilities.room-area', $resolved['project'] ? ['ra_project' => $resolved['project']->id] : []);

        return view('admin.utilities.room_area_quote', [
            'layoutId' => $option['id'],
            'option' => $option,
            'roomAreaProject' => $resolved['project'],
            'clientProject' => $clientProject,
            'backUrl' => $backUrl,
            'existingQuotes' => $existingQuotes,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
            'trades' => RoomAreaQuoteQuantities::trades(),
            'wizardUrls' => [
                'flooring' => route('admin.quotations.flooring.create', $wizardQuery),
                'trimming' => route('admin.quotations.trimming.create', $wizardQuery),
                'tile' => route('admin.quotations.tile.create', $wizardQuery),
                'millwork' => route('admin.quotations.millwork.create', $wizardQuery),
                'stairs' => route('admin.quotations.stairs.create', $wizardQuery),
            ],
            'defaultClientName' => $clientProject?->client_name
                ?: (string) ($resolved['project']?->name ?? ''),
            'defaultTitle' => $this->defaultDisplayTitle($option),
        ]);
    }

    public function store(Request $request)
    {
        $resolved = $this->requireLayout($request);
        $option = $resolved['option'];
        $clientProject = $resolved['client_project'];

        $data = $request->validate([
            'layout' => ['required', 'string', 'max:40'],
            'trades' => ['required', 'array', 'min:1'],
            'trades.*' => ['required', 'string', Rule::in(RoomAreaQuoteQuantities::tradeKeys())],
            'waste' => ['nullable', 'array'],
            'waste.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit_price' => ['nullable', 'array'],
            'unit_price.*' => ['nullable', 'numeric', 'min:0'],
            'catalog_material_id' => ['nullable', 'array'],
            'catalog_material_id.*' => ['nullable', 'integer', 'exists:materials,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:standard_quotations,id'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'display_title' => ['nullable', 'string', 'max:255'],
        ], [
            'trades.required' => 'Pick at least one trade.',
            'trades.min' => 'Pick at least one trade.',
        ]);

        $trades = array_values(array_unique(array_map('strval', $data['trades'])));
        $wasteByTrade = [];
        foreach ($trades as $trade) {
            $wasteByTrade[$trade] = isset($data['waste'][$trade])
                ? (float) $data['waste'][$trade]
                : (float) (RoomAreaQuoteQuantities::trades()[$trade]['default_waste'] ?? 0);
        }

        $qtyLines = $this->quantities->linesForTrades($option, $trades, $wasteByTrade);
        $built = [];
        foreach ($qtyLines as $qtyLine) {
            if ($qtyLine['qty'] <= 0) {
                continue;
            }
            $trade = $qtyLine['trade'];
            $price = round(max(0.0, (float) ($data['unit_price'][$trade] ?? 0)), 2);
            $catalogId = (int) ($data['catalog_material_id'][$trade] ?? 0) ?: null;
            $material = $catalogId ? Material::query()->find($catalogId) : null;
            $shortName = $material?->displayName() ?: $qtyLine['label'];
            $roomLabel = (string) ($option['short_name'] ?? $option['name'] ?? 'Room Area');
            $description = $qtyLine['label'].' — '.$roomLabel;
            if ($material) {
                $description .= ' · '.$material->displayName();
            }

            $built[] = $this->inserter->enrichLineFromCatalog([
                'trade' => $trade,
                'label' => $qtyLine['label'],
                'short_name' => $shortName,
                'description' => $description,
                'unit' => $qtyLine['unit'],
                'qty' => $qtyLine['qty'],
                'unit_price' => $price,
                'catalog_material_id' => $catalogId,
                'apply_hst' => true,
            ]);
        }

        if ($built === []) {
            throw ValidationException::withMessages([
                'trades' => 'None of the selected trades have a quantity from this layout.',
            ]);
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
            $displayTitle = $existing?->display_title ?: $this->defaultDisplayTitle($option);
        }

        $quote = $this->inserter->insert(
            $built,
            $existing,
            $clientProject,
            $clientName,
            $displayTitle,
            (string) ($option['short_name'] ?? $option['name'] ?? 'Room Area')
        );

        $message = $existing
            ? 'Lines added to '.$quote->quote_number.'.'
            : 'Quotation '.$quote->quote_number.' created from Room Area.';

        return redirect()
            ->route('admin.quotations.standard.edit', $quote)
            ->with('success', $message);
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

    /**
     * @param  array<string, mixed>  $option
     */
    private function defaultDisplayTitle(array $option): string
    {
        $short = trim((string) ($option['short_name'] ?? ''));
        if ($short !== '') {
            return $short.' quote';
        }
        $name = trim((string) ($option['name'] ?? 'Room Area'));

        return $name !== '' ? $name : 'Room Area';
    }
}
