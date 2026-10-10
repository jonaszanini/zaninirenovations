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
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\FenceQuotationCalculator;
use App\Services\QuotationPaymentSchedule;
use App\Support\FenceGateSpec;
use App\Support\FenceWorkDescriptionGenerator;
use App\Support\PurchaseListPrintMode;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationSaveIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * FenceQuotationController — standalone fence calculator quotes.
 *
 * Intentionally has NO PDF rendering of its own: the module is a
 * calculator that produces a saved snapshot, and the client-facing
 * document is always the project's existing Quotation. When you link
 * a fence quote into a project, `linkToProject()` appends a single
 * line item (description + pre-tax amount) to that project's
 * ClientProjectQuotation, which is then what gets rendered and
 * emailed via the existing Quotation flow.
 */
class FenceQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    /** The Fence hub: list of existing fence quotes + "New" button. */
    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            FenceQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.fence.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    /** Render the calculator form for a brand-new fence quote. */
    public function create(Request $request)
    {
        $settings = FenceFormulaSetting::current();
        $materials = FenceMaterial::query()->with('material')->orderBy('sort_order')->get();

        // A handful of catalogue prices must be > 0 for the calculator to
        // produce a usable estimate. Warn the admin up front instead of
        // letting them fill the form and then discover a $0 grand total.
        $zeroPricedKeys = $materials
            ->filter(fn (FenceMaterial $m) => $m->effectivePriceCad() <= 0)
            ->pluck('name')
            ->values()
            ->all();

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.fence.create', [
            'quotation' => null,
            'settings' => $settings,
            'materials' => $materials,
            'zeroPricedMaterials' => $zeroPricedKeys,
            'linkableProjects' => collect(),
            'linkProject' => $linkProject,
            'linkedCpq' => null,
        ]);
    }

    /** Persist a new fence quotation. */
    public function store(Request $request)
    {
        $data = $this->validateFenceInputs($request);

        $calc = (new FenceQuotationCalculator())->calculate($data);
        $persistence = $this->fencePersistenceFromCalc($request, $calc, $data);

        $intent = QuotationSaveIntent::fromRequest($request);
        $quote = FenceQuotation::create(array_merge($persistence, [
            'quote_number' => FenceQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkFenceIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $editUrl = route('admin.quotations.fence.edit', $quote->fresh());
        if ($request->input('after_save') === 'create_project' && ! $quote->fresh()->isLinked()) {
            $editUrl .= '#fq-create-project';
        }

        $resp = redirect()
            ->to($editUrl)
            ->with('success', $intent === QuotationSaveIntent::DRAFT
                ? ($linkedNow
                    ? 'Fence quotation saved as draft and linked.'
                    : 'Fence quotation saved as draft.')
                : ($linkedNow
                    ? 'Fence quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Fence quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    /** Open an existing quote back in the calculator for editing. */
    public function edit(Request $request, FenceQuotation $fence_quotation)
    {
        $settings = FenceFormulaSetting::current();
        $materials = FenceMaterial::query()->with('material')->orderBy('sort_order')->get();

        $zeroPricedKeys = $materials
            ->filter(fn (FenceMaterial $m) => $m->effectivePriceCad() <= 0)
            ->pluck('name')
            ->values()
            ->all();

        // Projects we'd allow linking to: everything that isn't rejected
        // or completed. The UI uses this to populate the "Link to project"
        // dropdown.
        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $linkProject = $this->quotationLinkCandidateProject($request);
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        return view('admin.quotations.fence.create', [
            'quotation' => $fence_quotation,
            'settings' => $settings,
            'materials' => $materials,
            'zeroPricedMaterials' => $zeroPricedKeys,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_FENCE,
                (int) $fence_quotation->id
            ),
        ]);
    }

    /**
     * Update an existing quote. Quantities follow the current runs;
     * catalogue unit prices stay frozen from the last save unless
     * `reprice` is posted (B17).
     */
    public function update(Request $request, FenceQuotation $fence_quotation)
    {
        $data = $this->validateFenceInputs($request, $fence_quotation);

        $frozen = $request->boolean('reprice')
            ? null
            : FenceQuotationCalculator::frozenUnitPricesFromCalculation(
                is_array($fence_quotation->calculation) ? $fence_quotation->calculation : null
            );
        $calc = (new FenceQuotationCalculator())->calculate($data, $frozen ?: null);
        $persistence = $this->fencePersistenceFromCalc($request, $calc, $data, $fence_quotation);

        $fence_quotation->fill(array_merge($persistence, [
            'version' => $fence_quotation->version + 1,
        ]))->save();

        if ($fence_quotation->isLinked()) {
            $project = ClientProject::query()->find($fence_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $fence_quotation) {
                    $this->syncFenceLineToProjectQuotation($project, $fence_quotation->fresh());
                });
            }
        }

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkFenceIfRequested($fence_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.fence.edit', $fence_quotation)
            ->with('success', $linkedNow
                ? 'Fence quotation '.$fence_quotation->quote_number.' updated and linked.'
                : 'Fence quotation '.$fence_quotation->quote_number.' updated (v'.$fence_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    /**
     * Lightweight JSON endpoint used by the calculator form to preview
     * the breakdown live, without persisting anything.
     */
    public function preview(Request $request)
    {
        $data = $this->validateFencePreviewInputs($request);
        $data['include_labour_hst'] = $request->has('include_labour_hst')
            ? $request->boolean('include_labour_hst')
            : true;

        $frozen = null;
        if ($request->boolean('use_frozen_prices') && is_array($request->input('frozen_unit_prices'))) {
            $frozen = [];
            foreach ($request->input('frozen_unit_prices') as $key => $price) {
                if (is_string($key) && is_numeric($price)) {
                    $frozen[$key] = round((float) $price, 2);
                }
            }
        }

        unset($data['fence_quotation_id']);

        $calc = (new FenceQuotationCalculator())->calculate($data, $frozen ?: null);
        $calc['suggested_work_description'] = FenceWorkDescriptionGenerator::generate($calc);

        return response()->json($calc);
    }

    /**
     * Link the fence quote to an existing client project by appending
     * a single line item (description + grand total) to that project's
     * main Quotation. Creates the Quotation row if one doesn't exist.
     *
     * We deliberately do NOT replace the project's existing line items
     * — the fence estimate is additive, sitting next to whatever other
     * scope the admin already captured.
     */
    public function linkToProject(Request $request, FenceQuotation $fence_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($fence_quotation, 'fence')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($fence_quotation, $project);

        if ($resp = $this->guardQuotationIsDraft($fence_quotation, 'link')) {
            return $resp;
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $fence_quotation) {
            $this->appendFenceLineToProjectQuotation($project, $fence_quotation);
        });

        return redirect()
            ->route('admin.quotations.fence.edit', $fence_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    /**
     * Create a new fixed-price client project from the fence editor, then
     * attach this fence estimate as the first line on that project's
     * Quotation. No "quote received" email is sent — the lead already came
     * from the fence calculator.
     */
    public function createProjectAndLink(
        Request $request,
        FenceQuotation $fence_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($fence_quotation, 'fence')) {
            return $resp;
        }
        if ($resp = $this->guardQuotationIsDraft($fence_quotation, 'create-project')) {
            return $resp;
        }

        $this->normalisePhoneOnRequest($request);

        $data = $request->validate([
            'fp_project_name' => ['nullable', 'string', 'max:255'],
            'fp_client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'fp_client_name'  => ['required', 'string', 'max:255'],
            'fp_client_email' => ['nullable', 'email', 'max:255'],
            'fp_client_phone' => ['nullable', 'digits:10'],
            'fp_client_address' => ['nullable', 'string', 'max:500'],
        ], [
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits (e.g. (123) 456-7890).',
            'fp_client_email.email' => 'Please enter a valid email address.',
        ]);

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Fence');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $fence_quotation) {
            $project = ClientProject::create([
                'name'           => $projectTitle,
                'client_id'      => $data['fp_client_id'] ?: null,
                'client_name'    => $clientName,
                'client_email'   => $data['fp_client_email'] ?: null,
                'client_phone'   => $data['fp_client_phone'] ?: null,
                'client_address' => $data['fp_client_address'] ?: null,
                'billing_type'   => ClientProject::BILLING_FIXED,
                'hourly_rate'    => null,
                'status'         => ClientProject::STATUS_VISIT_SCHEDULED,
                'status_changed_at' => now(),
            ]);

            $this->appendFenceLineToProjectQuotation($project, $fence_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created (fixed price). The fence line item was added to the Quotation tab.');
    }

    protected function autoLinkFenceIfRequested(FenceQuotation $fq, Request $request, array &$warnings): bool
    {
        if ($fq->isLinked() || ($fq->is_draft ?? false)) {
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

        $preTaxLine = round((float) $fq->materials_subtotal + (float) $fq->labour_total, 2);
        if ($preTaxLine <= 0) {
            $warnings[] = 'Pre-tax labour + materials must be greater than zero before this quote can attach to the project.';

            return false;
        }

        try {
            DB::transaction(function () use ($project, $fq) {
                $this->appendFenceLineToProjectQuotation($project, $fq->fresh());
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    /**
     * Push the current fence totals/description into the linked
     * {@see ClientProjectQuotation} row (and project financials). Does not
     * change {@see FenceQuotation::$linked_at} — use that only on first link.
     */
    protected function syncFenceLineToProjectQuotation(ClientProject $project, FenceQuotation $fence_quotation): void
    {
        if ($this->quotationIsDraft($fence_quotation)) {
            return;
        }
        $items = $fence_quotation->projectQuotationLineItems();
        $this->appendCommuteProjectLine($items, $fence_quotation);
        $this->appendDebrisRemovalProjectLine($items, $fence_quotation);
        $this->appendAdditionalCostsProjectLines($items, $fence_quotation);

        $snapshot = $fence_quotation->only([
            'quote_number',
            'client_name',
            'work_description',
            'fence_height_ft',
            'post_size',
            'board_style',
            'gate_count',
            'labour_rate_per_ft',
            'labour_rate_8ft_per_ft',
            'existing_post_labour_credit',
            'sides',
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
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'source_id' => $fence_quotation->id,
        ]);

        if (!$row->exists) {
            $row->fill([
                'title' => 'Fence — '.$fence_quotation->quote_number,
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
        $row->work_description = $fence_quotation->work_description;
        $this->applyPaymentScheduleToProjectQuotation($row, $fence_quotation);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    /**
     * Append this fence quote as its own {@see ClientProjectQuotation} row
     * (draft, linked to the fence record). Totals roll into Financials once
     * the quotation is accepted.
     */
    protected function appendFenceLineToProjectQuotation(ClientProject $project, FenceQuotation $fence_quotation): void
    {
        if ($this->quotationIsDraft($fence_quotation)) {
            return;
        }

        $this->syncFenceLineToProjectQuotation($project, $fence_quotation);

        $fence_quotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

    /**
     * Strip non-digits from the "new project" phone field before validation
     * (same behaviour as ClientProjectController::normalisePhoneOnRequest).
     */
    public function destroy(FenceQuotation $fence_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_FENCE)
            ->where('source_id', $fence_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $fence_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.fence.index')
            ->with('success', 'Fence quotation deleted.');
    }

    /**
     * Printable purchasing list (e.g. Home Depot) for a saved quote.
     * Quantities come from the stored calculation; long name, SKU, and
     * model come from the live materials catalogue when set.
     */
    public function purchaseList(Request $request, FenceQuotation $fence_quotation)
    {
        $fence_quotation->load('project');

        $calc = $fence_quotation->calculation;
        $rows = [];
        $alsoBuy = [];

        if (is_array($calc) && !empty($calc['materials'])) {
            $byKey = collect($calc['materials'])->keyBy('key');
            $catalogue = FenceMaterial::catalogueByKey();

            foreach (FenceMaterial::PURCHASE_LIST_KEYS as $key) {
                $row = $this->purchaseListRowFromCalc($byKey->get($key), $catalogue->get($key), $key);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            foreach (FenceMaterial::ALSO_BUY_KEYS as $key) {
                $row = $this->purchaseListRowFromCalc($byKey->get($key), $catalogue->get($key), $key);
                if ($row !== null) {
                    $alsoBuy[] = $row;
                }
            }
        }

        return view('admin.quotations.fence.purchase_list', [
            'quotation' => $fence_quotation,
            'rows' => $rows,
            'alsoBuyRows' => $alsoBuy,
            'showPrices' => PurchaseListPrintMode::showsPrices($request->query('prices')),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $line
     * @return array<string, mixed>|null
     */
    protected function purchaseListRowFromCalc(?array $line, ?FenceMaterial $mat, string $key): ?array
    {
        if (! $line) {
            return null;
        }
        $quantity = (float) ($line['quantity'] ?? 0);
        if ($quantity <= 0) {
            return null;
        }

        $qtyPart = $this->formatPurchaseQuantityLabel($quantity);
        $longName = $mat && filled($mat->effectiveLongName())
            ? $mat->effectiveLongName()
            : (filled($line['long_name'] ?? null) ? (string) $line['long_name'] : (string) ($mat?->name ?? $line['name'] ?? $key));
        $storeSku = $mat && filled($mat->effectiveStoreSku())
            ? $mat->effectiveStoreSku()
            : (filled($line['store_sku'] ?? null) ? (string) $line['store_sku'] : null);
        $model = $mat && filled($mat->effectiveModel())
            ? $mat->effectiveModel()
            : (filled($line['model'] ?? null) ? (string) $line['model'] : null);

        return [
            'quantity_label' => $qtyPart,
            'long_name_html' => $this->purchaseListCellHtml($longName),
            'store_sku_html' => $storeSku !== null ? $this->purchaseListCellHtml($storeSku) : null,
            'model_html' => $model !== null ? $this->purchaseListCellHtml($model) : null,
            'unit_price' => (float) ($line['unit_price'] ?? 0),
            'subtotal' => (float) ($line['subtotal'] ?? 0),
        ];
    }

    // =================================================================
    //   Helpers
    // =================================================================

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

        // Last pass: any foot-mark entity spellings left as plain text.
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
     * re-encoding apostrophes (avoids &#039; showing literally in some
     * browsers / print views).
     */
    protected function purchaseListCellHtml(string $value): string
    {
        $plain = $this->decodePurchaseListText($value);

        return htmlspecialchars($plain, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }

    /** Format a material quantity for the purchasing list (no trailing .00). */
    protected function formatPurchaseQuantityLabel(float $q): string
    {
        if (abs($q - round($q)) < 0.00001) {
            return (string) (int) round($q);
        }

        $s = number_format($q, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s !== '' ? $s : '0';
    }

    /** Shared validation rules for store() and update(). */
    protected function validateFenceInputs(Request $request, ?FenceQuotation $existing = null): array
    {
        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);
        $this->prepareExistingPostLabourCreditOnRequest($request, $existing);

        $rules = array_merge(
            $this->fenceGeometryRules(requiredClient: true),
            QuotationPaymentSchedule::validationRules(),
            CommuteExpenseService::commuteValidationRules(),
            DebrisRemovalExpenseService::validationRules(),
            AdditionalCostsService::validationRules()
        );

        $data = $request->validate($rules, array_merge(
            [
                'sides.required' => 'Enter at least one run length.',
                'sides.min' => 'Enter at least one run length.',
            ],
            QuotationPaymentSchedule::validationMessages(),
            CommuteExpenseService::commuteValidationMessages(),
            DebrisRemovalExpenseService::validationMessages(),
            AdditionalCostsService::validationMessages()
        ));
        $data['include_labour_hst'] = $request->has('include_labour_hst')
            ? $request->boolean('include_labour_hst')
            : true;
        $data['sides'] = $this->dropBlankFenceRuns($data['sides'] ?? []);
        if ($data['sides'] === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sides' => 'Enter at least one run length.',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateFencePreviewInputs(Request $request): array
    {
        $quote = null;
        $quoteId = $request->input('fence_quotation_id');
        if (is_numeric($quoteId) && (int) $quoteId > 0) {
            $quote = FenceQuotation::query()->find((int) $quoteId);
        }
        $this->prepareExistingPostLabourCreditOnRequest($request, $quote);

        $data = $request->validate(array_merge($this->fenceGeometryRules(requiredClient: false), [
            'fence_quotation_id' => ['nullable', 'integer', 'exists:fence_quotations,id'],
            'reprice' => ['nullable', 'boolean'],
        ]));
        $data['sides'] = $this->dropBlankFenceRuns($data['sides'] ?? []);

        return $data;
    }

    /**
     * Blank and missing credit fields use the same fallback as save:
     * posted value (including "0") always wins — even during re-price;
     * else re-price → config; else stored quote; else config for new quotes.
     */
    protected function prepareExistingPostLabourCreditOnRequest(Request $request, ?FenceQuotation $quote = null): void
    {
        $raw = $request->input('existing_post_labour_credit');
        $missing = $raw === null || $raw === '';
        if (! $missing) {
            return;
        }

        $request->merge([
            'existing_post_labour_credit' => $this->resolveExistingPostLabourCredit(
                $request->all(),
                $quote,
                $request->boolean('reprice')
            ),
        ]);
    }

    /**
     * Posted credit (including the string "0") always wins, including
     * when `reprice` is set. Re-price only supplies the current config
     * when the field is missing or blank. Re-price still means live
     * catalogue prices on update().
     */
    protected function resolveExistingPostLabourCredit(
        array $data,
        ?FenceQuotation $quote = null,
        bool $reprice = false
    ): float {
        if (array_key_exists('existing_post_labour_credit', $data)
            && $data['existing_post_labour_credit'] !== null
            && $data['existing_post_labour_credit'] !== '') {
            return round(max(0, (float) $data['existing_post_labour_credit']), 2);
        }

        if ($reprice) {
            return FenceFormulaSetting::current()->existingPostLabourCredit();
        }

        if ($quote !== null && $quote->existing_post_labour_credit !== null) {
            return round(max(0, (float) $quote->existing_post_labour_credit), 2);
        }

        return FenceFormulaSetting::current()->existingPostLabourCredit();
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>>
     */
    protected function fenceGeometryRules(bool $requiredClient): array
    {
        $gateKeys = array_keys(FenceGateSpec::PRESETS);

        $rules = [
            'sides' => ['required', 'array', 'min:1', 'max:'.FenceQuotation::MAX_RUNS],
            'sides.*.length_ft' => ['nullable', 'numeric', 'min:0.25', 'max:5000'],
            'sides.*.already_have_post_left' => ['nullable', 'boolean'],
            'sides.*.already_have_post_right' => ['nullable', 'boolean'],
            'sides.*.gate_goes_here' => ['nullable', 'boolean'],
            'sides.*.connected_to_previous' => ['nullable', 'boolean'],
            'sides.*.gate_preset' => ['nullable', Rule::in($gateKeys)],
            'sides.*.gate_width_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'sides.*.gate_type' => ['nullable', Rule::in([FenceGateSpec::TYPE_SINGLE, FenceGateSpec::TYPE_DOUBLE])],
            'fence_height_ft' => ['required', 'numeric', Rule::in([6, 8])],
            'post_size' => ['nullable', Rule::in([
                FenceQuotation::POST_SIZE_4X4,
                FenceQuotation::POST_SIZE_6X6,
            ])],
            'board_style' => ['required', Rule::in([
                FenceQuotation::BOARD_STYLE_STANDARD,
                FenceQuotation::BOARD_STYLE_OVERLAPPING,
            ])],
            'gate_count' => ['required', 'integer', 'min:0', 'max:16'],
            'extra_gate_preset' => ['nullable', Rule::in($gateKeys)],
            'labour_rate_per_ft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'labour_rate_8ft_per_ft' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'existing_post_labour_credit' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'include_labour_hst' => ['nullable', 'boolean'],
        ];

        if ($requiredClient) {
            $rules['client_name'] = ['required', 'string', 'max:160'];
            $rules['work_description'] = ['nullable', 'string', 'max:20000'];
            $rules['reprice'] = ['nullable', 'boolean'];
            $rules['after_save'] = ['nullable', 'string', 'max:40'];
            $rules['save_intent'] = ['nullable', 'in:draft,publish'];
        }

        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sides
     * @return list<array<string, mixed>>
     */
    protected function dropBlankFenceRuns(array $sides): array
    {
        $kept = [];
        foreach ($sides as $side) {
            if (! is_array($side)) {
                continue;
            }
            $len = $side['length_ft'] ?? null;
            if ($len === null || $len === '' || ! is_numeric($len) || (float) $len <= 0) {
                continue;
            }
            $kept[] = $side;
        }

        return array_values($kept);
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fencePersistenceFromCalc(
        Request $request,
        array $calc,
        array $data,
        ?FenceQuotation $existing = null
    ): array {
        $base = array_merge([
            'client_name' => $data['client_name'],
            'is_draft' => QuotationSaveIntent::isDraft($request),
            'work_description' => \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($data['work_description'] ?? null),
            'fence_height_ft' => $calc['inputs']['fence_height_ft'],
            'post_size' => $calc['inputs']['post_size'],
            'board_style' => $calc['inputs']['board_style'],
            'gate_count' => $calc['inputs']['gate_count'],
            'labour_rate_per_ft' => $data['labour_rate_per_ft'],
            'labour_rate_8ft_per_ft' => $data['labour_rate_8ft_per_ft'],
            'existing_post_labour_credit' => $calc['inputs']['existing_post_labour_credit'],
            'sides' => $calc['inputs']['sides'],
            'calculation' => $calc,
            'materials_subtotal' => $calc['totals']['materials_subtotal'],
            'hst' => $calc['totals']['hst'],
            'labour_total' => $calc['totals']['labour_subtotal'],
            'labour_hst' => $calc['totals']['labour_hst'],
            'include_labour_hst' => (bool) ($calc['inputs']['include_labour_hst'] ?? true),
            'grand_total' => $calc['totals']['grand_total'],
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'fence_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'grand_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'fence_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'grand_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'fence_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'grand_total');
    }
}
