<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\Material;
use App\Models\StandardQuotation;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Services\StandardQuotationCalculator;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\PurchaseListPrintMode;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationBuildingLabor;
use App\Support\StandardQuotationValidationMessages;
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
            'linkedCpq' => null,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    public function store(Request $request)
    {
        $calc = $this->validatedSnapshot($request, new StandardQuotationCalculator());

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $quote = StandardQuotation::create(array_merge($calc['persistence'], [
            'quote_number' => StandardQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkStandardIfRequested($quote, $request, $warnings);

        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.standard.edit', $quote)
            ->with('success', $linkedNow
                ? 'Standard quotation '.$quote->quote_number.' saved and linked to this project.'
                : 'Standard quotation '.$quote->quote_number.' saved.')
            ->with('standard_flash', $linkedNow ? 'linked' : 'saved');

        if ($warnings !== []) {
            $resp = $resp->with('warning', implode(' ', $warnings));
        }

        if ($quote->fresh()->isLinked() && $quote->client_project_id) {
            $resp = $resp
                ->with('standard_flash', 'linked')
                ->with('standard_linked_project_id', $quote->client_project_id);
        }

        return QuotationAfterSaveRedirect::apply($request, $resp);
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
            'linkedCpq' => ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_STANDARD,
                (int) $standard_quotation->id
            ),
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    /** Printable shopping list from the last saved materials rows. */
    public function purchaseList(Request $request, StandardQuotation $standard_quotation)
    {
        $standard_quotation->load('project');

        $materials = $standard_quotation->flattenedMaterials();
        $catalogIds = [];
        foreach ($materials as $row) {
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0) {
                $catalogIds[$id] = $id;
            }
        }
        $catalogById = $catalogIds === []
            ? collect()
            : Material::query()->whereIn('id', array_values($catalogIds))->get()->keyBy('id');

        $rows = [];
        foreach ($materials as $row) {
            if (StandardQuotation::isElectricalMaterialRow($row)) {
                continue;
            }

            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $shortName = trim((string) ($row['short_name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            /** @var Material|null $catalog */
            $catalog = $catalogId > 0 ? $catalogById->get($catalogId) : null;

            $name = $description !== '' ? $description : $shortName;
            if ($name === '' && $catalog) {
                $name = $catalog->displayName();
            }

            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            if ($name === '' && $unitPrice == 0.0 && ! $catalog) {
                continue;
            }
            if ($name === '') {
                $name = 'Material';
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '' && $catalog) {
                $url = (string) ($catalog->storeProductUrl() ?? '');
            }

            $sku = $catalog ? trim((string) ($catalog->store_sku ?? '')) : '';
            $unit = $catalog ? trim((string) ($catalog->unit ?? '')) : '';
            if ($unit === '' || $unit === 'each') {
                $unit = 'ea';
            }

            $rows[] = [
                'name_html' => $this->purchaseListCellHtml($name),
                'short_name_html' => ($shortName !== '' && $shortName !== $name)
                    ? $this->purchaseListCellHtml($shortName)
                    : null,
                'store_sku_html' => $sku !== '' ? $this->purchaseListCellHtml($sku) : null,
                'url' => $url !== '' ? $url : null,
                'quantity' => $qty,
                'unit_html' => $this->purchaseListCellHtml($unit),
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $qty, 2),
            ];
        }

        return view('admin.quotations.standard.purchase_list', [
            'quotation' => $standard_quotation,
            'rows' => $rows,
            'showPrices' => PurchaseListPrintMode::showsPrices($request->query('prices')),
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

        $resp = redirect()
            ->route('admin.quotations.standard.edit', $standard_quotation)
            ->with('success', 'Standard quotation '.$standard_quotation->quote_number.' updated (v'.$standard_quotation->version.').')
            ->with('standard_flash', 'saved');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
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

        $materialRowRules = [
            'short_name' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'apply_hst' => ['nullable'],
            'hst_includes_unit' => ['nullable'],
            'catalog_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'delivery_cad' => ['nullable', 'numeric', 'min:0'],
            'entry_mode' => ['nullable', 'string', Rule::in(['manual', 'catalog', 'electrical'])],
            'line_kind' => ['nullable', 'string', Rule::in([
                StandardQuotation::LINE_KIND_MATERIAL,
                StandardQuotation::LINE_KIND_ELECTRICAL,
            ])],
        ];

        $data = $request->validate(array_merge([
            'client_name' => ['required', 'string', 'max:160'],
            'display_title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in([
                ClientProjectQuotation::TYPE_QUOTATION,
                ClientProjectQuotation::TYPE_ESTIMATE,
            ])],
            'pdf_detail_mode' => ['required', Rule::in(StandardQuotation::pdfDetailModes())],
            'work_description' => ['nullable', 'string', 'max:20000'],
            'works' => ['required', 'array', 'min:1'],
            'works.*.title' => ['nullable', 'string', 'max:160'],
            'works.*.materials' => ['nullable', 'array'],
            'works.*.materials.*.short_name' => $materialRowRules['short_name'],
            'works.*.materials.*.description' => $materialRowRules['description'],
            'works.*.materials.*.url' => $materialRowRules['url'],
            'works.*.materials.*.image_url' => $materialRowRules['image_url'],
            'works.*.materials.*.unit_price' => $materialRowRules['unit_price'],
            'works.*.materials.*.quantity' => $materialRowRules['quantity'],
            'works.*.materials.*.apply_hst' => $materialRowRules['apply_hst'],
            'works.*.materials.*.hst_includes_unit' => $materialRowRules['hst_includes_unit'],
            'works.*.materials.*.catalog_material_id' => $materialRowRules['catalog_material_id'],
            'works.*.materials.*.delivery_cad' => $materialRowRules['delivery_cad'],
            'works.*.materials.*.entry_mode' => $materialRowRules['entry_mode'],
            'works.*.materials.*.line_kind' => $materialRowRules['line_kind'],
            'works.*.labour_use_fixed' => ['nullable'],
            'works.*.labour_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'works.*.labour_fixed_hst_included' => ['nullable'],
            'works.*.labour_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'works.*.labour_mode' => ['nullable', Rule::in(['hours', 'days'])],
            'works.*.labour_quantity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'includes_material' => ['nullable'],
            'includes_labour' => ['nullable'],
            'include_labour_hst' => ['nullable', 'boolean'],
            'contingency_enabled' => ['nullable'],
            'contingency_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), QuotationBuildingLabor::validationRules()), array_merge([
            'client_name.required' => 'Client name is required.',
            'client_name.max' => 'Client name is too long. Keep it to 160 characters or fewer.',
            'display_title.required' => 'Enter a title — it appears at the top of the client PDF.',
            'display_title.max' => 'Title is too long. Keep it to 255 characters or fewer.',
            'document_type.required' => 'Pick whether this is a quotation or an estimate.',
            'work_description.max' => 'Description of the work is too long. Shorten that box and save again.',
            'works.required' => 'Add at least one job.',
            'works.min' => 'Add at least one job.',
            'contingency_percent.numeric' => 'Contingency percent must be a number.',
            'contingency_percent.max' => 'Contingency percent cannot exceed 100.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages(), QuotationBuildingLabor::validationMessages(), StandardQuotationValidationMessages::forWorks($request->input('works'))));

        $includesMaterial = $request->boolean('includes_material');
        $includesLabour = $request->boolean('includes_labour');

        if (! $includesMaterial && ! $includesLabour) {
            throw ValidationException::withMessages([
                'includes_material' => 'Select Material and/or Labour for the PDF intro.',
            ]);
        }

        $worksPosted = is_array($data['works'] ?? null) ? array_values($data['works']) : [];
        $workErrors = [];
        $multiJob = count($worksPosted) >= 2;

        foreach ($worksPosted as $idx => $work) {
            if (! is_array($work)) {
                continue;
            }
            if ($multiJob && trim((string) ($work['title'] ?? '')) === '') {
                $workErrors["works.$idx.title"] = 'Enter a name for this job.';
            }
            $useFixed = filter_var($work['labour_use_fixed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $useFixed) {
                $rate = $work['labour_hourly_rate'] ?? null;
                if ($rate === null || $rate === '') {
                    $workErrors["works.$idx.labour_hourly_rate"] = 'Enter an hourly rate, or enable flat labour fee.';
                }
                $mode = $work['labour_mode'] ?? null;
                if ($mode === null || $mode === '') {
                    $workErrors["works.$idx.labour_mode"] = 'Pick hours or days, or enable flat labour fee.';
                }
                $qty = $work['labour_quantity'] ?? null;
                if ($qty === null || $qty === '') {
                    $workErrors["works.$idx.labour_quantity"] = 'Enter estimated hours/days, or enable flat labour fee.';
                }
            }
        }
        if ($workErrors !== []) {
            throw ValidationException::withMessages($workErrors);
        }

        $worksForCompute = [];
        foreach ($worksPosted as $work) {
            if (! is_array($work)) {
                continue;
            }
            $materialRows = is_array($work['materials'] ?? null) ? $work['materials'] : [];
            foreach ($materialRows as &$mRow) {
                if (! is_array($mRow)) {
                    continue;
                }
                $inc = filter_var($mRow['hst_includes_unit'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $mRow['hst_includes_unit'] = $inc;
                $mRow['apply_hst'] = $inc ? true : filter_var($mRow['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN);
            }
            unset($mRow);

            $worksForCompute[] = [
                'title' => trim((string) ($work['title'] ?? '')),
                'materials' => $materialRows,
                'labour_use_fixed' => filter_var($work['labour_use_fixed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'labour_fixed_amount' => round((float) ($work['labour_fixed_amount'] ?? 0), 2),
                'labour_fixed_hst_included' => filter_var($work['labour_fixed_hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'labour_hourly_rate' => (float) ($work['labour_hourly_rate'] ?? 0),
                'labour_mode' => (string) ($work['labour_mode'] ?? 'hours'),
                'labour_quantity' => (float) ($work['labour_quantity'] ?? 0),
            ];
        }

        $contingencyEnabled = filter_var($data['contingency_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contingencyPercent = $data['contingency_percent'] ?? null;
        if ($contingencyPercent === null || $contingencyPercent === '') {
            $contingencyPercent = 10.0;
        }
        $includeLabourHst = $request->has('include_labour_hst')
            ? $request->boolean('include_labour_hst')
            : true;
        $building = QuotationBuildingLabor::fromRequest($request);

        $computed = $calculator->computeWorks(
            $worksForCompute,
            $contingencyEnabled,
            (float) $contingencyPercent,
            $includeLabourHst,
            $building['include_building'],
            $building['building_labor_percent'],
            // Remainder-to-labour-jobs is v3-only; saved v2 keeps last-job leftover.
            $existing ? $existing->usesCurrentPdfFold() : true
        );

        $materialMapper = static function (array $m): array {
            $lineKind = StandardQuotation::normalizeMaterialLineKind($m);
            $isElectrical = $lineKind === StandardQuotation::LINE_KIND_ELECTRICAL;
            $url = $isElectrical ? '' : trim((string) ($m['url'] ?? ''));
            $imageUrl = $isElectrical ? '' : trim((string) ($m['image_url'] ?? ''));

            $catalogId = ! $isElectrical && isset($m['catalog_material_id']) && $m['catalog_material_id'] !== ''
                ? (int) $m['catalog_material_id']
                : null;

            $description = trim((string) ($m['description'] ?? ''));
            if ($isElectrical && $description === '') {
                $description = StandardQuotation::ELECTRICAL_LINE_DESCRIPTION;
            }

            $entryMode = strtolower(trim((string) ($m['entry_mode'] ?? '')));
            if ($isElectrical) {
                $entryMode = 'electrical';
            } elseif ($catalogId > 0 || $entryMode === 'catalog') {
                $entryMode = 'catalog';
            } else {
                $entryMode = 'manual';
            }

            $qty = (float) ($m['quantity'] ?? 0);
            if ($isElectrical && $qty <= 0) {
                $qty = 1.0;
            }

            return [
                'short_name' => $isElectrical ? '' : ($m['short_name'] ?? ''),
                'description' => $description,
                'url' => $url === '' ? null : $url,
                'image_url' => $imageUrl === '' ? null : $imageUrl,
                'unit_price' => $m['unit_price'],
                'quantity' => $qty,
                'apply_hst' => $m['apply_hst'],
                'hst_includes_unit' => (bool) ($m['hst_includes_unit'] ?? false),
                'catalog_material_id' => $catalogId > 0 ? $catalogId : null,
                'delivery_cad' => $isElectrical ? 0.0 : round(max(0.0, (float) ($m['delivery_cad'] ?? 0)), 2),
                'line_kind' => $lineKind,
                'entry_mode' => $entryMode,
            ];
        };

        $worksForStorage = [];
        $materialsForStorage = [];
        foreach ($computed['works'] as $i => $job) {
            $posted = $worksForCompute[$i] ?? [];
            $jobMaterials = array_map($materialMapper, is_array($job['materials'] ?? null) ? $job['materials'] : []);
            foreach ($jobMaterials as $row) {
                $materialsForStorage[] = $row;
            }
            $useFixed = (bool) ($posted['labour_use_fixed'] ?? false);
            $worksForStorage[] = [
                'title' => (string) ($posted['title'] ?? ''),
                'materials' => $jobMaterials,
                'labour_mode' => $posted['labour_mode'] ?? 'hours',
                'labour_quantity' => round((float) ($posted['labour_quantity'] ?? 0), 2),
                'labour_hours' => $job['labour_hours'],
                'labour_hourly_rate' => round((float) ($posted['labour_hourly_rate'] ?? 0), 2),
                'labour_use_fixed' => $useFixed,
                'labour_fixed_amount' => $useFixed ? round((float) ($posted['labour_fixed_amount'] ?? 0), 2) : null,
                'labour_fixed_hst_included' => $useFixed && (bool) ($posted['labour_fixed_hst_included'] ?? false),
            ];
        }

        $firstWork = $worksForStorage[0] ?? null;
        $singleJob = count($worksForStorage) === 1 && is_array($firstWork);

        $workDescription = trim((string) ($data['work_description'] ?? ''));
        $workDescription = \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($workDescription) ?? '';
        $paymentSchedule = $this->paymentScheduleFromRequest($request);

        $persistence = [
            'persistence' => [
                'client_name' => trim($data['client_name']),
                'display_title' => \Illuminate\Support\Str::limit($data['display_title'], 255, ''),
                'document_type' => $data['document_type'],
                'includes_material' => $includesMaterial,
                'includes_labour' => $includesLabour,
                'pdf_detail_mode' => $data['pdf_detail_mode'],
                'work_description' => $workDescription === '' ? null : $workDescription,
                'payment_installments' => $paymentSchedule['payment_installments'],
                'payment_percentages' => $paymentSchedule['payment_percentages'],
                'materials' => $materialsForStorage,
                'works' => $worksForStorage,
                'labour_mode' => $singleJob ? ($firstWork['labour_mode'] ?? 'hours') : 'hours',
                'labour_quantity' => $singleJob
                    ? (float) ($firstWork['labour_quantity'] ?? 0)
                    : (float) $computed['labour_hours'],
                'labour_hours' => $computed['labour_hours'],
                'labour_hourly_rate' => $singleJob
                    ? (float) ($firstWork['labour_hourly_rate'] ?? 0)
                    : 0,
                'labour_use_fixed' => $singleJob
                    ? (bool) ($firstWork['labour_use_fixed'] ?? false)
                    : true,
                'labour_fixed_amount' => $singleJob
                    ? $firstWork['labour_fixed_amount']
                    : (float) $computed['labour_total'],
                'labour_fixed_hst_included' => $singleJob
                    ? (bool) ($firstWork['labour_fixed_hst_included'] ?? false)
                    : false,
                'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
                'materials_hst' => $computed['materials_hst'],
                'materials_with_hst' => $computed['materials_with_hst'],
                'labour_total' => $computed['labour_total'],
                'labour_hst' => $computed['labour_hst'],
                'include_labour_hst' => $includeLabourHst,
                'grand_total' => $computed['grand_total'],
                'contingency_enabled' => $computed['contingency_enabled'],
                'contingency_percent' => $computed['contingency_percent'],
                'contingency_amount' => $computed['contingency_amount'],
                'contingency_hst' => $computed['contingency_hst'],
                'include_building' => $building['include_building'],
                'building_labor_percent' => $building['building_labor_percent'],
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
        $this->refreshLinkedCommuteExpense($standardQuotation, $project);
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
        $taxablePreHst = $this->preHstTaxableForLink($standardQuotation);
        $exemptPreHst = $this->preHstExemptForLink($standardQuotation);

        /** @var ClientProjectQuotation $row */
        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $standardQuotation->id,
        ]);

        $lineDescription = $this->resolveStandardLinkLineDescription(
            $standardQuotation,
            $lineDescription,
            $row->exists ? $row : null
        );

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

        $docType = $standardQuotation->document_type;
        if ($docType !== ClientProjectQuotation::TYPE_QUOTATION
            && $docType !== ClientProjectQuotation::TYPE_ESTIMATE) {
            $docType = ClientProjectQuotation::TYPE_QUOTATION;
        }

        $includeLabour = (bool) $standardQuotation->includes_labour
            || $standardQuotation->chargedLabourPreTax() > 0;
        $includeMaterial = (bool) $standardQuotation->includes_material
            || $standardQuotation->chargedMaterialsPreTax() > 0;

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

    protected function resolveStandardLinkLineDescription(
        StandardQuotation $standardQuotation,
        ?string $lineDescription,
        ?ClientProjectQuotation $existing = null
    ): string {
        $lineDescription = trim((string) ($lineDescription ?? ''));
        if ($lineDescription !== '') {
            return $lineDescription;
        }

        $existingTitle = $existing ? trim((string) ($existing->title ?? '')) : '';
        if ($existingTitle !== '') {
            return $existingTitle;
        }

        return $standardQuotation->pdfSummaryLineDescription();
    }

    /**
     * Pre-HST amount (materials taxable subtotal + taxable share of
     * contingency + labour total) that should be linked into the project
     * quotation as an HST-applicable line.
     */
    protected function preHstTaxableForLink(StandardQuotation $sq): float
    {
        return round((float) ($sq->computedTotals()['pre_hst_taxable'] ?? 0), 2);
    }

    /**
     * Pre-HST amount of HST-exempt material rows (and their share of
     * contingency, if any). Linked as a separate exempt line item so the
     * project quotation does not add HST to it.
     */
    protected function preHstExemptForLink(StandardQuotation $sq): float
    {
        return round((float) ($sq->computedTotals()['pre_hst_exempt'] ?? 0), 2);
    }

    protected function purchaseListCellHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }

}
