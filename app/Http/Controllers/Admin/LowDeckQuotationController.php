<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LowDeckAreaExceededException;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\LowDeckFormulaSetting;
use App\Models\LowDeckMaterial;
use App\Models\LowDeckQuotation;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\LowDeckQuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    use HandlesQuotationCommuteExpense;
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

        $calc = (new LowDeckQuotationCalculator())->calculate($data);
        $persistence = $this->lowDeckPersistenceFromCalc($request, $calc, $data);

        $quote = LowDeckQuotation::create(array_merge($persistence, [
            'quote_number' => LowDeckQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkLowDeckIfRequested($quote->fresh(), $request, $warnings);

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $quote->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Low deck quotation '.$quote->quote_number.' saved and linked to this project.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.low-deck.edit', $quote->fresh())
            ->with('success', 'Low deck quotation '.$quote->quote_number.' saved.');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
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

        if ($newlyLinked && $hubProjectRequested) {
            $resp = redirect()
                ->route('admin.client-projects.show', $low_deck_quotation->fresh()->client_project_id)
                ->withFragment('quotation')
                ->with('success', 'Low deck quotation '.$low_deck_quotation->quote_number.' updated and linked.');

            return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
        }

        $resp = redirect()
            ->route('admin.quotations.low-deck.edit', $low_deck_quotation)
            ->with('success', 'Low deck quotation '.$low_deck_quotation->quote_number.' updated (v'.$low_deck_quotation->version.').');

        return $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings));
    }

    /** Live JSON breakdown for the calculator form. */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'width_ft' => ['required', 'numeric', 'min:0', 'max:500'],
            'length_ft' => ['required', 'numeric', 'min:0', 'max:500'],
            'joist_spacing_in' => ['required', 'integer', 'in:12,16'],
            'labour_rate_per_sqft' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'waste_dump_fee_cad' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ]);

        try {
            $calc = (new LowDeckQuotationCalculator())->calculate($data);
        } catch (LowDeckAreaExceededException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($calc);
    }

    public function linkToProject(Request $request, LowDeckQuotation $low_deck_quotation)
    {
        if ($low_deck_quotation->isLinked()) {
            return back()->with('error', 'This low deck quotation is already linked to a project.');
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($low_deck_quotation, $project);

        if (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
            return back()->with('error', 'Cannot link to this project — it no longer accepts new quotations.');
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
        if ($low_deck_quotation->isLinked()) {
            return back()->with('error', 'This low deck quotation is already linked to a project.');
        }

        $this->normaliseLowDeckProjectPhoneOnRequest($request);

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
        $projectTitle = trim((string) ($data['fp_project_name'] ?? ''));
        if ($projectTitle === '') {
            $projectTitle = $clientName.' — Low deck';
        }
        $projectTitle = Str::limit($projectTitle, 255, '');

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

        $statusService->onCreated($project, false);
        app(\App\Services\ClientProjectContactService::class)->ensureProjectLinkedToDirectory($project->fresh());

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
    public function purchaseList(LowDeckQuotation $low_deck_quotation)
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
                    'name' => $longName,
                    'store_sku' => $mat && filled($mat->effectiveStoreSku())
                        ? $mat->effectiveStoreSku()
                        : (filled($line['store_sku'] ?? null) ? (string) $line['store_sku'] : null),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'subtotal' => (float) ($line['subtotal'] ?? 0),
                ];
            }
        }

        return view('admin.quotations.low_deck.purchase_list', [
            'quotation' => $low_deck_quotation,
            'rows' => $rows,
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
        ];
    }

    protected function autoLinkLowDeckIfRequested(LowDeckQuotation $ld, Request $request, array &$warnings): bool
    {
        if ($ld->isLinked()) {
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
        $lineAmount = round(
            (float) $low_deck_quotation->materials_subtotal + (float) $low_deck_quotation->labour_total,
            2
        );

        $items = [[
            'description' => $low_deck_quotation->summaryDescription(),
            'amount' => $lineAmount,
            'apply_hst' => true,
        ]];
        $this->appendCommuteProjectLine($items, $low_deck_quotation);

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
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendLowDeckLineToProjectQuotation(ClientProject $project, LowDeckQuotation $low_deck_quotation): void
    {
        $this->syncLowDeckLineToProjectQuotation($project, $low_deck_quotation);

        $low_deck_quotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

    protected function normaliseLowDeckProjectPhoneOnRequest(Request $request): void
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

    protected function validateLowDeckInputs(Request $request): array
    {
        $settings = LowDeckFormulaSetting::current();
        $maxArea = (float) $settings->max_area_sq_ft;

        $rules = [
            'client_name' => ['required', 'string', 'max:160'],
            'work_description' => ['nullable', 'string', 'max:5000'],
            'width_ft' => ['required', 'numeric', 'min:1', 'max:500'],
            'length_ft' => ['required', 'numeric', 'min:1', 'max:500'],
            'joist_spacing_in' => ['required', 'integer', 'in:12,16'],
            'labour_rate_per_sqft' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'waste_dump_fee_cad' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'include_diagrams_on_pdf' => ['nullable', 'boolean'],
            ...CommuteExpenseService::commuteValidationRules(),
        ];

        $validator = validator($request->all(), $rules);
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

        return $validator->validate();
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
        $base = [
            'client_name' => $data['client_name'],
            'work_description' => trim((string) ($data['work_description'] ?? '')) ?: null,
            'width_ft' => $calc['inputs']['width_ft'],
            'length_ft' => $calc['inputs']['length_ft'],
            'joist_spacing_in' => $calc['inputs']['joist_spacing_in'],
            'labour_rate_per_sqft' => $calc['inputs']['labour_rate_per_sqft'],
            'waste_dump_fee_cad' => $calc['inputs']['waste_dump_fee_cad'],
            'include_diagrams_on_pdf' => $request->boolean('include_diagrams_on_pdf'),
            'calculation' => $calc,
            'materials_subtotal' => $calc['totals']['materials_subtotal'],
            'hst' => $calc['totals']['hst'],
            'labour_total' => $calc['totals']['labour_subtotal'],
            'labour_hst' => $calc['totals']['labour_hst'],
            'grand_total' => $calc['totals']['grand_total'],
        ];

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'low_deck_quotations');

        return $this->mergeCommuteIntoPersistence($base, $commute, 'grand_total');
    }
}
