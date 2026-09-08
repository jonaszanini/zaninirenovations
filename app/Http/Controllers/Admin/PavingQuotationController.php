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
use App\Models\PavingQuotation;
use App\Models\PavingMarbleType;
use App\Models\PavingStoneType;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\PavingMarbleCalculator;
use App\Support\QuotationAfterSaveRedirect;
use App\Services\PavingQuotationCalculator;
use App\Services\PavingStoneEntryResolver;
use App\Services\QuotationPaymentSchedule;
use App\Support\PurchaseListPrintMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PavingQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            PavingQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.paving.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    public function create(Request $request)
    {
        return $this->formView(null, $request);
    }

    public function store(Request $request)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, null);

        $quote = PavingQuotation::create(array_merge($calc, [
            'quote_number' => PavingQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkPavingIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.paving.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Paving quotation saved as draft and linked.' : 'Paving quotation saved as draft.')
                : ($linkedNow
                    ? 'Paving quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Paving quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, PavingQuotation $paving_quotation)
    {
        return $this->formView($paving_quotation, $request);
    }

    public function update(Request $request, PavingQuotation $paving_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $paving_quotation);

        $paving_quotation->fill(array_merge($calc, [
            'version' => $paving_quotation->version + 1,
        ]))->save();

        if ($paving_quotation->isLinked()) {
            $project = ClientProject::query()->find($paving_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $paving_quotation) {
                    $this->syncPavingLineToProjectQuotation($project, $paving_quotation->fresh());
                });
            }
        }

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkPavingIfRequested($paving_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.paving.edit', $paving_quotation)
            ->with('success', $linkedNow
                ? 'Paving quotation '.$paving_quotation->quote_number.' updated and linked.'
                : 'Paving quotation '.$paving_quotation->quote_number.' updated (v'.$paving_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request)
    {
        try {
            $inputs = $this->gatherInputsFromRequest($request);
            $calc = (new PavingQuotationCalculator)->calculate($inputs);

            return response()->json($calc);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Printable purchasing list (materials + rentals) for a saved quote.
     * Quantities come from the stored calculation breakdown.
     */
    public function purchaseList(Request $request, PavingQuotation $paving_quotation)
    {
        $paving_quotation->loadMissing('project');

        $inputs = is_array($paving_quotation->inputs) ? $paving_quotation->inputs : [];
        $calc = $inputs !== []
            ? (new PavingQuotationCalculator)->calculate($inputs)
            : $paving_quotation->calculation;
        $materialRows = [];
        $rentalRows = [];
        $stoneLinks = $this->purchaseListStoneLinks($paving_quotation);

        if (is_array($calc)) {
            foreach ($calc['materials']['rows'] ?? [] as $row) {
                if ((float) ($row['qty'] ?? 0) <= 0) {
                    continue;
                }
                $materialRows[] = $this->formatPurchaseListRow($row, $stoneLinks);
            }

            $rentalLabels = ['Bin rental (14 yd)', 'Mini skid steer', 'Plate compactor'];
            $disposalLabel = 'Disposal (clean fill)';

            foreach ($calc['fixed_costs']['rows'] ?? [] as $row) {
                if ((float) ($row['qty'] ?? 0) <= 0) {
                    continue;
                }
                $label = (string) ($row['label'] ?? '');
                if (in_array($label, $rentalLabels, true)) {
                    $rentalRows[] = $this->formatPurchaseListRow($row, $stoneLinks);
                } elseif ($label === $disposalLabel) {
                    $materialRows[] = $this->formatPurchaseListRow($row, $stoneLinks);
                }
            }
        }

        return view('admin.quotations.paving.purchase_list', [
            'quotation' => $paving_quotation,
            'materialRows' => $materialRows,
            'rentalRows' => $rentalRows,
            'showPrices' => PurchaseListPrintMode::showsPrices($request->query('prices')),
        ]);
    }

    public function linkToProject(Request $request, PavingQuotation $paving_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($paving_quotation, 'paving')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($paving_quotation, $project);

        if ($paving_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $paving_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $paving_quotation->summaryDescription();
        }

        DB::transaction(function () use ($project, $paving_quotation, $lineDesc) {
            $this->syncPavingLineToProjectQuotation($project, $paving_quotation, $lineDesc);
        });

        return redirect()
            ->route('admin.quotations.paving.edit', $paving_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    public function createProjectAndLink(
        Request $request,
        PavingQuotation $paving_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($paving_quotation, 'paving')) {
            return $resp;
        }
        if ($paving_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $paving_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before creating a project.');
        }

        $this->normalisePhoneOnRequest($request);

        $data = $request->validate([
            'link_line_description' => ['nullable', 'string', 'max:500'],
            'fp_project_name' => ['nullable', 'string', 'max:255'],
            'fp_client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'fp_client_name' => ['required', 'string', 'max:255'],
            'fp_client_email' => ['nullable', 'email', 'max:255'],
            'fp_client_phone' => ['nullable', 'digits:10'],
            'fp_client_address' => ['nullable', 'string', 'max:500'],
        ], [
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits.',
        ]);

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $paving_quotation->summaryDescription();
        }

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Paving');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $paving_quotation, $lineDesc) {
            $project = ClientProject::create([
                'name' => $projectTitle,
                'client_id' => $data['fp_client_id'] ?: null,
                'client_name' => $clientName,
                'client_email' => $data['fp_client_email'] ?: null,
                'client_phone' => $data['fp_client_phone'] ?: null,
                'client_address' => $data['fp_client_address'] ?: null,
                'billing_type' => ClientProject::BILLING_FIXED,
                'hourly_rate' => null,
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'status_changed_at' => now(),
            ]);

            $this->syncPavingLineToProjectQuotation($project, $paving_quotation, $lineDesc);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The paving line was added to the Quotation tab.');
    }

    public function destroy(PavingQuotation $paving_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_PAVING)
            ->where('source_id', $paving_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $paving_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.paving.index')
            ->with('success', 'Paving quotation deleted.');
    }

    protected function formView(?PavingQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();

        $stoneTypes = PavingStoneType::query()->orderBy('sort_order')->get();
        $marbleTypes = PavingMarbleType::query()->orderBy('sort_order')->get();

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.paving.create', [
            'quotation' => $quotation,
            'stoneTypes' => $stoneTypes,
            'marbleTypes' => $marbleTypes,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $quotation
                ? ClientProjectQuotation::findForSource(
                    ClientProjectQuotation::SOURCE_PAVING,
                    (int) $quotation->id
                )
                : null,
        ]);
    }

    /**
     * @return array{inputs: array, calculation: array, final_quote_total: float, client_name: ?string, work_description: ?string, include_commute: bool, commute_trip_percent: float, commute_estimated_trips: int, commute_round_trip_cost: ?float, commute_amount: float, is_draft?: bool}
     */
    protected function validatedCalculation(Request $request, string $intent, ?PavingQuotation $existing = null): array
    {
        $clientRules = $intent === 'draft'
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:5000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules()), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages(), AdditionalCostsService::validationMessages()));

        $inputs = $this->gatherInputsFromRequest($request);
        $calc = (new PavingQuotationCalculator)->calculate($inputs);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);
        $workDescription = trim((string) $request->input('work_description'));

        $base = array_merge([
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription === '' ? null : $workDescription,
            'is_draft' => $intent === 'draft',
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'paving_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'paving_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'paving_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request): array
    {
        $this->normalizeCadMoneyStringsOnRequest($request);
        $this->normalizeMarbleInputsOnRequest($request);

        $data = $request->validate([
            'stone_entries' => ['required', 'array', 'min:1', 'max:'.PavingStoneEntryResolver::MAX_ENTRIES],
            'stone_entries.*.stone_type_id' => ['required', 'integer', 'exists:paving_stone_types,id'],
            'stone_entries.*.input_mode' => ['required', Rule::in(PavingStoneEntryResolver::INPUT_MODES)],
            'stone_entries.*.qty' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'stone_entries.*.area_sq_ft' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'stone_entries.*.length_ft' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'stone_entries.*.width_ft' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'helper_hourly_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'owner_hourly_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'mini_skid_days' => ['required', 'integer', 'min:0', 'max:365'],
            'plate_compactor_days' => ['required', 'integer', 'min:0', 'max:365'],
            'profit_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'waste_factor' => ['required', 'numeric', 'min:0', 'max:100'],
            'excavation_depth_in' => ['required', 'numeric', 'min:1', 'max:48'],
            'bedding_sand_depth_in' => ['nullable', 'numeric', 'min:0.25', 'max:6'],
            'hst_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'manual_price_override' => ['nullable', 'numeric', 'min:0'],
            'marble_enabled' => ['nullable'],
            'marble_type_id' => ['nullable', 'integer', 'exists:paving_marble_types,id'],
            'marble_area_sq_ft' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'marble_depth_in' => ['nullable', 'numeric', 'min:0.5', 'max:12'],
            'marble_include_geotextile' => ['nullable'],
        ]);

        $marbleEnabled = $request->boolean('marble_enabled');
        if ($marbleEnabled) {
            $request->validate([
                'marble_type_id' => ['required', 'integer', 'exists:paving_marble_types,id'],
                'marble_area_sq_ft' => ['required', 'numeric', 'min:0.1', 'max:1000000'],
                'marble_depth_in' => ['required', 'numeric', 'min:0.5', 'max:12'],
            ]);
        }

        $profitMargin = (float) $data['profit_margin'];
        if ($profitMargin > 1) {
            $profitMargin /= 100;
        }
        $waste = (float) $data['waste_factor'];
        if ($waste > 1) {
            $waste /= 100;
        }
        $hst = (float) $data['hst_rate'];
        if ($hst > 0.5) {
            $hst /= 100;
        }

        $stoneEntries = PavingStoneEntryResolver::resolveFromRequest($data['stone_entries']);
        $totalAreaSqFt = PavingStoneEntryResolver::totalArea($stoneEntries);

        $excavationDepthIn = (float) $data['excavation_depth_in'];
        $sandDepthIn = isset($data['bedding_sand_depth_in']) && $data['bedding_sand_depth_in'] !== ''
            ? (float) $data['bedding_sand_depth_in']
            : 1.0;
        $stoneHeightIn = PavingStoneEntryResolver::maxPaverHeightIn($stoneEntries);
        $crushedBaseDepthIn = max(0.0, $excavationDepthIn - $stoneHeightIn - $sandDepthIn);

        if ($crushedBaseDepthIn < 0.5) {
            throw ValidationException::withMessages([
                'excavation_depth_in' => sprintf(
                    'Excavation depth must be greater than paver height (%s in.) plus bedding sand (%s in.).',
                    rtrim(rtrim(number_format($stoneHeightIn, 2), '0'), '.'),
                    rtrim(rtrim(number_format($sandDepthIn, 2), '0'), '.'),
                ),
            ]);
        }

        $primaryEntry = PavingStoneEntryResolver::primaryEntryForCoverage($stoneEntries);

        $marblePayload = [
            'marble_enabled' => $marbleEnabled,
            'marble_type_id' => null,
            'marble_area_sq_ft' => 0,
            'marble_depth_in' => 2,
            'marble_include_geotextile' => true,
        ];

        if ($marbleEnabled) {
            $marbleType = PavingMarbleType::query()->findOrFail($data['marble_type_id']);
            $marbleArea = (float) $data['marble_area_sq_ft'];
            $marbleDepth = (float) $data['marble_depth_in'];
            $marbleGeo = $request->boolean('marble_include_geotextile');
            $snapshot = PavingMarbleCalculator::snapshotFromType($marbleType, $marbleArea, $marbleDepth, $marbleGeo);

            $marblePayload = [
                'marble_enabled' => true,
                'marble_type_id' => (int) $marbleType->id,
                'marble_area_sq_ft' => $marbleArea,
                'marble_depth_in' => $marbleDepth,
                'marble_include_geotextile' => $marbleGeo,
                'marble_name' => $snapshot['marble_name'],
                'marble_reference_depth_in' => $snapshot['reference_depth_in'],
                'marble_sq_ft_per_bag_at_reference_depth' => $snapshot['sq_ft_per_bag_at_reference_depth'],
                'marble_price_per_bag' => $snapshot['price_per_bag'],
                'marble_bulk_min_bags' => $snapshot['bulk_min_bags'],
                'marble_bulk_discount_pct' => $snapshot['bulk_discount_pct'],
                'marble_delivery_cost' => $snapshot['delivery_cost'],
                'marble_additional_cost' => $snapshot['additional_cost'],
                'marble_additional_cost_description' => $snapshot['additional_cost_description'],
            ];
        }

        return array_merge([
            'stone_entries' => $stoneEntries,
            'total_area_sq_ft' => $totalAreaSqFt,
            'paving_stone_type_id' => $primaryEntry['stone_type_id'] ?? null,
            'stone_sq_ft_per_stone' => $primaryEntry['sq_ft_per_stone'] ?? null,
            'stone_price_per_stone' => $primaryEntry['price_per_stone'] ?? null,
            'stone_price_per_sq_ft' => $primaryEntry['price_per_sq_ft'] ?? null,
            'stone_delivery_cost' => $primaryEntry['delivery_cost'] ?? null,
            'stone_additional_cost' => $primaryEntry['additional_cost'] ?? null,
            'stone_additional_cost_description' => $primaryEntry['additional_cost_description'] ?? null,
            'helper_hourly_rate' => (float) $data['helper_hourly_rate'],
            'owner_hourly_rate' => (float) $data['owner_hourly_rate'],
            'include_helper' => $request->boolean('include_helper'),
            'mini_skid_days' => (int) $data['mini_skid_days'],
            'plate_compactor_days' => (int) $data['plate_compactor_days'],
            'profit_margin' => $profitMargin,
            'waste_factor' => $waste,
            'excavation_depth_in' => $excavationDepthIn,
            'bedding_sand_depth_in' => $sandDepthIn,
            'stone_height_in' => $stoneHeightIn,
            'crushed_base_depth_in' => $crushedBaseDepthIn,
            'stone_length_in' => $primaryEntry['length_in'] ?? null,
            'stone_width_in' => $primaryEntry['width_in'] ?? null,
            'hst_rate' => $hst,
            'add_polymeric_sand' => $request->boolean('add_polymeric_sand'),
            'include_old_surface_removal' => $request->boolean('include_old_surface_removal'),
            'include_geotextile' => $request->boolean('include_geotextile'),
            'manual_price_override' => isset($data['manual_price_override']) && $data['manual_price_override'] !== ''
                ? (float) $data['manual_price_override']
                : null,
        ], $marblePayload);
    }

    /**
     * Clear marble fields when the bed is disabled so preview/save validation does not fail on empty defaults.
     */
    protected function normalizeMarbleInputsOnRequest(Request $request): void
    {
        if ($request->input('marble_type_id') === '' || $request->input('marble_type_id') === null) {
            $request->merge(['marble_type_id' => null]);
        }

        if ($request->boolean('marble_enabled')) {
            return;
        }

        $request->merge([
            'marble_type_id' => null,
            'marble_area_sq_ft' => null,
            'marble_depth_in' => 2,
            'marble_include_geotextile' => true,
        ]);
    }

    protected function normalizeCadMoneyStringsOnRequest(Request $request): void
    {
        $moneyKeys = ['helper_hourly_rate', 'owner_hourly_rate', 'manual_price_override'];
        $optionalEmptyToNull = ['manual_price_override'];
        $emptyMeansZero = ['helper_hourly_rate', 'owner_hourly_rate'];

        foreach ($moneyKeys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $v = $request->input($key);
            if (($v === '' || $v === null) && in_array($key, $optionalEmptyToNull, true)) {
                $request->merge([$key => null]);

                continue;
            }
            if (($v === '' || $v === null) && in_array($key, $emptyMeansZero, true)) {
                $request->merge([$key => '0']);

                continue;
            }
            if (! is_string($v)) {
                continue;
            }
            $request->merge([$key => str_replace(',', '', $v)]);
        }
    }

    protected function autoLinkPavingIfRequested(PavingQuotation $pq, Request $request, array &$warnings): bool
    {
        if ($pq->isLinked()) {
            return false;
        }

        if ($pq->is_draft) {
            return false;
        }

        $project = $this->quotationLinkCandidateProject($request);
        if (! $project) {
            return false;
        }

        if (! $this->quotationProjectAllowsNewLink($project)) {
            $warnings[] = 'This project cannot accept linked quotations anymore. Save your work and link manually.';

            return false;
        }

        if ((float) $pq->final_quote_total <= 0) {
            $warnings[] = 'Set a final quote total greater than zero before it can attach to the project.';

            return false;
        }

        try {
            $lineDesc = $pq->summaryDescription();
            DB::transaction(function () use ($project, $pq, $lineDesc) {
                $this->syncPavingLineToProjectQuotation($project, $pq->fresh(), $lineDesc);
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncPavingLineToProjectQuotation(
        ClientProject $project,
        PavingQuotation $pavingQuotation,
        ?string $lineDescription = null
    ): void {
        $lineDescription = trim((string) ($lineDescription ?? ''));
        if ($lineDescription === '') {
            $lineDescription = $pavingQuotation->summaryDescription();
        }

        $items = $this->buildPavingProjectLineItems($pavingQuotation, $lineDescription);

        $snapshot = $pavingQuotation->only([
            'quote_number',
            'client_name',
            'work_description',
            'inputs',
            'calculation',
            'final_quote_total',
            'version',
        ]);

        /** @var ClientProjectQuotation $row */
        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'source_id' => $pavingQuotation->id,
        ]);

        if (!$row->exists) {
            $row->fill([
                'title' => 'Paving — '.$pavingQuotation->quote_number,
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'labour' => true,
                'material' => true,
                'quotation_number' => ClientProjectQuotation::generateNumber(
                    ClientProjectQuotation::TYPE_QUOTATION
                ),
                'version' => 1,
            ]);
        }

        $row->items = $items;
        $row->work_description = $pavingQuotation->work_description;
        $this->applyPaymentScheduleToProjectQuotation($row, $pavingQuotation);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();

        $pavingQuotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => $pavingQuotation->linked_at ?? now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

    /**
     * Project quotation lines for a paving calculator quote.
     *
     * Final quote already includes HST on materials & fixed costs plus profit;
     * labour is pre-tax only. Split so the project PDF shows 13% HST on labour
     * without double-taxing the materials portion.
     *
     * @return list<array{description: string, amount: float, apply_hst: bool, hst_included?: bool}>
     */
    protected function buildPavingProjectLineItems(PavingQuotation $pavingQuotation, string $lineDescription): array
    {
        $final = round((float) $pavingQuotation->final_quote_total, 2);
        $calc = is_array($pavingQuotation->calculation) ? $pavingQuotation->calculation : [];
        $labour = round((float) ($calc['summary']['labour_total'] ?? $calc['labour']['total'] ?? 0), 2);
        $labourHst = round($labour * ClientProjectQuotation::HST_RATE, 2);
        $commute = ($pavingQuotation->include_commute ?? false)
            ? round((float) ($pavingQuotation->commute_amount ?? 0), 2)
            : 0.0;
        $debris = ($pavingQuotation->include_debris_removal ?? false)
            ? round((float) ($pavingQuotation->debris_removal_amount ?? 0), 2)
            : 0.0;
        $exempt = round($final - $labour - $labourHst - $commute - $debris, 2);

        if ($labour <= 0 || $labourHst <= 0) {
            return [[
                'description' => $lineDescription,
                'amount' => $final,
                'apply_hst' => false,
            ]];
        }

        $items = [];
        if ($exempt > 0) {
            $items[] = [
                'description' => $lineDescription.' — Material',
                'amount' => $exempt,
                'apply_hst' => false,
                'hst_included' => true,
            ];
        }
        $items[] = [
            'description' => $lineDescription.' — Labour',
            'amount' => round($labour + $commute + $debris, 2),
            'apply_hst' => true,
            'hst_taxable_amount' => $labour,
        ];

        return $items;
    }

    /** @return array<string, string> Calculator row label => product URL */
    protected function purchaseListStoneLinks(PavingQuotation $quotation): array
    {
        $inputs = is_array($quotation->inputs) ? $quotation->inputs : [];
        $links = [];

        $entries = PavingStoneEntryResolver::normalize($inputs);
        foreach ($entries as $entry) {
            $stoneId = $entry['stone_type_id'] ?? null;
            if (! $stoneId) {
                continue;
            }
            $stone = PavingStoneType::query()->find($stoneId);
            if ($stone && filled($stone->product_url)) {
                $label = ($entry['stone_name'] ?? 'Paving stones').' paving stones';
                $links[$label] = $stone->product_url;
            }
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $stoneLinks
     * @return array{quantity_label: string, label: string, unit: string, note: string, product_url: string|null, unit_price_label: string, with_tax_label: string}
     */
    protected function formatPurchaseListRow(array $row, array $stoneLinks): array
    {
        $label = html_entity_decode((string) ($row['label'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $qty = (float) ($row['qty'] ?? 0);
        $qtyTons = isset($row['qty_tons']) ? (float) $row['qty_tons'] : 0.0;
        $unit = (string) ($row['unit'] ?? '');
        $note = html_entity_decode((string) ($row['note'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $withTax = (float) ($row['with_tax'] ?? 0);
        if ($withTax <= 0 && isset($row['before_tax'])) {
            $withTax = round((float) $row['before_tax'] + (float) ($row['hst'] ?? 0), 2);
        }
        $unitPrice = (float) ($row['unit_price'] ?? 0);

        $quantityLabel = $this->formatPurchaseQuantityLabel($qty);
        if ($qtyTons > 0) {
            $quantityLabel = $this->formatPurchaseQuantityLabel($qty).' cu yd · '
                .$this->formatPurchaseQuantityLabel($qtyTons).' tons';
            $unit = 'vol. / wt.';
        }

        return [
            'quantity_label' => $quantityLabel,
            'label' => $label,
            'unit' => $unit,
            'note' => $note !== '' && $note !== '—' ? $note : '',
            'product_url' => $stoneLinks[$label] ?? null,
            'unit_price_label' => '$'.number_format($unitPrice, 2, '.', ','),
            'with_tax_label' => '$'.number_format($withTax, 2, '.', ','),
        ];
    }

    protected function formatPurchaseQuantityLabel(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.00001) {
            return (string) (int) round($qty);
        }

        $formatted = number_format($qty, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }
}
