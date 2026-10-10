<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LowDeckAreaExceededException;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationAdditionalCosts;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\LowDeckFormulaSetting;
use App\Models\LowDeckMaterial;
use App\Models\LowDeckQuotation;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\LowDeckQuotationCalculator;
use App\Services\QuotationPaymentSchedule;
use App\Support\LowDeckWorkDescriptionGenerator;
use App\Support\PurchaseListPrintMode;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationSaveIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/**
 * LowDeckQuotationController — standalone low floating deck calculator quotes.
 *
 * Like the fence module, this has NO PDF of its own: it produces a saved
 * snapshot, and the client-facing document is always the project's existing
 * Quotation. Linking a deck quote into a project appends a single line item
 * (description + pre-tax amount) to that project's ClientProjectQuotation.
 */
class LowDeckQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            LowDeckQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.low_deck.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.quotations.low_deck.create', $this->editorViewData(null, $request));
    }

    public function store(Request $request)
    {
        $data = $this->validateLowDeckInputs($request);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $calc = (new LowDeckQuotationCalculator())->calculate($data);
        $persistence = $this->lowDeckPersistenceFromCalc($request, $calc, $data);

        $quote = LowDeckQuotation::create(array_merge($persistence, [
            'quote_number' => LowDeckQuotation::generateNumber(),
            'version' => 1,
        ]));
        $this->rememberSubmitSucceeded($request);

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkLowDeckIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $intent = QuotationSaveIntent::fromRequest($request);
        $resp = redirect()
            ->route('admin.quotations.low-deck.edit', $quote->fresh())
            ->with('success', $intent === QuotationSaveIntent::DRAFT
                ? ($linkedNow
                    ? 'Low deck quotation saved as draft and linked.'
                    : 'Low deck quotation saved as draft.')
                : ($linkedNow
                    ? 'Low deck quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Low deck quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, LowDeckQuotation $low_deck_quotation)
    {
        return view('admin.quotations.low_deck.create', $this->editorViewData($low_deck_quotation, $request));
    }

    public function update(Request $request, LowDeckQuotation $low_deck_quotation)
    {
        $data = $this->validateLowDeckInputs($request);

        $calc = (new LowDeckQuotationCalculator())->calculate($data);
        $persistence = $this->lowDeckPersistenceFromCalc($request, $calc, $data, $low_deck_quotation);

        $low_deck_quotation->fill(array_merge($persistence, [
            'version' => $low_deck_quotation->version + 1,
        ]))->save();

        if ($low_deck_quotation->isLinked()) {
            $project = ClientProject::query()->find($low_deck_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $low_deck_quotation) {
                    $this->syncLowDeckLineToProjectQuotation($project, $low_deck_quotation->fresh());
                });
            }
        }

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkLowDeckIfRequested($low_deck_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.low-deck.edit', $low_deck_quotation)
            ->with('success', $linkedNow
                ? 'Low deck quotation '.$low_deck_quotation->quote_number.' updated and linked.'
                : 'Low deck quotation '.$low_deck_quotation->quote_number.' updated (v'.$low_deck_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    /** Live JSON breakdown for the calculator form. */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'width_ft' => ['required', 'numeric', 'min:0', 'max:500'],
            'length_ft' => ['required', 'numeric', 'min:0', 'max:500'],
            'labour_rate_per_sqft' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'waste_dump_fee_cad' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'material_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'include_under_deck_gravel' => ['nullable', 'boolean'],
            'include_labour_hst' => ['nullable', 'boolean'],
        ]);
        $data['material_waste_percent'] = $this->normaliseMaterialWastePercent(
            $request->input('material_waste_percent')
        );
        $data['include_under_deck_gravel'] = $request->has('include_under_deck_gravel')
            ? $request->boolean('include_under_deck_gravel')
            : true;
        $data['include_labour_hst'] = $request->has('include_labour_hst')
            ? $request->boolean('include_labour_hst')
            : true;

        try {
            $calc = (new LowDeckQuotationCalculator())->calculate($data);
        } catch (LowDeckAreaExceededException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $calc['suggested_work_description'] = LowDeckWorkDescriptionGenerator::generate($calc['geometry'] ?? []);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, LowDeckQuotation $low_deck_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($low_deck_quotation, 'low deck')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($low_deck_quotation, $project);

        if ($resp = $this->guardQuotationIsDraft($low_deck_quotation, 'link')) {
            return $resp;
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $low_deck_quotation) {
            $this->appendLowDeckLineToProjectQuotation($project, $low_deck_quotation);
        });

        return redirect()
            ->route('admin.quotations.low-deck.edit', $low_deck_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    public function createProjectAndLink(
        Request $request,
        LowDeckQuotation $low_deck_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($low_deck_quotation, 'low deck')) {
            return $resp;
        }
        if ($resp = $this->guardQuotationIsDraft($low_deck_quotation, 'create-project')) {
            return $resp;
        }

        $this->normalisePhoneOnRequest($request);

        $data = $request->validate([
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

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Low deck');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $low_deck_quotation) {
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

            $this->appendLowDeckLineToProjectQuotation($project, $low_deck_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created (fixed price). The low deck line item was added to the Quotation tab.');
    }

    public function destroy(LowDeckQuotation $low_deck_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_LOW_DECK)
            ->where('source_id', $low_deck_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $low_deck_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.low-deck.index')
            ->with('success', 'Low deck quotation deleted.');
    }

    /** Printable Home Depot shopping list for a saved quote. */
    public function purchaseList(Request $request, LowDeckQuotation $low_deck_quotation)
    {
        $low_deck_quotation->load('project');

        $calc = $low_deck_quotation->calculation;
        $rows = [];

        if (is_array($calc) && ! empty($calc['materials'])) {
            $byKey = collect($calc['materials'])->keyBy('key');
            $catalogue = LowDeckMaterial::catalogueByKey();

            foreach (LowDeckMaterial::PURCHASE_LIST_KEYS as $key) {
                $line = $byKey->get($key);
                if (! $line) {
                    continue;
                }
                $quantity = (float) ($line['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                /** @var LowDeckMaterial|null $mat */
                $mat = $catalogue->get($key);

                $longName = $mat && filled($mat->effectiveLongName())
                    ? $mat->effectiveLongName()
                    : (filled($line['long_name'] ?? null) ? (string) $line['long_name'] : (string) ($mat?->name ?? $line['name'] ?? $key));

                $rows[] = [
                    'quantity' => $quantity,
                    'unit' => (string) ($line['unit'] ?? 'ea'),
                    'name_html' => $this->purchaseListCellHtml($longName),
                    'store_sku_html' => $mat && filled($mat->effectiveStoreSku())
                        ? $this->purchaseListCellHtml($mat->effectiveStoreSku())
                        : (filled($line['store_sku'] ?? null) ? $this->purchaseListCellHtml((string) $line['store_sku']) : null),
                    'unit_html' => $this->purchaseListCellHtml((string) ($line['unit'] ?? 'ea')),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'subtotal' => (float) ($line['subtotal'] ?? 0),
                ];
            }
        }

        return view('admin.quotations.low_deck.purchase_list', [
            'quotation' => $low_deck_quotation,
            'rows' => $rows,
            'showPrices' => PurchaseListPrintMode::showsPrices($request->query('prices')),
        ]);
    }

    /** Printable foundations / framing / decking layout diagrams for a saved quote. */
    public function layoutDiagrams(LowDeckQuotation $low_deck_quotation)
    {
        $low_deck_quotation->loadMissing('project');

        $geometry = is_array($low_deck_quotation->calculation)
            ? ($low_deck_quotation->calculation['geometry'] ?? [])
            : [];

        $hasDiagrams = is_array($geometry)
            && (float) ($geometry['run_ft'] ?? 0) > 0
            && (float) ($geometry['span_ft'] ?? 0) > 0;

        return view('admin.quotations.low_deck.layout_diagrams', [
            'quotation' => $low_deck_quotation,
            'hasDiagrams' => $hasDiagrams,
            'diagrams' => $hasDiagrams
                ? \App\Support\LowDeckDiagramRenderer::render($geometry)
                : ['foundations' => '', 'framing' => '', 'decking' => ''],
        ]);
    }

    // =================================================================
    //   Helpers
    // =================================================================

    protected function editorViewData(?LowDeckQuotation $quotation, Request $request): array
    {
        $settings = LowDeckFormulaSetting::current();
        $materials = LowDeckMaterial::query()->with('material')->orderBy('sort_order')->get();

        $zeroPricedKeys = $materials
            ->filter(fn (LowDeckMaterial $m) => $m->effectivePriceCad() <= 0)
            ->pluck('name')
            ->values()
            ->all();

        $linkableProjects = collect();
        if ($quotation && ! $quotation->project) {
            $linkableProjects = ClientProject::query()
                ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
                ->orderByDesc('id')
                ->get(['id', 'name', 'client_name', 'status']);
        }

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return [
            'quotation' => $quotation,
            'settings' => $settings,
            'materials' => $materials,
            'zeroPricedMaterials' => $zeroPricedKeys,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $quotation
                ? ClientProjectQuotation::findForSource(
                    ClientProjectQuotation::SOURCE_LOW_DECK,
                    (int) $quotation->id
                )
                : null,
        ];
    }

    protected function autoLinkLowDeckIfRequested(LowDeckQuotation $ld, Request $request, array &$warnings): bool
    {
        if ($ld->isLinked() || ($ld->is_draft ?? false)) {
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

        $preTaxLine = round((float) $ld->materials_subtotal + (float) $ld->labour_total, 2);
        if ($preTaxLine <= 0) {
            $warnings[] = 'Pre-tax labour + materials must be greater than zero before this quote can attach to the project.';

            return false;
        }

        try {
            DB::transaction(function () use ($project, $ld) {
                $this->appendLowDeckLineToProjectQuotation($project, $ld->fresh());
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncLowDeckLineToProjectQuotation(ClientProject $project, LowDeckQuotation $low_deck_quotation): void
    {
        if ($this->quotationIsDraft($low_deck_quotation)) {
            return;
        }
        $items = $low_deck_quotation->projectQuotationLineItems();
        $this->appendCommuteProjectLine($items, $low_deck_quotation);
        $this->appendDebrisRemovalProjectLine($items, $low_deck_quotation);
        $this->appendAdditionalCostsProjectLines($items, $low_deck_quotation);

        $snapshot = $low_deck_quotation->only([
            'quote_number',
            'client_name',
            'work_description',
            'width_ft',
            'length_ft',
            'joist_spacing_in',
            'labour_rate_per_sqft',
            'waste_dump_fee_cad',
            'include_diagrams_on_pdf',
            'calculation',
            'materials_subtotal',
            'hst',
            'labour_total',
            'labour_hst',
            'include_labour_hst',
            'grand_total',
            'include_commute',
            'commute_trip_percent',
            'commute_estimated_trips',
            'commute_round_trip_cost',
            'commute_amount',
        ]);

        /** @var ClientProjectQuotation $row */
        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_LOW_DECK,
            'source_id' => $low_deck_quotation->id,
        ]);

        if (! $row->exists) {
            $row->fill([
                'title' => 'Low deck — '.$low_deck_quotation->quote_number,
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
        $row->work_description = $low_deck_quotation->work_description;
        $this->applyPaymentScheduleToProjectQuotation($row, $low_deck_quotation);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendLowDeckLineToProjectQuotation(ClientProject $project, LowDeckQuotation $low_deck_quotation): void
    {
        if ($this->quotationIsDraft($low_deck_quotation)) {
            return;
        }

        $this->syncLowDeckLineToProjectQuotation($project, $low_deck_quotation);

        $low_deck_quotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

    protected function validateLowDeckInputs(Request $request): array
    {
        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $settings = LowDeckFormulaSetting::current();
        $maxArea = (float) $settings->max_area_sq_ft;

        $rules = array_merge([
            'save_intent' => ['nullable', 'in:draft,publish'],
            'client_name' => ['required', 'string', 'max:160'],
            'work_description' => ['nullable', 'string', 'max:20000'],
            'width_ft' => ['required', 'numeric', 'min:1', 'max:500'],
            'length_ft' => ['required', 'numeric', 'min:1', 'max:500'],
            'labour_rate_per_sqft' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'waste_dump_fee_cad' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'material_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'include_under_deck_gravel' => ['nullable', 'boolean'],
            'include_labour_hst' => ['nullable', 'boolean'],
            'include_diagrams_on_pdf' => ['nullable', 'boolean'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules());

        $validator = validator($request->all(), $rules, array_merge(
            QuotationPaymentSchedule::validationMessages(),
            CommuteExpenseService::commuteValidationMessages(),
            DebrisRemovalExpenseService::validationMessages(),
            AdditionalCostsService::validationMessages()
        ));
        $validator->after(function ($validator) use ($request, $maxArea) {
            $area = (float) $request->input('width_ft') * (float) $request->input('length_ft');
            if ($area > $maxArea + 1e-6) {
                $validator->errors()->add(
                    'width_ft',
                    sprintf(
                        'Deck area %s sq ft exceeds the %s sq ft maximum for a floating deck. Split the deck or reduce the dimensions.',
                        rtrim(rtrim(number_format($area, 2), '0'), '.'),
                        rtrim(rtrim(number_format($maxArea, 2), '0'), '.')
                    )
                );
            }
        });

        return $validator->validate() + [
            'material_waste_percent' => $this->normaliseMaterialWastePercent(
                $request->input('material_waste_percent')
            ),
            'include_under_deck_gravel' => $request->has('include_under_deck_gravel')
                ? $request->boolean('include_under_deck_gravel')
                : true,
            'include_labour_hst' => $request->has('include_labour_hst')
                ? $request->boolean('include_labour_hst')
                : true,
        ];
    }

    protected function normaliseMaterialWastePercent(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 10.0;
        }

        $percent = round(max(0.0, (float) $value), 2);

        return $percent > 0 && $percent <= 1 ? round($percent * 100, 2) : $percent;
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function lowDeckPersistenceFromCalc(
        Request $request,
        array $calc,
        array $data,
        ?LowDeckQuotation $existing = null
    ): array {
        $base = array_merge([
            'client_name' => $data['client_name'],
            'is_draft' => QuotationSaveIntent::isDraft($request),
            'work_description' => \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($data['work_description'] ?? null),
            'width_ft' => $calc['inputs']['width_ft'],
            'length_ft' => $calc['inputs']['length_ft'],
            'joist_spacing_in' => (int) round((float) ($calc['geometry']['joist_spacing_in'] ?? 0)),
            'labour_rate_per_sqft' => $calc['inputs']['labour_rate_per_sqft'],
            'waste_dump_fee_cad' => $calc['inputs']['waste_dump_fee_cad'],
            'include_diagrams_on_pdf' => $request->boolean('include_diagrams_on_pdf'),
            'calculation' => $calc,
            'materials_subtotal' => $calc['totals']['materials_subtotal'],
            'hst' => $calc['totals']['hst'],
            'labour_total' => $calc['totals']['labour_subtotal'],
            'labour_hst' => $calc['totals']['labour_hst'],
            'include_labour_hst' => (bool) ($calc['inputs']['include_labour_hst'] ?? true),
            'grand_total' => $calc['totals']['grand_total'],
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'low_deck_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'grand_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'low_deck_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'grand_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'low_deck_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'grand_total');
    }

    /**
     * Undo literal HTML entities in catalogue text (e.g. &#039;, &amp;#039;).
     */
    protected function decodePurchaseListText(string $value): string
    {
        $decoded = $value;
        for ($i = 0; $i < 8; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        $decoded = preg_replace('/&(?:#0*39|#39)(?!\\d);?/i', "'", $decoded);
        $decoded = preg_replace('/&(?:#x0*27)(?![0-9a-fA-F]);?/i', "'", $decoded);
        $decoded = str_replace(['&apos;', '&#039;', '&#39;', '&#x27;', '&#X27;'], "'", $decoded);

        for ($i = 0; $i < 3; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        for ($i = 0; $i < 5; $i++) {
            $next = preg_replace('/&(?:#0*39|#39)(?!\\d);?/i', "'", $decoded);
            $next = preg_replace('/&(?:#x0*27)(?![0-9a-fA-F]);?/i', "'", $next);
            $next = str_replace(['&apos;', '&#039;', '&#39;', '&#x27;', '&#X27;'], "'", $next);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        return $decoded;
    }

    /**
     * Decode catalogue entities, then escape for HTML text nodes without
     * re-encoding apostrophes (avoids &#039; showing literally in print).
     */
    protected function purchaseListCellHtml(string $value): string
    {
        $plain = $this->decodePurchaseListText($value);

        return htmlspecialchars($plain, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }
}
