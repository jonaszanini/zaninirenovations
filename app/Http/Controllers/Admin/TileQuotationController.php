<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationAdditionalCosts;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\TileEdge;
use App\Models\TileGroutType;
use App\Models\TileMortarType;
use App\Models\TileProduct;
use App\Models\TileQuotation;
use App\Services\AdditionalCostsService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Services\TileQuotationCalculator;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationBuildingLabor;
use Illuminate\Http\Request;

class TileQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

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
        $roomAreaImport = app(\App\Services\RoomAreaQuotePrefill::class)->forRequest($request, 'tile');
        $linkProject = $this->mergeRoomAreaLinkProject(
            $this->quotationLinkCandidateProject($request),
            $roomAreaImport
        );
        if ($linkProject && ! $linkProject->relationLoaded('photos')) {
            $linkProject->load('photos');
        }

        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $edges = TileEdge::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.quotations.tile.create', compact('linkProject', 'mortarTypes', 'groutTypes', 'products', 'edges') + [
            'linkedCpq' => null,
            'roomAreaImport' => $roomAreaImport,
        ]);
    }

    public function store(Request $request, TileQuotationCalculator $calculator)
    {
        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $data = $request->validate(array_merge([
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string', 'max:20000'],
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
            'include_edge' => ['nullable', 'boolean'],
            'tile_edge_id' => ['nullable', 'integer', 'exists:tile_edges,id'],
            'edge_lf' => ['nullable', 'numeric', 'min:0'],

            'is_draft' => ['nullable', 'string'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules(), QuotationBuildingLabor::validationRules()), array_merge(
            QuotationPaymentSchedule::validationMessages(),
            CommuteExpenseService::commuteValidationMessages(),
            DebrisRemovalExpenseService::validationMessages(),
            AdditionalCostsService::validationMessages(),
            QuotationBuildingLabor::validationMessages()
        ));

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $includeEdge = filter_var($data['include_edge'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $this->computeFromData($calculator, $data, $includeTile, $includeEdge);

        $quotation = new TileQuotation();
        $quotation->client_project_id = empty($data['client_project_id']) ? null : (int) $data['client_project_id'];
        $quotation->quote_number = TileQuotation::generateNumber();
        $quotation->client_name = $data['client_name'] ?? null;
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($data['work_description'] ?? null);
        $schedule = $this->paymentScheduleFromRequest($request);
        $quotation->payment_installments = $schedule['payment_installments'];
        $quotation->payment_percentages = $schedule['payment_percentages'];
        
        $quotation->inputs = $this->inputsFromData($data, $includeTile, $includeEdge);
        $quotation->calculation = $calculation;
        $commute = $this->commutePersistenceFromRequest($request, null, 'tile_quotations');
        $merged = $this->mergeCommuteIntoPersistence(
            ['final_quote_total' => round((float) $calculation['grand_total'], 2)],
            $commute,
            'final_quote_total'
        );
        $debris = $this->debrisRemovalPersistenceFromRequest($request, null, 'tile_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, null, 'tile_quotations');
        $merged = $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
        $quotation->final_quote_total = $merged['final_quote_total'];
        if ($commute !== []) {
            $quotation->include_commute = $merged['include_commute'];
            $quotation->commute_trip_percent = $merged['commute_trip_percent'];
            $quotation->commute_estimated_trips = $merged['commute_estimated_trips'];
            $quotation->commute_round_trip_cost = $merged['commute_round_trip_cost'];
            $quotation->commute_amount = $merged['commute_amount'];
        }
        if ($debris !== []) {
            $quotation->include_debris_removal = $merged['include_debris_removal'];
            $quotation->debris_removal_amount = $merged['debris_removal_amount'];
        }
        if (array_key_exists('additional_costs', $merged)) {
            $quotation->additional_costs = $merged['additional_costs'];
        }
        
        if ($quotation->client_project_id) {
            $quotation->linked_at = now();
        }
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
        }

        return QuotationAfterSaveRedirect::apply($request, redirect()
            ->route('admin.quotations.tile.edit', $quotation)
            ->with('success', $quotation->client_project_id
                ? 'Tile quotation created and linked.'
                : 'Tile quotation created.'));
    }

    public function edit(Request $request, TileQuotation $quotation)
    {
        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $selectedEdgeId = (int) (($quotation->inputs['tile_edge_id'] ?? 0));
        $edges = TileEdge::query()
            ->where(function ($q) use ($selectedEdgeId) {
                $q->where('is_active', true);
                if ($selectedEdgeId > 0) {
                    $q->orWhere('id', $selectedEdgeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $quotation->load('project.photos');
        $returnToProject = $request->query('return') === 'project';
        $linkProject = $quotation->project;

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('created_at')
            ->get();

        return view('admin.quotations.tile.create', compact('quotation', 'mortarTypes', 'groutTypes', 'products', 'edges', 'returnToProject', 'linkProject', 'linkableProjects') + [
            'linkedCpq' => ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_TILE,
                (int) $quotation->id
            ),
        ]);
    }

    public function update(Request $request, TileQuotation $quotation, TileQuotationCalculator $calculator)
    {
        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $data = $request->validate(array_merge([
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string', 'max:20000'],
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
            'include_edge' => ['nullable', 'boolean'],
            'tile_edge_id' => ['nullable', 'integer', 'exists:tile_edges,id'],
            'edge_lf' => ['nullable', 'numeric', 'min:0'],
            'is_draft' => ['nullable', 'string'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules(), QuotationBuildingLabor::validationRules()), array_merge(
            QuotationPaymentSchedule::validationMessages(),
            CommuteExpenseService::commuteValidationMessages(),
            DebrisRemovalExpenseService::validationMessages(),
            AdditionalCostsService::validationMessages(),
            QuotationBuildingLabor::validationMessages()
        ));

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $includeEdge = filter_var($data['include_edge'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $this->computeFromData($calculator, $data, $includeTile, $includeEdge);

        if (! $quotation->isLinked()) {
            $quotation->client_name = $data['client_name'] ?? null;
        }
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($data['work_description'] ?? null);
        $schedule = $this->paymentScheduleFromRequest($request);
        $quotation->payment_installments = $schedule['payment_installments'];
        $quotation->payment_percentages = $schedule['payment_percentages'];
        
        $quotation->inputs = $this->inputsFromData($data, $includeTile, $includeEdge);
        $quotation->calculation = $calculation;
        $commute = $this->commutePersistenceFromRequest($request, $quotation, 'tile_quotations');
        $merged = $this->mergeCommuteIntoPersistence(
            ['final_quote_total' => round((float) $calculation['grand_total'], 2)],
            $commute,
            'final_quote_total'
        );
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $quotation, 'tile_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $quotation, 'tile_quotations');
        $merged = $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
        $quotation->final_quote_total = $merged['final_quote_total'];
        if ($commute !== []) {
            $quotation->include_commute = $merged['include_commute'];
            $quotation->commute_trip_percent = $merged['commute_trip_percent'];
            $quotation->commute_estimated_trips = $merged['commute_estimated_trips'];
            $quotation->commute_round_trip_cost = $merged['commute_round_trip_cost'];
            $quotation->commute_amount = $merged['commute_amount'];
        }
        if ($debris !== []) {
            $quotation->include_debris_removal = $merged['include_debris_removal'];
            $quotation->debris_removal_amount = $merged['debris_removal_amount'];
        }
        if (array_key_exists('additional_costs', $merged)) {
            $quotation->additional_costs = $merged['additional_costs'];
        }
        $quotation->version += 1;
        
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
        }

        return QuotationAfterSaveRedirect::apply($request, redirect()
            ->route('admin.quotations.tile.edit', $quotation)
            ->with('success', 'Tile quotation updated.'));
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
        if ($resp = $this->guardQuotationAlreadyLinked($quotation, 'tile')) {
            return $resp;
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
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Tile');

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

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', ['client_project' => $project->id]).'#quotation')
            ->with('success', 'Project created. The tile quotation was added to the Quotation tab.');
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
            $includeEdge = filter_var($request->input('include_edge', false), FILTER_VALIDATE_BOOLEAN);

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
                filter_var($request->input('include_disposal_fee', false), FILTER_VALIDATE_BOOLEAN),
                $includeEdge,
                empty($request->input('tile_edge_id')) ? null : (int) $request->input('tile_edge_id'),
                (float) $request->input('edge_lf', 0),
                filter_var($request->input('include_building', false), FILTER_VALIDATE_BOOLEAN),
                (float) ($request->input('building_labor_percent', QuotationBuildingLabor::DEFAULT_PERCENT) ?: QuotationBuildingLabor::DEFAULT_PERCENT)
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

        $labourAmt = $quotation->labourPreTaxAmountForProjectQuotation();
        if (! empty($quotation->include_debris_removal)) {
            $debrisGross = (float) ($quotation->debris_removal_amount ?? 0);
            if ($debrisGross > 0) {
                $labourAmt = round(
                    $labourAmt + ClientProjectQuotation::preTaxFromHstIncluded($debrisGross),
                    2
                );
            }
        }

        $items = [[
            'description' => 'Tile installation (labor)',
            'amount' => $labourAmt,
            'apply_hst' => true,
        ]];

        $materialAmt = $quotation->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Materials',
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }

        $this->appendCommuteProjectLine($items, $quotation);
        $this->appendAdditionalCostsProjectLines($items, $quotation);

        $snapshot = $quotation->only([
            'quote_number',
            'client_name',
            'inputs',
            'calculation',
            'final_quote_total',
            'work_description',
            'version',
            'include_commute',
            'commute_amount',
            'commute_estimated_trips',
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
        $this->applyPaymentScheduleToProjectQuotation($row, $quotation);
        $row->source_snapshot = $snapshot;

        $row->save();

        if ($quotation->project) {
            $quotation->project->syncFixedContractTotalsFromQuotation();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function computeFromData(
        TileQuotationCalculator $calculator,
        array $data,
        bool $includeTile,
        bool $includeEdge
    ): array {
        return $calculator->compute(
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
            filter_var($data['include_disposal_fee'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $includeEdge,
            empty($data['tile_edge_id']) ? null : (int) $data['tile_edge_id'],
            (float) ($data['edge_lf'] ?? 0),
            filter_var($data['include_building'] ?? false, FILTER_VALIDATE_BOOLEAN),
            (float) ($data['building_labor_percent'] ?? QuotationBuildingLabor::DEFAULT_PERCENT)
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function inputsFromData(array $data, bool $includeTile, bool $includeEdge): array
    {
        return [
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
            'include_edge' => $includeEdge,
            'tile_edge_id' => empty($data['tile_edge_id']) ? null : (int) $data['tile_edge_id'],
            'edge_lf' => (float) ($data['edge_lf'] ?? 0),
            'include_building' => filter_var($data['include_building'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'building_labor_percent' => (float) ($data['building_labor_percent'] ?? QuotationBuildingLabor::DEFAULT_PERCENT),
        ];
    }
}
