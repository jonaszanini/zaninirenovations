<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\TileGroutType;
use App\Models\TileMortarType;
use App\Models\TileProduct;
use App\Models\TileQuotation;
use App\Services\TileQuotationCalculator;
use Illuminate\Http\Request;

class TileQuotationController extends Controller
{
    use \App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            TileQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.tile.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    public function create(Request $request)
    {
        $linkProject = null;
        if ($request->filled('project')) {
            $linkProject = ClientProject::query()->with('photos')->find($request->query('project'));
        }

        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.quotations.tile.create', compact('linkProject', 'mortarTypes', 'groutTypes', 'products'));
    }

    public function store(Request $request, TileQuotationCalculator $calculator)
    {
        $data = $request->validate([
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string'],
            'total_area_sqft' => ['nullable', 'numeric', 'min:0'],
            'length_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'width_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'waste_margin_percent' => ['required', 'numeric', 'min:0'],
            'tile_product_id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'include_tile_material' => ['nullable', 'boolean'],
            'trowel_size' => ['required', 'string', 'in:1/4x1/4,1/4x3/8,1/2x1/2'],
            'mortar_type_id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'joint_width_decimal' => ['required', 'numeric', 'min:0'],
            'grout_type_id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'labor_rate' => ['required', 'numeric', 'min:0'],
            'include_demolition' => ['nullable', 'boolean'],
            'demolition_complexity' => ['nullable', 'string', 'in:standard,heavy,custom'],
            'custom_demolition_rate' => ['nullable', 'numeric', 'min:0'],
            'include_disposal_fee' => ['nullable', 'boolean'],

            'is_draft' => ['nullable', 'string'],
        ]);

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $calculator->compute(
            (float) ($data['length_ft'] ?? 0),
            (float) ($data['width_ft'] ?? 0),
            (float) $data['waste_margin_percent'],
            empty($data['tile_product_id']) ? null : (int) $data['tile_product_id'],
            $includeTile,
            $data['trowel_size'],
            empty($data['mortar_type_id']) ? null : (int) $data['mortar_type_id'],
            (float) $data['joint_width_decimal'],
            empty($data['grout_type_id']) ? null : (int) $data['grout_type_id'],
            (float) $data['labor_rate'],
            (float) ($data['total_area_sqft'] ?? 0),
            filter_var($data['include_demolition'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $data['demolition_complexity'] ?? 'standard',
            (float) ($data['custom_demolition_rate'] ?? 0),
            filter_var($data['include_disposal_fee'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        $quotation = new TileQuotation();
        $quotation->client_project_id = empty($data['client_project_id']) ? null : (int) $data['client_project_id'];
        $quotation->quote_number = TileQuotation::generateNumber();
        $quotation->client_name = $data['client_name'] ?? null;
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = $data['work_description'] ?? null;
        
        $quotation->inputs = [
            'total_area_sqft' => (float) ($data['total_area_sqft'] ?? 0),
            'length_ft' => (float) ($data['length_ft'] ?? 0),
            'width_ft' => (float) ($data['width_ft'] ?? 0),
            'waste_margin_percent' => (float) $data['waste_margin_percent'],
            'tile_product_id' => $data['tile_product_id'] ?? null,
            'include_tile_material' => $includeTile,
            'trowel_size' => $data['trowel_size'],
            'mortar_type_id' => $data['mortar_type_id'] ?? null,
            'joint_width_decimal' => (float) $data['joint_width_decimal'],
            'grout_type_id' => $data['grout_type_id'] ?? null,
            'labor_rate' => (float) $data['labor_rate'],
            'include_demolition' => filter_var($data['include_demolition'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'demolition_complexity' => $data['demolition_complexity'] ?? 'standard',
            'custom_demolition_rate' => (float) ($data['custom_demolition_rate'] ?? 0),
            'include_disposal_fee' => filter_var($data['include_disposal_fee'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        $quotation->calculation = $calculation;
        $quotation->final_quote_total = $calculation['grand_total'];
        
        if ($quotation->client_project_id) {
            $quotation->linked_at = now();
        }
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
            return redirect()
                ->route('admin.client-projects.show', ['client_project' => $quotation->client_project_id, 'tab' => 'quotation'])
                ->with('success', 'Tile quotation created and linked.');
        }

        return redirect()->route('admin.quotations.index')->with('success', 'Tile quotation created.');
    }

    public function edit(Request $request, TileQuotation $quotation)
    {
        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $quotation->load('project.photos');
        $returnToProject = $request->query('return') === 'project';
        $linkProject = null;

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('created_at')
            ->get();

        return view('admin.quotations.tile.create', compact('quotation', 'mortarTypes', 'groutTypes', 'products', 'returnToProject', 'linkProject', 'linkableProjects'));
    }

    public function update(Request $request, TileQuotation $quotation, TileQuotationCalculator $calculator)
    {
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string'],
            'total_area_sqft' => ['nullable', 'numeric', 'min:0'],
            'length_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'width_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'waste_margin_percent' => ['required', 'numeric', 'min:0'],
            'tile_product_id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'include_tile_material' => ['nullable', 'boolean'],
            'trowel_size' => ['required', 'string', 'in:1/4x1/4,1/4x3/8,1/2x1/2'],
            'mortar_type_id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'joint_width_decimal' => ['required', 'numeric', 'min:0'],
            'grout_type_id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'labor_rate' => ['required', 'numeric', 'min:0'],
            'include_demolition' => ['nullable', 'boolean'],
            'demolition_complexity' => ['nullable', 'string', 'in:standard,heavy,custom'],
            'custom_demolition_rate' => ['nullable', 'numeric', 'min:0'],
            'include_disposal_fee' => ['nullable', 'boolean'],
            'is_draft' => ['nullable', 'string'],
        ]);

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $calculator->compute(
            (float) ($data['length_ft'] ?? 0),
            (float) ($data['width_ft'] ?? 0),
            (float) $data['waste_margin_percent'],
            empty($data['tile_product_id']) ? null : (int) $data['tile_product_id'],
            $includeTile,
            $data['trowel_size'],
            empty($data['mortar_type_id']) ? null : (int) $data['mortar_type_id'],
            (float) $data['joint_width_decimal'],
            empty($data['grout_type_id']) ? null : (int) $data['grout_type_id'],
            (float) $data['labor_rate'],
            (float) ($data['total_area_sqft'] ?? 0),
            filter_var($data['include_demolition'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $data['demolition_complexity'] ?? 'standard',
            (float) ($data['custom_demolition_rate'] ?? 0),
            filter_var($data['include_disposal_fee'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        if (! $quotation->isLinked()) {
            $quotation->client_name = $data['client_name'] ?? null;
        }
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = $data['work_description'] ?? null;
        
        $quotation->inputs = [
            'total_area_sqft' => (float) ($data['total_area_sqft'] ?? 0),
            'length_ft' => (float) ($data['length_ft'] ?? 0),
            'width_ft' => (float) ($data['width_ft'] ?? 0),
            'waste_margin_percent' => (float) $data['waste_margin_percent'],
            'tile_product_id' => $data['tile_product_id'] ?? null,
            'include_tile_material' => $includeTile,
            'trowel_size' => $data['trowel_size'],
            'mortar_type_id' => $data['mortar_type_id'] ?? null,
            'joint_width_decimal' => (float) $data['joint_width_decimal'],
            'grout_type_id' => $data['grout_type_id'] ?? null,
            'labor_rate' => (float) $data['labor_rate'],
            'include_demolition' => filter_var($data['include_demolition'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'demolition_complexity' => $data['demolition_complexity'] ?? 'standard',
            'custom_demolition_rate' => (float) ($data['custom_demolition_rate'] ?? 0),
            'include_disposal_fee' => filter_var($data['include_disposal_fee'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        $quotation->calculation = $calculation;
        $quotation->final_quote_total = $calculation['grand_total'];
        $quotation->version += 1;
        
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
        }

        if ($request->query('return') === 'project' && $quotation->client_project_id) {
            return redirect()
                ->route('admin.client-projects.show', ['client_project' => $quotation->client_project_id, 'tab' => 'quotation'])
                ->with('success', 'Tile quotation updated.');
        }

        return redirect()->route('admin.quotations.index')->with('success', 'Tile quotation updated.');
    }

    public function destroy(TileQuotation $quotation)
    {
        $projectId = $quotation->client_project_id;
        
        if ($projectId) {
            ClientProjectQuotation::query()
                ->where('client_project_id', $projectId)
                ->where('source_type', ClientProjectQuotation::SOURCE_TILE)
                ->where('source_id', $quotation->id)
                ->delete();
        }

        $quotation->delete();

        return back()->with('success', 'Tile quotation deleted.');
    }

    public function linkToProject(Request $request, TileQuotation $quotation)
    {
        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        if ($quotation->isLinked()) {
            return back()->with('error', 'Already linked to a project.');
        }

        $quotation->client_project_id = $data['client_project_id'];
        $quotation->linked_at = now();
        $quotation->save();

        $this->syncProjectQuotation($quotation);

        return redirect()
            ->route('admin.client-projects.show', ['client_project' => $quotation->client_project_id, 'tab' => 'quotation'])
            ->with('success', 'Quotation linked successfully.');
    }

    public function createProjectAndLink(
        Request $request,
        TileQuotation $quotation,
        \App\Services\ClientProjectStatusService $statusService
    ) {
        if ($quotation->isLinked()) {
            return back()->with('error', 'This tile quotation is already linked to a project.');
        }
        if ($quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before creating a project.');
        }

        $this->normalisePhoneOnRequest($request);

        if (! filled(trim((string) $request->input('fp_client_address', '')))) {
            $request->merge([
                'fp_client_address' => null,
                'fp_client_address_place_id' => null,
                'fp_client_address_latitude' => null,
                'fp_client_address_longitude' => null,
            ]);
        }

        $data = $request->validate([
            'fp_project_name' => ['nullable', 'string', 'max:255'],
            'fp_client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'fp_client_name' => ['required', 'string', 'max:255'],
            'fp_client_email' => ['nullable', 'email', 'max:255'],
            'fp_client_phone' => ['nullable', 'digits:10'],
            'fp_client_address' => ['nullable', 'string', 'max:500'],
            'fp_client_address_place_id' => ['nullable', 'string', 'max:255'],
            'fp_client_address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'fp_client_address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits.',
        ]);

        $clientName = trim($data['fp_client_name']);
        $projectTitle = trim((string) ($data['fp_project_name'] ?? ''));
        if ($projectTitle === '') {
            $projectTitle = $clientName.' — Tile';
        }
        $projectTitle = \Illuminate\Support\Str::limit($projectTitle, 255, '');

        $project = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $projectTitle, $clientName, $quotation) {
            $project = ClientProject::create([
                'name' => $projectTitle,
                'client_id' => $data['fp_client_id'] ?: null,
                'client_name' => $clientName,
                'client_email' => $data['fp_client_email'] ?: null,
                'client_phone' => $data['fp_client_phone'] ?: null,
                'client_address' => $data['fp_client_address'] ?: null,
                'client_address_place_id' => $data['fp_client_address_place_id'] ?: null,
                'client_address_latitude' => $data['fp_client_address_latitude'] ?? null,
                'client_address_longitude' => $data['fp_client_address_longitude'] ?? null,
                'billing_type' => ClientProject::BILLING_FIXED,
                'hourly_rate' => null,
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'status_changed_at' => now(),
            ]);

            $quotation->client_project_id = $project->id;
            $quotation->linked_at = now();
            $quotation->save();

            $this->syncProjectQuotation($quotation);

            return $project;
        });

        $statusService->onCreated($project, false);
        app(\App\Services\ClientProjectContactService::class)->ensureProjectLinkedToDirectory($project->fresh());

        return redirect()
            ->to(route('admin.client-projects.show', ['client_project' => $project->id]).'#quotation')
            ->with('success', 'Project created. The tile quotation was added to the Quotation tab.');
    }

    protected function normalisePhoneOnRequest(Request $request): void
    {
        if (! $request->has('fp_client_phone')) {
            return;
        }

        $raw = (string) $request->input('fp_client_phone');
        $digits = preg_replace('/\D/', '', $raw);
        $request->merge([
            'fp_client_phone' => $digits === '' ? null : $digits,
        ]);
    }

    public function calculateLive(Request $request, TileQuotationCalculator $calculator)
    {
        try {
            $totalAreaSqft = (float) $request->input('total_area_sqft', 0);
            $lengthFt = (float) $request->input('length_ft', 0);
            $widthFt = (float) $request->input('width_ft', 0);
            
            \Illuminate\Support\Facades\Log::info("Tile Live Calc: total_area={$totalAreaSqft}, length={$lengthFt}, width={$widthFt}");
            
            if (($lengthFt <= 0 || $widthFt <= 0) && $totalAreaSqft <= 0) {
                return response()->json(['valid' => false]);
            }
            
            $includeTile = filter_var($request->input('include_tile_material', false), FILTER_VALIDATE_BOOLEAN);

            $calculation = $calculator->compute(
                $lengthFt,
                $widthFt,
                (float) $request->input('waste_margin_percent', 10),
                empty($request->input('tile_product_id')) ? null : (int) $request->input('tile_product_id'),
                $includeTile,
                $request->input('trowel_size', '1/4x1/4'),
                empty($request->input('mortar_type_id')) ? null : (int) $request->input('mortar_type_id'),
                (float) $request->input('joint_width_decimal', 0.125),
                empty($request->input('grout_type_id')) ? null : (int) $request->input('grout_type_id'),
                (float) $request->input('labor_rate', 0),
                $totalAreaSqft,
                filter_var($request->input('include_demolition', false), FILTER_VALIDATE_BOOLEAN),
                $request->input('demolition_complexity', 'standard'),
                (float) $request->input('custom_demolition_rate', 0),
                filter_var($request->input('include_disposal_fee', false), FILTER_VALIDATE_BOOLEAN)
            );

            $html = view('admin.quotations.tile._live_breakdown', compact('calculation'))->render();

            return response()->json([
                'valid' => true,
                'html' => $html,
                'calculation' => $calculation,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tile calculateLive error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['valid' => false, 'error' => $e->getMessage()]);
        }
    }

    protected function syncProjectQuotation(TileQuotation $quotation): void
    {
        if (! $quotation->client_project_id) {
            return;
        }

        $items = [[
            'description' => 'Tile installation (labor)',
            'amount' => $quotation->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $quotation->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Client-selected tile & materials',
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }

        $snapshot = $quotation->only([
            'quote_number',
            'client_name',
            'inputs',
            'calculation',
            'final_quote_total',
            'work_description',
            'version',
        ]);

        $row = ClientProjectQuotation::query()->firstOrNew([
            'client_project_id' => $quotation->client_project_id,
            'source_type' => ClientProjectQuotation::SOURCE_TILE,
            'source_id' => $quotation->id,
        ]);

        if (! $row->exists) {
            $row->fill([
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'quotation_number' => ClientProjectQuotation::generateNumber(
                    ClientProjectQuotation::TYPE_QUOTATION
                ),
                'version' => 1,
            ]);
        }

        $row->labour = true;
        $row->material = $quotation->includesMaterialCharge();
        $row->title = $quotation->displayTitle();
        $row->items = $items;
        $row->work_description = $quotation->scopeDescriptionForPdf();
        $row->source_snapshot = $snapshot;

        $row->save();

        if ($quotation->project) {
            $quotation->project->syncFixedContractTotalsFromQuotation();
        }
    }
}
