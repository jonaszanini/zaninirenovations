<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\PaintingPaintLine;
use App\Models\PaintingQuotation;
use App\Models\PaintingSettings;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\PaintingQuotationCalculator;
use App\Services\QuotationPaymentSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaintingQuotationController extends Controller
{
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            PaintingQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.painting.index', [
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

        $quote = PaintingQuotation::create(array_merge($calc, [
            'quote_number' => PaintingQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkPaintingIfRequested($quote->fresh(), $request, $warnings);

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $quote->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', $intent === 'draft'
                    ? 'Painting quotation saved as draft and linked.'
                    : 'Painting quotation '.$quote->quote_number.' saved and linked to this project.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.painting.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? 'Painting quotation saved as draft.'
                : 'Painting quotation '.$quote->quote_number.' saved.');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    public function edit(Request $request, PaintingQuotation $painting_quotation)
    {
        return $this->formView($painting_quotation, $request);
    }

    public function update(Request $request, PaintingQuotation $painting_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $painting_quotation);

        $painting_quotation->fill(array_merge($calc, [
            'version' => $painting_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($painting_quotation->isLinked() && $painting_quotation->client_project_id) {
            $project = ClientProject::query()->find($painting_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $painting_quotation) {
                    $this->syncPaintingLineToProjectQuotation($project, $painting_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkPaintingIfRequested($painting_quotation->fresh(), $request, $warnings);

        if ($this->shouldReturnToProjectAfterPaintingSave($request, $painting_quotation->fresh())) {
            $r = redirect()
                ->route('admin.client-projects.show', $painting_quotation->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Painting quotation '.$painting_quotation->quote_number.' updated (v'.$painting_quotation->version.').');

            return $warnings === [] ? $r : $r->with('warning', implode(' ', $warnings));
        }

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $painting_quotation->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Painting quotation '.$painting_quotation->quote_number.' updated and linked.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.painting.edit', $painting_quotation)
            ->with('success', 'Painting quotation '.$painting_quotation->quote_number.' updated (v'.$painting_quotation->version.').');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    protected function shouldReturnToProjectAfterPaintingSave(Request $request, PaintingQuotation $quotation): bool
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
        $inputsForCalc = $this->mergePartialPaintingInputs($raw);
        $calc = (new PaintingQuotationCalculator)->calculate($inputsForCalc);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, PaintingQuotation $painting_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($painting_quotation, 'painting')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($painting_quotation, $project);

        if ($painting_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $painting_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $painting_quotation->summaryDescription();
        }

        DB::transaction(function () use ($project, $painting_quotation, $lineDesc) {
            $this->appendPaintingLineToProjectQuotation($project, $painting_quotation, $lineDesc);
        });

        return redirect()
            ->route('admin.quotations.painting.edit', $painting_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    public function createProjectAndLink(
        Request $request,
        PaintingQuotation $painting_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($painting_quotation, 'painting')) {
            return $resp;
        }
        if ($painting_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $painting_quotation->final_quote_total <= 0) {
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
            $lineDesc = $painting_quotation->summaryDescription();
        }

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Painting');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $painting_quotation, $lineDesc) {
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

            $this->appendPaintingLineToProjectQuotation($project, $painting_quotation, $lineDesc);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The painting line was added to the Quotation tab.');
    }

    public function destroy(PaintingQuotation $painting_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_PAINTING)
            ->where('source_id', $painting_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $painting_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.painting.index')
            ->with('success', 'Painting quotation deleted.');
    }

    protected function formView(?PaintingQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();

        $settings = PaintingSettings::current();
        $paintLines = PaintingPaintLine::query()->activeOrdered()->get();

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        $linkedCpq = null;
        if ($quotation) {
            $linkedCpq = ClientProjectQuotation::query()
                ->where('source_type', ClientProjectQuotation::SOURCE_PAINTING)
                ->where('source_id', $quotation->id)
                ->first();
        }

        return view('admin.quotations.painting.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'paintLines' => $paintLines,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $linkedCpq,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCalculation(Request $request, string $intent, ?PaintingQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:5000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
            ...QuotationPaymentSchedule::validationRules(),
        ], CommuteExpenseService::commuteValidationRules()), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages()));

        $rawInputs = $this->gatherInputsFromRequest($request, $draft);
        if (! $draft) {
            $engine = new PaintingQuotationCalculator;
            $hints = $engine->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
            $inputsForCalc = $rawInputs;
        } else {
            $inputsForCalc = $this->mergePartialPaintingInputs($rawInputs);
        }

        $calc = (new PaintingQuotationCalculator)->calculate($inputsForCalc);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);

        $workDescription = trim((string) $request->input('work_description'));

        $base = [
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription === '' ? null : $workDescription,
            'payment_installments' => (int) $request->input('payment_installments'),
            'is_draft' => $draft,
            'inputs' => $rawInputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ];

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'painting_quotations');

        return $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function mergePartialPaintingInputs(array $inputs): array
    {
        $defaults = PaintingQuotationCalculator::defaultInputs();
        foreach ($inputs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'excluded_areas' && is_array($value) && $value === []) {
                continue;
            }
            $defaults[$key] = $value;
        }

        if (empty($defaults['paint_line_id'])) {
            $defaults['paint_line_id'] = PaintingPaintLine::query()->activeOrdered()->value('id');
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request, bool $draft = false): array
    {
        $modeRule = Rule::in([
            PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS,
            PaintingQuotationCalculator::EXCLUDED_MODE_SQF,
        ]);

        if ($draft) {
            $data = $request->validate([
                'total_property_sqf' => ['nullable', 'numeric', 'min:0'],
                'paint_line_id' => ['nullable', 'integer', 'exists:painting_paint_lines,id'],
                'surface_prep_description' => ['nullable', 'string', 'max:5000'],
                'excluded_areas' => ['nullable', 'array'],
                'excluded_areas.*.name' => ['nullable', 'string', 'max:160'],
                'excluded_areas.*.mode' => ['nullable', 'string', $modeRule],
                'excluded_areas.*.length_ft' => ['nullable', 'numeric', 'min:0'],
                'excluded_areas.*.width_ft' => ['nullable', 'numeric', 'min:0'],
                'excluded_areas.*.sqf' => ['nullable', 'numeric', 'min:0'],
            ]);
        } else {
            $data = $request->validate([
                'total_property_sqf' => ['required', 'numeric', 'min:0.01'],
                'paint_line_id' => ['required', 'integer', 'exists:painting_paint_lines,id'],
                'surface_prep_description' => ['nullable', 'string', 'max:5000'],
                'excluded_areas' => ['nullable', 'array'],
                'excluded_areas.*.name' => ['nullable', 'string', 'max:160'],
                'excluded_areas.*.mode' => ['nullable', 'string', $modeRule],
                'excluded_areas.*.length_ft' => ['nullable', 'numeric', 'min:0'],
                'excluded_areas.*.width_ft' => ['nullable', 'numeric', 'min:0'],
                'excluded_areas.*.sqf' => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        $excluded = [];
        foreach ($data['excluded_areas'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $mode = (string) ($row['mode'] ?? PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS);
            if (! in_array($mode, [
                PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS,
                PaintingQuotationCalculator::EXCLUDED_MODE_SQF,
            ], true)) {
                $mode = PaintingQuotationCalculator::EXCLUDED_MODE_DIMENSIONS;
            }
            $excluded[] = [
                'name' => $name,
                'mode' => $mode,
                'length_ft' => round(max(0, (float) ($row['length_ft'] ?? 0)), 2),
                'width_ft' => round(max(0, (float) ($row['width_ft'] ?? 0)), 2),
                'sqf' => round(max(0, (float) ($row['sqf'] ?? 0)), 2),
            ];
        }

        $prep = trim((string) ($data['surface_prep_description'] ?? ''));
        if ($prep === '') {
            $prep = PaintingQuotationCalculator::defaultSurfacePrepDescription();
        }

        return [
            'total_property_sqf' => round(max(0, (float) ($data['total_property_sqf'] ?? 0)), 2),
            'excluded_areas' => $excluded,
            'paint_line_id' => isset($data['paint_line_id']) ? (int) $data['paint_line_id'] : null,
            'coats' => PaintingQuotationCalculator::DEFAULT_COATS,
            'surface_prep_description' => $prep,
        ];
    }

    protected function autoLinkPaintingIfRequested(PaintingQuotation $pq, Request $request, array &$warnings): bool
    {
        if ($pq->isLinked() || $pq->is_draft) {
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
                $this->appendPaintingLineToProjectQuotation($project, $pq->fresh(), $lineDesc);
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncPaintingLineToProjectQuotation(ClientProject $project, PaintingQuotation $paintingQuotation): void
    {
        $items = [[
            'description' => 'Painting service (labor)',
            'amount' => $paintingQuotation->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];
        $materialAmt = $paintingQuotation->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $paint = $paintingQuotation->paintLine();
            $label = $paint ? 'Materials — '.$paint->name : 'Materials';
            $items[] = [
                'description' => $label,
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }
        $this->appendCommuteProjectLine($items, $paintingQuotation);

        $snapshot = $paintingQuotation->only([
            'quote_number',
            'client_name',
            'inputs',
            'calculation',
            'final_quote_total',
            'work_description',
            'version',
        ]);

        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_PAINTING,
            'source_id' => $paintingQuotation->id,
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
        $row->material = $paintingQuotation->includesMaterialCharge();
        $row->title = $paintingQuotation->displayTitle();
        $row->items = $items;
        $row->work_description = $paintingQuotation->scopeDescriptionForPdf();
        $this->applyPaymentScheduleToProjectQuotation($row, $paintingQuotation);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendPaintingLineToProjectQuotation(
        ClientProject $project,
        PaintingQuotation $paintingQuotation,
        string $lineDescription
    ): void {
        $this->syncPaintingLineToProjectQuotation($project, $paintingQuotation);

        $paintingQuotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

}
