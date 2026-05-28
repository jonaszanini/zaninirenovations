<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\PavingMaterialRate;
use App\Models\PavingPricingTier;
use App\Models\PavingQuotation;
use App\Models\PavingStoneType;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\PavingQuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PavingQuotationController extends Controller
{
    use HandlesQuotationCommuteExpense;
    use ResolvesQuotationProjectLink;

    public function index()
    {
        $quotations = PavingQuotation::query()
            ->with('project')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.quotations.paving.index', compact('quotations'));
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

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $quote->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', $intent === 'draft'
                    ? 'Paving quotation saved as draft and linked.'
                    : 'Paving quotation '.$quote->quote_number.' saved and linked to this project.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.paving.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? 'Paving quotation saved as draft.'
                : 'Paving quotation '.$quote->quote_number.' saved.');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
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

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $paving_quotation->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Paving quotation '.$paving_quotation->quote_number.' updated and linked.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.paving.edit', $paving_quotation)
            ->with('success', 'Paving quotation '.$paving_quotation->quote_number.' updated (v'.$paving_quotation->version.').');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    public function preview(Request $request)
    {
        $inputs = $this->gatherInputsFromRequest($request);
        $calc = (new PavingQuotationCalculator)->calculate($inputs);

        return response()->json($calc);
    }

    /**
     * Printable purchasing list (materials + rentals) for a saved quote.
     * Quantities come from the stored calculation breakdown.
     */
    public function purchaseList(PavingQuotation $paving_quotation)
    {
        $paving_quotation->loadMissing('project');

        $calc = $paving_quotation->calculation;
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
        ]);
    }

    public function linkToProject(Request $request, PavingQuotation $paving_quotation)
    {
        if ($paving_quotation->isLinked()) {
            return back()->with('error', 'This paving quotation is already linked to a project.');
        }
        if ($paving_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (save without draft) before linking.');
        }
        if ((float) $paving_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        if (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
            return back()->with('error', 'Cannot link to this project — it no longer accepts new quotations.');
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
        if ($paving_quotation->isLinked()) {
            return back()->with('error', 'This paving quotation is already linked to a project.');
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
        $projectTitle = trim((string) ($data['fp_project_name'] ?? ''));
        if ($projectTitle === '') {
            $projectTitle = $clientName.' — Paving';
        }
        $projectTitle = Str::limit($projectTitle, 255, '');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $paving_quotation, $lineDesc) {
            $project = ClientProject::create([
                'name' => $projectTitle,
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

        $statusService->onCreated($project, false);

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
        $materialRateGroups = PavingMaterialRate::groupedForAdmin();
        $pricingTiers = PavingPricingTier::query()->orderBy('area_sq_ft')->get();

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
            'materialRateGroups' => $materialRateGroups,
            'pricingTiers' => $pricingTiers,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
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
        ], CommuteExpenseService::commuteValidationRules()), [
            'client_name.required' => 'Client name is required to save a published quotation.',
        ]);

        $inputs = $this->gatherInputsFromRequest($request);
        $calc = (new PavingQuotationCalculator)->calculate($inputs);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);
        $workDescription = trim((string) $request->input('work_description'));

        $base = [
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription === '' ? null : $workDescription,
            'is_draft' => $intent === 'draft',
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ];

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'paving_quotations');

        return $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request): array
    {
        $this->normalizeCadMoneyStringsOnRequest($request);

        $data = $request->validate([
            'area_input_mode' => ['nullable', Rule::in(['total', 'dimensions'])],
            'area_length_ft' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'area_width_ft' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'total_area_sq_ft' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'paving_stone_type_id' => ['required', 'integer', 'exists:paving_stone_types,id'],
            'stone_sq_ft_per_stone' => ['nullable', 'numeric', 'min:0.0001', 'max:100'],
            'stone_price_per_stone' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'helper_hourly_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'owner_hourly_rate' => ['required', 'numeric', 'min:0', 'max:500'],
            'mini_skid_days' => ['required', 'integer', 'min:0', 'max:365'],
            'plate_compactor_days' => ['required', 'integer', 'min:0', 'max:365'],
            'profit_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'waste_factor' => ['required', 'numeric', 'min:0', 'max:100'],
            'excavation_depth_in' => ['required', 'numeric', 'min:1', 'max:48'],
            'hst_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'marble_stone_type_id' => ['nullable', 'integer', 'exists:paving_stone_types,id'],
            'marble_area_sq_ft' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'marble_sq_ft_per_stone' => ['nullable', 'numeric', 'min:0.0001', 'max:100'],
            'marble_price_per_stone' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'manual_price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

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

        $marbleOn = filter_var($data['marble_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($marbleOn && (float) ($data['marble_area_sq_ft'] ?? 0) > 0 && empty($data['marble_stone_type_id'])) {
            throw ValidationException::withMessages([
                'marble_stone_type_id' => 'Select a marble / accent stone type when marble area is greater than zero.',
            ]);
        }

        $areaMode = in_array($data['area_input_mode'] ?? 'total', ['total', 'dimensions'], true)
            ? ($data['area_input_mode'] ?? 'total')
            : 'total';

        if ($areaMode === 'dimensions') {
            $length = isset($data['area_length_ft']) ? (float) $data['area_length_ft'] : 0.0;
            $width = isset($data['area_width_ft']) ? (float) $data['area_width_ft'] : 0.0;
            if ($length <= 0 || $width <= 0) {
                throw ValidationException::withMessages([
                    'area_length_ft' => 'Enter length and width when using length × width mode.',
                ]);
            }
            $totalAreaSqFt = round($length * $width, 2);
        } else {
            if (! isset($data['total_area_sq_ft']) || (float) $data['total_area_sq_ft'] < 1) {
                throw ValidationException::withMessages([
                    'total_area_sq_ft' => 'Enter the total project area in square feet.',
                ]);
            }
            $totalAreaSqFt = (float) $data['total_area_sq_ft'];
            $length = null;
            $width = null;
        }

        if ($totalAreaSqFt < 1) {
            throw ValidationException::withMessages([
                'total_area_sq_ft' => 'Project area must be at least 1 sq ft.',
            ]);
        }

        $mainStone = PavingStoneType::query()->findOrFail($data['paving_stone_type_id']);

        $marbleStone = ! empty($data['marble_stone_type_id'])
            ? PavingStoneType::query()->find($data['marble_stone_type_id'])
            : null;

        return [
            'area_input_mode' => $areaMode,
            'area_length_ft' => $areaMode === 'dimensions' ? $length : null,
            'area_width_ft' => $areaMode === 'dimensions' ? $width : null,
            'total_area_sq_ft' => $totalAreaSqFt,
            'paving_stone_type_id' => (int) $mainStone->id,
            'stone_sq_ft_per_stone' => (float) $mainStone->sq_ft_per_stone,
            'stone_price_per_stone' => (float) $mainStone->price_per_stone,
            'helper_hourly_rate' => (float) $data['helper_hourly_rate'],
            'owner_hourly_rate' => (float) $data['owner_hourly_rate'],
            'mini_skid_days' => (int) $data['mini_skid_days'],
            'plate_compactor_days' => (int) $data['plate_compactor_days'],
            'profit_margin' => $profitMargin,
            'waste_factor' => $waste,
            'excavation_depth_in' => (float) $data['excavation_depth_in'],
            'hst_rate' => $hst,
            'add_polymeric_sand' => $request->boolean('add_polymeric_sand'),
            'include_old_surface_removal' => $request->boolean('include_old_surface_removal'),
            'include_geotextile' => $request->boolean('include_geotextile'),
            'marble_enabled' => $request->boolean('marble_enabled'),
            'marble_stone_type_id' => $data['marble_stone_type_id'] ?? null,
            'marble_area_sq_ft' => isset($data['marble_area_sq_ft']) ? (float) $data['marble_area_sq_ft'] : 0,
            'marble_sq_ft_per_stone' => isset($data['marble_sq_ft_per_stone']) && $data['marble_sq_ft_per_stone'] !== ''
                ? (float) $data['marble_sq_ft_per_stone']
                : ($marbleStone ? (float) $marbleStone->sq_ft_per_stone : null),
            'marble_price_per_stone' => isset($data['marble_price_per_stone']) && $data['marble_price_per_stone'] !== ''
                ? (float) $data['marble_price_per_stone']
                : ($marbleStone ? (float) $marbleStone->price_per_stone : null),
            'manual_price_override' => isset($data['manual_price_override']) && $data['manual_price_override'] !== ''
                ? (float) $data['manual_price_override']
                : null,
        ];
    }

    /**
     * Strip thousands separators from CAD calculator inputs before validation.
     * Preview uses fetch(FormData) without running submit handlers that strip commas.
     */
    protected function normalizeCadMoneyStringsOnRequest(Request $request): void
    {
        $moneyKeys = ['stone_price_per_stone', 'helper_hourly_rate', 'owner_hourly_rate', 'marble_price_per_stone', 'manual_price_override'];
        $optionalEmptyToNull = ['marble_price_per_stone', 'manual_price_override'];
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
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();

        $pavingQuotation->update([
            'client_project_id' => $project->id,
            'linked_at' => $pavingQuotation->linked_at ?? now(),
        ]);
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
        $exempt = round($final - $labour - $labourHst - $commute, 2);

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
            'amount' => round($labour + $commute, 2),
            'apply_hst' => true,
            'hst_taxable_amount' => $labour,
        ];

        return $items;
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

    /** @return array<string, string> Calculator row label => product URL */
    protected function purchaseListStoneLinks(PavingQuotation $quotation): array
    {
        $inputs = is_array($quotation->inputs) ? $quotation->inputs : [];
        $links = [];

        $mainId = $inputs['paving_stone_type_id'] ?? null;
        if ($mainId) {
            $stone = PavingStoneType::query()->find($mainId);
            if ($stone && filled($stone->product_url)) {
                $links['Main paving stones'] = $stone->product_url;
            }
        }

        $marbleId = $inputs['marble_stone_type_id'] ?? null;
        if ($marbleId) {
            $stone = PavingStoneType::query()->find($marbleId);
            if ($stone && filled($stone->product_url)) {
                $links['Marble / accent paving stones'] = $stone->product_url;
            }
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $stoneLinks
     * @return array{quantity_label: string, label: string, unit: string, note: string, product_url: string|null, with_tax_label: string}
     */
    protected function formatPurchaseListRow(array $row, array $stoneLinks): array
    {
        $label = (string) ($row['label'] ?? '');
        $qty = (float) ($row['qty'] ?? 0);
        $unit = (string) ($row['unit'] ?? '');
        $note = (string) ($row['note'] ?? '');

        $withTax = (float) ($row['with_tax'] ?? 0);
        if ($withTax <= 0 && isset($row['before_tax'])) {
            $withTax = round((float) $row['before_tax'] + (float) ($row['hst'] ?? 0), 2);
        }

        return [
            'quantity_label' => $this->formatPurchaseQuantityLabel($qty),
            'label' => $label,
            'unit' => $unit,
            'note' => $note !== '' && $note !== '—' ? $note : '',
            'product_url' => $stoneLinks[$label] ?? null,
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
