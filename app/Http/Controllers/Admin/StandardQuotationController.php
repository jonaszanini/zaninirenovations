<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Services\StandardQuotationCalculator;
use App\Support\MaterialCatalogPickerOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Standalone Standard quotation (materials table + labour).
 */
class StandardQuotationController extends Controller
{
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            StandardQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.standard.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    public function create(Request $request)
    {
        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.standard.create', [
            'quotation' => null,
            'linkableProjects' => collect(),
            'linkProject' => $linkProject,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    public function store(Request $request)
    {
        $calc = $this->validatedSnapshot($request, new StandardQuotationCalculator());

        $quote = StandardQuotation::create(array_merge($calc['persistence'], [
            'quote_number' => StandardQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkStandardIfRequested($quote, $request, $warnings);

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $quote->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Standard quotation '.$quote->quote_number.' saved and linked to this project.')
                ->with('standard_flash', 'linked')
                ->with('standard_linked_project_id', $quote->client_project_id);

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.standard.edit', $quote)
            ->with('success', 'Standard quotation '.$quote->quote_number.' saved.')
            ->with('standard_flash', 'saved');

        if ($warnings !== []) {
            $resp = $resp->with('warning', implode(' ', $warnings));
        }

        if ($quote->fresh()->isLinked() && $quote->client_project_id) {
            $resp = $resp
                ->with('standard_flash', 'linked')
                ->with('standard_linked_project_id', $quote->client_project_id);
        }

        return $resp;
    }

    public function edit(Request $request, StandardQuotation $standard_quotation)
    {
        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.standard.create', [
            'quotation' => $standard_quotation,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    public function update(Request $request, StandardQuotation $standard_quotation)
    {
        $calc = $this->validatedSnapshot($request, new StandardQuotationCalculator(), $standard_quotation);

        $standard_quotation->fill(array_merge($calc['persistence'], [
            'version' => $standard_quotation->version + 1,
        ]))->save();

        $warnings = [];
        $this->autoLinkStandardIfRequested($standard_quotation->fresh(), $request, $warnings);

        $standard_quotation->refresh();

        // If this standard quotation is linked to a project, re-sync the
        // project quotation's line items so the project's Quotation tab /
        // PDF reflect the latest amounts.
        if ($standard_quotation->isLinked() && $standard_quotation->project) {
            DB::transaction(function () use ($standard_quotation) {
                $this->syncStandardLinesIntoProjectQuotation(
                    $standard_quotation->project,
                    $standard_quotation
                );
            });
        }

        // Honour ?return=project / return=project — sent by the project's
        // "Edit quotation" entry point so the user lands back on the
        // project's Quotation tab (not the standalone editor).
        if ($this->shouldReturnToProject($request, $standard_quotation)) {
            $r = redirect()
                ->route('admin.client-projects.show', $standard_quotation->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Standard quotation '.$standard_quotation->quote_number.' updated (v'.$standard_quotation->version.').');
            if ($warnings !== []) {
                $r = $r->with('warning', implode(' ', $warnings));
            }

            return $r;
        }

        $resp = redirect()
            ->route('admin.quotations.standard.edit', $standard_quotation)
            ->with('success', 'Standard quotation '.$standard_quotation->quote_number.' updated (v'.$standard_quotation->version.').')
            ->with('standard_flash', 'saved');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    /**
     * Whether the editor should redirect back to the project after save.
     * True when the form was opened from the project's "Edit quotation"
     * entry (which appends ?return=project to the URL).
     */
    protected function shouldReturnToProject(Request $request, StandardQuotation $sq): bool
    {
        if (!$sq->isLinked()) {
            return false;
        }

        return $request->input('return') === 'project'
            || $request->query('return') === 'project';
    }

    public function linkToProject(Request $request, StandardQuotation $standard_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($standard_quotation, 'standard')) {
            return $resp;
        }

        if ((float) $standard_quotation->grand_total <= 0) {
            return back()->with('error', 'Set a grand total greater than zero before linking.');
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($standard_quotation, $project);

        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $standard_quotation->pdfSummaryLineDescription();
        }

        DB::transaction(function () use ($project, $standard_quotation, $lineDesc) {
            $this->appendStandardLineToProjectQuotation($project, $standard_quotation, $lineDesc);
        });

        return redirect()
            ->route('admin.quotations.standard.edit', $standard_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.')
            ->with('standard_flash', 'linked')
            ->with('standard_linked_project_id', $project->id);
    }

    public function createProjectAndLink(
        Request $request,
        StandardQuotation $standard_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($standard_quotation, 'standard')) {
            return $resp;
        }

        if ((float) $standard_quotation->grand_total <= 0) {
            return back()->with('error', 'Set a grand total greater than zero before creating a project.');
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
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits (e.g. (123) 456-7890).',
            'fp_client_email.email' => 'Please enter a valid email address.',
        ]);

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = $standard_quotation->pdfSummaryLineDescription();
        }

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Standard');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $standard_quotation, $lineDesc) {
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

            $this->appendStandardLineToProjectQuotation($project, $standard_quotation, $lineDesc);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created (fixed price). The standard quotation line was added to the Quotation tab.');
    }

    public function destroy(StandardQuotation $standard_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STANDARD)
            ->where('source_id', $standard_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $standard_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.standard.index')
            ->with('success', 'Standard quotation deleted.');
    }

    /**
     * When POST/query carries `link_client_project_id` / `project`, attaches
     * this SQ once grand total allows. Returns whether a NEW link succeeded.
     */
    protected function autoLinkStandardIfRequested(StandardQuotation $sq, Request $request, array &$warnings): bool
    {
        if ($sq->isLinked()) {
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

        if ((float) $sq->grand_total <= 0) {
            $warnings[] = 'Set a grand total greater than zero before it can attach to this project.';

            return false;
        }

        try {
            DB::transaction(function () use ($project, $sq) {
                $this->appendStandardLineToProjectQuotation($project, $sq, $sq->pdfSummaryLineDescription());
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    /**
     * @return array{persistence: array<string, mixed>}
     */
    protected function validatedSnapshot(
        Request $request,
        StandardQuotationCalculator $calculator,
        ?StandardQuotation $existing = null
    ): array {
        $request->merge([
            'display_title' => trim((string) $request->input('display_title', '')),
        ]);

        $data = $request->validate(array_merge([
            'client_name' => ['required', 'string', 'max:160'],
            'display_title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in([
                ClientProjectQuotation::TYPE_QUOTATION,
                ClientProjectQuotation::TYPE_ESTIMATE,
            ])],
            'pdf_detail_mode' => ['required', Rule::in([
                StandardQuotation::PDF_DETAIL_SUMMARY,
                StandardQuotation::PDF_DETAIL_DETAILED,
            ])],
            'work_description' => ['nullable', 'string', 'max:5000'],
            'materials' => ['nullable', 'array'],
            'materials.*.short_name' => ['nullable', 'string', 'max:60'],
            'materials.*.description' => ['nullable', 'string', 'max:500'],
            'materials.*.url' => ['nullable', 'string', 'max:2000'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.apply_hst' => ['nullable'],
            'materials.*.hst_includes_unit' => ['nullable'],
            'materials.*.catalog_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'includes_material' => ['nullable'],
            'includes_labour' => ['nullable'],
            'labour_use_fixed' => ['nullable'],
            'labour_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'labour_fixed_hst_included' => ['nullable'],
            'labour_hourly_rate' => [
                Rule::requiredIf(fn () => ! $request->boolean('labour_use_fixed')),
                'nullable',
                'numeric',
                'min:0',
            ],
            'labour_mode' => [
                Rule::requiredIf(fn () => ! $request->boolean('labour_use_fixed')),
                'nullable',
                Rule::in(['hours', 'days']),
            ],
            'labour_quantity' => [
                Rule::requiredIf(fn () => ! $request->boolean('labour_use_fixed')),
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
            ],
            'contingency_enabled' => ['nullable'],
            'contingency_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules()), array_merge([
            'client_name.required' => 'Client name is required.',
            'display_title.required' => 'Enter a title — it appears at the top of the client PDF.',
            'document_type.required' => 'Pick whether this is a quotation or an estimate.',
            'labour_hourly_rate.required' => 'Enter an hourly rate, or enable flat labour fee.',
            'labour_mode.required' => 'Pick hours or days, or enable flat labour fee.',
            'labour_quantity.required' => 'Enter estimated hours/days, or enable flat labour fee.',
            'contingency_percent.numeric' => 'Contingency percent must be a number.',
            'contingency_percent.max' => 'Contingency percent cannot exceed 100.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages()));

        $includesMaterial = $request->boolean('includes_material');
        $includesLabour = $request->boolean('includes_labour');

        if (! $includesMaterial && ! $includesLabour) {
            throw ValidationException::withMessages([
                'includes_material' => 'Select Material and/or Labour for the PDF intro.',
            ]);
        }

        $materialRows = $data['materials'] ?? [];
        if (! is_array($materialRows)) {
            $materialRows = [];
        }
        foreach ($materialRows as &$mRow) {
            $inc = filter_var($mRow['hst_includes_unit'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $mRow['hst_includes_unit'] = $inc;
            $mRow['apply_hst'] = $inc ? true : filter_var($mRow['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }
        unset($mRow);

        $contingencyEnabled = filter_var($data['contingency_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contingencyPercent = $data['contingency_percent'] ?? null;
        if ($contingencyPercent === null || $contingencyPercent === '') {
            $contingencyPercent = 10.0;
        }

        $labourUseFixed = $request->boolean('labour_use_fixed');
        $labourFixedHstInc = $request->boolean('labour_fixed_hst_included');
        $labourFixedAmt = round((float) ($data['labour_fixed_amount'] ?? 0), 2);

        $computed = $calculator->compute(
            $materialRows,
            (float) ($data['labour_hourly_rate'] ?? 0),
            (string) ($data['labour_mode'] ?? 'hours'),
            (float) ($data['labour_quantity'] ?? 0),
            $contingencyEnabled,
            (float) $contingencyPercent,
            $labourUseFixed,
            $labourFixedAmt,
            $labourFixedHstInc
        );

        $materialsForStorage = array_map(static function (array $m): array {
            $url = trim((string) ($m['url'] ?? ''));

            $catalogId = isset($m['catalog_material_id']) && $m['catalog_material_id'] !== ''
                ? (int) $m['catalog_material_id']
                : null;

            return [
                'short_name' => $m['short_name'] ?? '',
                'description' => $m['description'],
                'url' => $url === '' ? null : $url,
                'unit_price' => $m['unit_price'],
                'quantity' => $m['quantity'],
                'apply_hst' => $m['apply_hst'],
                'hst_includes_unit' => (bool) ($m['hst_includes_unit'] ?? false),
                'catalog_material_id' => $catalogId > 0 ? $catalogId : null,
            ];
        }, $computed['materials']);

        $workDescription = trim((string) ($data['work_description'] ?? ''));

        $persistence = [
            'persistence' => [
                'client_name' => trim($data['client_name']),
                'display_title' => \Illuminate\Support\Str::limit($data['display_title'], 255, ''),
                'document_type' => $data['document_type'],
                'includes_material' => $includesMaterial,
                'includes_labour' => $includesLabour,
                'pdf_detail_mode' => $data['pdf_detail_mode'],
                'work_description' => $workDescription === '' ? null : $workDescription,
                'payment_installments' => (int) $data['payment_installments'],
                'materials' => $materialsForStorage,
                'labour_mode' => $data['labour_mode'] ?? 'hours',
                'labour_quantity' => round((float) ($data['labour_quantity'] ?? 0), 2),
                'labour_hours' => $computed['labour_hours'],
                'labour_hourly_rate' => round((float) ($data['labour_hourly_rate'] ?? 0), 2),
                'labour_use_fixed' => $labourUseFixed,
                'labour_fixed_amount' => $labourUseFixed ? $labourFixedAmt : null,
                'labour_fixed_hst_included' => $labourUseFixed && $labourFixedHstInc,
                'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
                'materials_hst' => $computed['materials_hst'],
                'materials_with_hst' => $computed['materials_with_hst'],
                'labour_total' => $computed['labour_total'],
                'labour_hst' => $computed['labour_hst'],
                'grand_total' => $computed['grand_total'],
                'contingency_enabled' => $computed['contingency_enabled'],
                'contingency_percent' => $computed['contingency_percent'],
                'contingency_amount' => $computed['contingency_amount'],
                'contingency_hst' => $computed['contingency_hst'],
            ],
        ];

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'standard_quotations');
        $persistence['persistence'] = $this->mergeCommuteIntoPersistence(
            $persistence['persistence'],
            $commute,
            'grand_total'
        );
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'standard_quotations');
        $persistence['persistence'] = $this->mergeDebrisRemovalIntoPersistence(
            $persistence['persistence'],
            $debris,
            'grand_total'
        );

        return $persistence;
    }

    /**
     * Initial link: mark the SQ as belonging to the project, then sync
     * its line(s) into the project quotation. Used by the
     * "Link to project" / "Create project & link" flows.
     */
    protected function appendStandardLineToProjectQuotation(
        ClientProject $project,
        StandardQuotation $standardQuotation,
        string $lineDescription
    ): void {
        $standardQuotation->client_project_id = $project->id;
        $standardQuotation->linked_at = now();
        foreach ($this->quotationLinkClientNameAttributes($project) as $key => $value) {
            $standardQuotation->{$key} = $value;
        }
        $standardQuotation->save();

        $this->syncStandardLinesIntoProjectQuotation(
            $project,
            $standardQuotation,
            $lineDescription
        );
    }

    /**
     * Sync one {@see ClientProjectQuotation} row (per standard quotation
     * id) so its line items mirror the current state of $standardQuotation.
     */
    protected function syncStandardLinesIntoProjectQuotation(
        ClientProject $project,
        StandardQuotation $standardQuotation,
        ?string $lineDescription = null
    ): void {
        $lineDescription = $lineDescription !== null
            ? trim($lineDescription)
            : '';
        if ($lineDescription === '') {
            $lineDescription = $standardQuotation->pdfSummaryLineDescription();
        }

        $taxablePreHst = $this->preHstTaxableForLink($standardQuotation);
        $exemptPreHst = $this->preHstExemptForLink($standardQuotation);

        $sqLines = [];
        if ($exemptPreHst > 0 && $taxablePreHst > 0) {
            $sqLines[] = [
                'description' => $lineDescription.' — HST applicable',
                'amount' => $taxablePreHst,
                'apply_hst' => true,
                'source_standard_quotation_id' => $standardQuotation->id,
            ];
            $sqLines[] = [
                'description' => $lineDescription.' — HST exempt',
                'amount' => $exemptPreHst,
                'apply_hst' => false,
                'source_standard_quotation_id' => $standardQuotation->id,
            ];
        } elseif ($exemptPreHst > 0) {
            $sqLines[] = [
                'description' => $lineDescription,
                'amount' => $exemptPreHst,
                'apply_hst' => false,
                'source_standard_quotation_id' => $standardQuotation->id,
            ];
        } else {
            $sqLines[] = [
                'description' => $lineDescription,
                'amount' => $taxablePreHst,
                'apply_hst' => true,
                'source_standard_quotation_id' => $standardQuotation->id,
            ];
        }

        $this->appendCommuteProjectLine($sqLines, $standardQuotation);
        $this->appendDebrisRemovalProjectLine($sqLines, $standardQuotation);

        /** @var ClientProjectQuotation $row */
        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $standardQuotation->id,
        ]);

        $docType = $standardQuotation->document_type;
        if ($docType !== ClientProjectQuotation::TYPE_QUOTATION
            && $docType !== ClientProjectQuotation::TYPE_ESTIMATE) {
            $docType = ClientProjectQuotation::TYPE_QUOTATION;
        }

        $includeLabour = (bool) $standardQuotation->includes_labour;
        $includeMaterial = (bool) $standardQuotation->includes_material;

        if (! $row->exists) {
            $row->fill([
                'title' => $lineDescription,
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'type' => $docType,
                'labour' => $includeLabour,
                'material' => $includeMaterial,
                'quotation_number' => ClientProjectQuotation::generateNumber($docType),
                'version' => 1,
            ]);
        } else {
            $row->labour = $includeLabour;
            $row->material = $includeMaterial;

            if ($row->type !== $docType) {
                $row->quotation_number = ClientProjectQuotation::swapNumberPrefixForType(
                    $row->quotation_number,
                    $docType
                );
                $row->type = $docType;
            }
        }

        $row->items = $sqLines;
        $row->work_description = $standardQuotation->work_description;
        $this->applyPaymentScheduleToProjectQuotation($row, $standardQuotation);
        $row->source_snapshot = $standardQuotation->only($standardQuotation->getFillable());
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    /**
     * Pre-HST amount (materials taxable subtotal + taxable share of
     * contingency + labour total) that should be linked into the project
     * quotation as an HST-applicable line.
     */
    protected function preHstTaxableForLink(StandardQuotation $sq): float
    {
        [$taxableSub, $exemptSub] = $this->materialsTaxableExemptSplit($sq);
        $contingencyTaxable = $this->contingencyTaxableShare($sq, $taxableSub, $exemptSub);

        return round($taxableSub + $contingencyTaxable + (float) $sq->labour_total, 2);
    }

    /**
     * Pre-HST amount of HST-exempt material rows (and their share of
     * contingency, if any). Linked as a separate exempt line item so the
     * project quotation does not add HST to it.
     */
    protected function preHstExemptForLink(StandardQuotation $sq): float
    {
        [$taxableSub, $exemptSub] = $this->materialsTaxableExemptSplit($sq);
        $contingencyExempt = (float) $sq->contingency_amount
            - $this->contingencyTaxableShare($sq, $taxableSub, $exemptSub);

        return round($exemptSub + max(0.0, $contingencyExempt), 2);
    }

    /**
     * @return array{0: float, 1: float} [taxableSubtotal, exemptSubtotal]
     */
    protected function materialsTaxableExemptSplit(StandardQuotation $sq): array
    {
        $taxable = 0.0;
        $exempt = 0.0;
        $materials = is_array($sq->materials) ? $sq->materials : [];

        foreach ($materials as $m) {
            $b = StandardQuotationCalculator::breakdownMaterialRow($m);
            if ($b === null) {
                continue;
            }
            $taxable += $b['taxable_pre'];
            $exempt += $b['exempt_pre'];
        }

        return [round($taxable, 2), round($exempt, 2)];
    }

    protected function contingencyTaxableShare(
        StandardQuotation $sq,
        float $taxableSub,
        float $exemptSub
    ): float {
        $contingency = (float) $sq->contingency_amount;
        if ($contingency <= 0) {
            return 0.0;
        }
        $matSub = $taxableSub + $exemptSub;
        if ($matSub <= 0) {
            return 0.0;
        }

        return round($contingency * ($taxableSub / $matSub), 2);
    }

}
