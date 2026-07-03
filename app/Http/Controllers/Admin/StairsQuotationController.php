<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StairsMaterialType;
use App\Models\StairsQuotation;
use App\Models\StairsSettings;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\StairsQuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StairsQuotationController extends Controller
{
    use HandlesQuotationCommuteExpense;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            StairsQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.stairs.index', [
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

        $quote = StairsQuotation::create(array_merge($calc, [
            'quote_number' => StairsQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkStairsIfRequested($quote->fresh(), $request, $warnings);

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $quote->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', $intent === 'draft'
                    ? 'Stairs quotation saved as draft and linked.'
                    : 'Stairs quotation '.$quote->quote_number.' saved and linked to this project.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.stairs.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? 'Stairs quotation saved as draft.'
                : 'Stairs quotation '.$quote->quote_number.' saved.');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    public function edit(Request $request, StairsQuotation $stairs_quotation)
    {
        return $this->formView($stairs_quotation, $request);
    }

    public function update(Request $request, StairsQuotation $stairs_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $stairs_quotation);

        $stairs_quotation->fill(array_merge($calc, [
            'version' => $stairs_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($stairs_quotation->isLinked() && $stairs_quotation->client_project_id) {
            $project = ClientProject::query()->find($stairs_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $stairs_quotation) {
                    $this->syncStairsLineToProjectQuotation($project, $stairs_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkStairsIfRequested($stairs_quotation->fresh(), $request, $warnings);

        if ($this->shouldReturnToProjectAfterStairsSave($request, $stairs_quotation->fresh())) {
            $r = redirect()
                ->route('admin.client-projects.show', $stairs_quotation->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Stairs quotation '.$stairs_quotation->quote_number.' updated (v'.$stairs_quotation->version.').');

            return $warnings === [] ? $r : $r->with('warning', implode(' ', $warnings));
        }

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $stairs_quotation->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Stairs quotation '.$stairs_quotation->quote_number.' updated and linked.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.stairs.edit', $stairs_quotation)
            ->with('success', 'Stairs quotation '.$stairs_quotation->quote_number.' updated (v'.$stairs_quotation->version.').');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    protected function shouldReturnToProjectAfterStairsSave(Request $request, StairsQuotation $quotation): bool
    {
        if (! $quotation->isLinked()) {
            return false;
        }

        return $request->input('return') === 'project'
            || $request->query('return') === 'project';
    }

    public function preview(Request $request)
    {
        $raw = $this->gatherInputsFromRequest($request, true);
        $inputsForCalc = $this->mergePartialStairsInputs($raw);
        $calc = (new StairsQuotationCalculator)->calculate($inputsForCalc);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, StairsQuotation $stairs_quotation)
    {
        if ($stairs_quotation->isLinked()) {
            return back()->with('error', 'This stairs quotation is already linked to a project.');
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($stairs_quotation, $project);

        if ($stairs_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $stairs_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
            return back()->with('error', 'Cannot link to this project — it no longer accepts new quotations.');
        }

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $stairs_quotation->summaryDescription();
        }

        DB::transaction(function () use ($project, $stairs_quotation, $lineDesc) {
            $this->appendStairsLineToProjectQuotation($project, $stairs_quotation, $lineDesc);
        });

        return redirect()
            ->route('admin.quotations.stairs.edit', $stairs_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    public function createProjectAndLink(
        Request $request,
        StairsQuotation $stairs_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($stairs_quotation->isLinked()) {
            return back()->with('error', 'This stairs quotation is already linked to a project.');
        }
        if ($stairs_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $stairs_quotation->final_quote_total <= 0) {
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
            $lineDesc = $stairs_quotation->summaryDescription();
        }

        $clientName = trim($data['fp_client_name']);
        $projectTitle = trim((string) ($data['fp_project_name'] ?? ''));
        if ($projectTitle === '') {
            $projectTitle = $clientName.' — Stairs';
        }
        $projectTitle = Str::limit($projectTitle, 255, '');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $stairs_quotation, $lineDesc) {
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

            $this->appendStairsLineToProjectQuotation($project, $stairs_quotation, $lineDesc);

            return $project;
        });

        $statusService->onCreated($project, false);
        app(\App\Services\ClientProjectContactService::class)->ensureProjectLinkedToDirectory($project->fresh());

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The stairs line was added to the Quotation tab.');
    }

    public function destroy(StairsQuotation $stairs_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STAIRS)
            ->where('source_id', $stairs_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $stairs_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.stairs.index')
            ->with('success', 'Stairs quotation deleted.');
    }

    protected function formView(?StairsQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();

        $materialTypes = StairsMaterialType::query()->orderBy('sort_order')->get();
        $settings = StairsSettings::current();

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.stairs.create', [
            'quotation' => $quotation,
            'materialTypes' => $materialTypes,
            'settings' => $settings,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
        ]);
    }

    /**
     * @return array{inputs: array, calculation: array, final_quote_total: float, client_name: ?string}
     */
    protected function validatedCalculation(Request $request, string $intent, ?StairsQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], CommuteExpenseService::commuteValidationRules()), [
            'client_name.required' => 'Client name is required to save a published quotation.',
        ]);

        $rawInputs = $this->gatherInputsFromRequest($request, $draft);
        if (! $draft) {
            $engine = new StairsQuotationCalculator;
            $hints = $engine->validationHints($rawInputs, null, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
            $inputsForCalc = $rawInputs;
        } else {
            $inputsForCalc = $this->mergePartialStairsInputs($rawInputs);
        }

        $calc = (new StairsQuotationCalculator)->calculate($inputsForCalc);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);

        $base = [
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'is_draft' => $draft,
            'inputs' => $rawInputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ];

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'stairs_quotations');

        return $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
    }

    /**
     * Fill gaps so live preview / draft totals can be computed without persisting defaults into stored inputs.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function mergePartialStairsInputs(array $inputs): array
    {
        $defaults = StairsQuotationCalculator::defaultInputs();
        foreach (['carpet_removal', 'requires_stain', 'requires_clear_coat'] as $boolKey) {
            if (array_key_exists($boolKey, $inputs)) {
                $defaults[$boolKey] = filter_var($inputs[$boolKey], FILTER_VALIDATE_BOOLEAN);
            }
        }
        if (array_key_exists('material_price', $inputs)) {
            $defaults['material_price'] = round(max(0, (float) $inputs['material_price']), 2);
        }
        foreach ($inputs as $key => $value) {
            if (in_array($key, ['carpet_removal', 'requires_stain', 'requires_clear_coat', 'material_price'], true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'segments' && is_array($value) && $value === []) {
                continue;
            }
            $defaults[$key] = $value;
        }
        if (empty($defaults['material_type_id'])) {
            $defaults['material_type_id'] = StairsMaterialType::query()->orderBy('sort_order')->value('id');
        }
        if (($defaults['total_steps'] ?? 0) < 1) {
            $defaults['total_steps'] = 1;
        }
        $segs = $defaults['segments'] ?? [];
        $sum = 0;
        if (is_array($segs)) {
            foreach ($segs as $seg) {
                if (is_array($seg)) {
                    $sum += max(0, (int) ($seg['qty'] ?? 0));
                }
            }
        }
        if (! is_array($segs) || $segs === [] || $sum < 1) {
            $ts = max(0, (int) $defaults['total_steps']);
            $defaults['segments'] = [['stair_type' => 'standard', 'qty' => max(1, $ts), 'open_sides' => StairsQuotationCalculator::OPEN_NONE]];
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeMoneyOnRequest(Request $request): void
    {
        if (! $request->exists('material_price')) {
            return;
        }
        $v = $request->input('material_price');
        if ($v === '' || $v === null) {
            $request->merge(['material_price' => '0']);

            return;
        }
        if (is_string($v)) {
            $request->merge(['material_price' => str_replace(',', '', $v)]);
        }
    }

    protected function gatherInputsFromRequest(Request $request, bool $draft = false): array
    {
        $this->normalizeMoneyOnRequest($request);

        $openSideRule = Rule::in([
            StairsQuotationCalculator::OPEN_NONE,
            StairsQuotationCalculator::OPEN_ONE,
            StairsQuotationCalculator::OPEN_TWO,
        ]);
        if ($draft) {
            $data = $request->validate([
                'total_steps' => ['nullable', 'integer', 'min:0', 'max:500'],
                'material_type_id' => ['nullable', 'integer', 'exists:stairs_material_types,id'],
                'nosing_choice' => ['nullable', 'string', 'max:64'],
                'segments' => ['nullable', 'array'],
                'segments.*.stair_type' => ['nullable', 'string', 'max:32'],
                'segments.*.qty' => ['nullable', 'integer', 'min:0', 'max:500'],
                'segments.*.open_sides' => ['nullable', 'string', $openSideRule],
                'material_price' => ['nullable', 'numeric', 'min:0'],
            ]);
        } else {
            $data = $request->validate([
                'total_steps' => ['required', 'integer', 'min:1', 'max:500'],
                'material_type_id' => ['required', 'integer', 'exists:stairs_material_types,id'],
                'nosing_choice' => ['nullable', 'string', 'max:64'],
                'segments' => ['nullable', 'array'],
                'segments.*.stair_type' => ['nullable', 'string', 'max:32'],
                'segments.*.qty' => ['nullable', 'integer', 'min:0', 'max:500'],
                'segments.*.open_sides' => ['nullable', 'string', $openSideRule],
                'material_price' => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        $segments = [];
        foreach ($data['segments'] ?? [] as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $os = (string) ($seg['open_sides'] ?? '');
            if (! in_array($os, [
                StairsQuotationCalculator::OPEN_NONE,
                StairsQuotationCalculator::OPEN_ONE,
                StairsQuotationCalculator::OPEN_TWO,
            ], true)) {
                $os = StairsQuotationCalculator::OPEN_NONE;
            }
            $segments[] = [
                'stair_type' => (string) ($seg['stair_type'] ?? 'standard'),
                'qty' => max(0, (int) ($seg['qty'] ?? 0)),
                'open_sides' => $os,
            ];
        }

        $materialId = isset($data['material_type_id']) ? (int) $data['material_type_id'] : null;
        $material = $materialId ? StairsMaterialType::query()->find($materialId) : null;
        foreach ($segments as $i => $seg) {
            $segments[$i]['open_sides'] = StairsQuotationCalculator::normalizeSegmentOpenSidesForMaterial($seg, $material);
        }

        $fallbackSegments = $draft
            ? $segments
            : ($segments === [] ? [['stair_type' => 'standard', 'qty' => max(1, (int) ($data['total_steps'] ?? 1)), 'open_sides' => StairsQuotationCalculator::OPEN_NONE]] : $segments);

        return [
            'total_steps' => max(0, (int) ($data['total_steps'] ?? 0)),
            'carpet_removal' => $request->boolean('carpet_removal'),
            'segments' => $fallbackSegments,
            'material_type_id' => isset($data['material_type_id']) ? (int) $data['material_type_id'] : null,
            'nosing_choice' => trim((string) ($data['nosing_choice'] ?? '')),
            'requires_stain' => $request->boolean('requires_stain'),
            'requires_clear_coat' => $request->boolean('requires_clear_coat'),
            'material_price' => round(max(0, (float) ($data['material_price'] ?? 0)), 2),
        ];
    }

    protected function autoLinkStairsIfRequested(StairsQuotation $sq, Request $request, array &$warnings): bool
    {
        if ($sq->isLinked() || $sq->is_draft) {
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

        if ((float) $sq->final_quote_total <= 0) {
            $warnings[] = 'Set a final quote total greater than zero before it can attach to the project.';

            return false;
        }

        try {
            $lineDesc = $sq->summaryDescription();
            DB::transaction(function () use ($project, $sq, $lineDesc) {
                $this->appendStairsLineToProjectQuotation($project, $sq->fresh(), $lineDesc);
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncStairsLineToProjectQuotation(ClientProject $project, StairsQuotation $stairsQuotation): void
    {
        $items = [[
            'description' => $stairsQuotation->summaryDescription(),
            'amount' => $stairsQuotation->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];
        $materialAmt = $stairsQuotation->materialPriceAmount();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Materials',
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }
        $this->appendCommuteProjectLine($items, $stairsQuotation);

        $snapshot = $stairsQuotation->only([
            'quote_number',
            'client_name',
            'inputs',
            'calculation',
            'final_quote_total',
            'version',
        ]);

        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_STAIRS,
            'source_id' => $stairsQuotation->id,
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
        $row->material = $stairsQuotation->includesMaterialCharge();
        $row->title = $stairsQuotation->displayTitle();
        $row->items = $items;
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendStairsLineToProjectQuotation(
        ClientProject $project,
        StairsQuotation $stairsQuotation,
        string $lineDescription
    ): void {
        $this->syncStairsLineToProjectQuotation($project, $stairsQuotation);

        $stairsQuotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
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
}
