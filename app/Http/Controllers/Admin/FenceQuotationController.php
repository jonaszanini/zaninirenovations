<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Services\ClientProjectStatusService;
use App\Services\FenceQuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    /** The Fence hub: list of existing fence quotes + "New" button. */
    public function index()
    {
        $quotations = FenceQuotation::query()
            ->with('project')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.quotations.fence.index', [
            'quotations' => $quotations,
        ]);
    }

    /** Render the calculator form for a brand-new fence quote. */
    public function create()
    {
        $settings = FenceFormulaSetting::current();
        $materials = FenceMaterial::query()->orderBy('sort_order')->get();

        // A handful of catalogue prices must be > 0 for the calculator to
        // produce a usable estimate. Warn the admin up front instead of
        // letting them fill the form and then discover a $0 grand total.
        $zeroPricedKeys = $materials
            ->filter(fn (FenceMaterial $m) => (float) $m->price_cad <= 0)
            ->pluck('name')
            ->values()
            ->all();

        return view('admin.quotations.fence.create', [
            'quotation' => null,
            'settings' => $settings,
            'materials' => $materials,
            'zeroPricedMaterials' => $zeroPricedKeys,
            'linkableProjects' => collect(),
        ]);
    }

    /** Persist a new fence quotation. */
    public function store(Request $request)
    {
        $data = $this->validateFenceInputs($request);

        $calc = (new FenceQuotationCalculator())->calculate($data);

        $quote = FenceQuotation::create([
            'quote_number' => FenceQuotation::generateNumber(),
            'client_name' => $data['client_name'],
            'fence_height_ft' => $calc['inputs']['fence_height_ft'],
            'board_style' => $calc['inputs']['board_style'],
            'overlap_in' => $calc['inputs']['overlap_in'],
            'gate_count' => $calc['inputs']['gate_count'],
            'sides' => $calc['inputs']['sides'],
            'calculation' => $calc,
            'materials_subtotal' => $calc['totals']['materials_subtotal'],
            'hst' => $calc['totals']['hst'],
            'labour_total' => $calc['totals']['labour_subtotal'],
            'grand_total' => $calc['totals']['grand_total'],
            'version' => 1,
        ]);

        return redirect()
            ->route('admin.quotations.fence.edit', $quote)
            ->with('success', 'Fence quotation '.$quote->quote_number.' saved.');
    }

    /** Open an existing quote back in the calculator for editing. */
    public function edit(FenceQuotation $fence_quotation)
    {
        $settings = FenceFormulaSetting::current();
        $materials = FenceMaterial::query()->orderBy('sort_order')->get();

        $zeroPricedKeys = $materials
            ->filter(fn (FenceMaterial $m) => (float) $m->price_cad <= 0)
            ->pluck('name')
            ->values()
            ->all();

        // Projects we'd allow linking to: everything that isn't rejected
        // or completed. The UI uses this to populate the "Link to project"
        // dropdown.
        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED])
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        return view('admin.quotations.fence.create', [
            'quotation' => $fence_quotation,
            'settings' => $settings,
            'materials' => $materials,
            'zeroPricedMaterials' => $zeroPricedKeys,
            'linkableProjects' => $linkableProjects,
        ]);
    }

    /**
     * Update an existing quote — recalculates the snapshot and bumps
     * the version. We keep the original PDF on disk until the next
     * download/print so an in-flight email still works.
     */
    public function update(Request $request, FenceQuotation $fence_quotation)
    {
        $data = $this->validateFenceInputs($request);

        $calc = (new FenceQuotationCalculator())->calculate($data);

        $fence_quotation->fill([
            'client_name' => $data['client_name'],
            'fence_height_ft' => $calc['inputs']['fence_height_ft'],
            'board_style' => $calc['inputs']['board_style'],
            'overlap_in' => $calc['inputs']['overlap_in'],
            'gate_count' => $calc['inputs']['gate_count'],
            'sides' => $calc['inputs']['sides'],
            'calculation' => $calc,
            'materials_subtotal' => $calc['totals']['materials_subtotal'],
            'hst' => $calc['totals']['hst'],
            'labour_total' => $calc['totals']['labour_subtotal'],
            'grand_total' => $calc['totals']['grand_total'],
            'version' => $fence_quotation->version + 1,
        ])->save();

        return redirect()
            ->route('admin.quotations.fence.edit', $fence_quotation)
            ->with('success', 'Fence quotation '.$fence_quotation->quote_number.' updated (v'.$fence_quotation->version.').');
    }

    /**
     * Lightweight JSON endpoint used by the calculator form to preview
     * the breakdown live, without persisting anything.
     */
    public function preview(Request $request)
    {
        // Preview accepts the same inputs as store() but doesn't require
        // a client name (yet).
        $data = $request->validate([
            'sides' => ['required', 'array', 'min:1', 'max:4'],
            'sides.*.length_ft' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'fence_height_ft' => ['required', 'numeric', Rule::in([6, 8])],
            'board_style' => ['required', Rule::in([FenceQuotation::BOARD_STYLE_STANDARD, FenceQuotation::BOARD_STYLE_OVERLAPPING])],
            'overlap_in' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'gate_count' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $calc = (new FenceQuotationCalculator())->calculate($data);

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
        if ($fence_quotation->isLinked()) {
            return back()->with('error', 'This fence quotation is already linked to a project.');
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);

        if (in_array($project->status, [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED], true)) {
            return back()->with('error', 'Cannot link to a rejected or completed project.');
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
        if ($fence_quotation->isLinked()) {
            return back()->with('error', 'This fence quotation is already linked to a project.');
        }

        $this->normaliseFenceProjectPhoneOnRequest($request);

        $data = $request->validate([
            'fp_project_name' => ['nullable', 'string', 'max:255'],
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
            $projectTitle = $clientName.' — Fence';
        }
        $projectTitle = Str::limit($projectTitle, 255, '');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $fence_quotation) {
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

            $this->appendFenceLineToProjectQuotation($project, $fence_quotation);

            return $project;
        });

        $statusService->onCreated($project, false);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created (fixed price). The fence line item was added to the Quotation tab.');
    }

    /**
     * Append this fence quote as its own {@see ClientProjectQuotation} row
     * (draft, linked to the fence record). Totals roll into Financials once
     * the quotation is accepted.
     */
    protected function appendFenceLineToProjectQuotation(ClientProject $project, FenceQuotation $fence_quotation): void
    {
        $lineAmount = round(
            (float) $fence_quotation->materials_subtotal + (float) $fence_quotation->labour_total,
            2
        );

        $items = [[
            'description' => $fence_quotation->summaryDescription()
                .' (see fence quote '.$fence_quotation->quote_number.')',
            'amount' => $lineAmount,
            'apply_hst' => true,
        ]];

        $snapshot = $fence_quotation->only([
            'quote_number',
            'client_name',
            'fence_height_ft',
            'board_style',
            'gate_count',
            'sides',
            'calculation',
            'materials_subtotal',
            'hst',
            'labour_total',
            'grand_total',
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
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();

        $fence_quotation->update([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ]);
    }

    /**
     * Strip non-digits from the "new project" phone field before validation
     * (same behaviour as ClientProjectController::normalisePhoneOnRequest).
     */
    protected function normaliseFenceProjectPhoneOnRequest(Request $request): void
    {
        if (!$request->has('fp_client_phone')) {
            return;
        }

        $raw = (string) $request->input('fp_client_phone');
        $digits = preg_replace('/\D/', '', $raw);

        $request->merge([
            'fp_client_phone' => $digits === '' ? null : $digits,
        ]);
    }

    public function destroy(FenceQuotation $fence_quotation)
    {
        $fence_quotation->delete();

        return redirect()
            ->route('admin.quotations.fence.index')
            ->with('success', 'Fence quotation deleted.');
    }

    /**
     * Printable purchasing list (e.g. Home Depot) for a saved quote.
     * Quantities come from the stored calculation; long name, SKU, and
     * model come from the live materials catalogue when set.
     */
    public function purchaseList(FenceQuotation $fence_quotation)
    {
        $calc = $fence_quotation->calculation;
        $rows = [];

        if (is_array($calc) && !empty($calc['materials'])) {
            $byKey = collect($calc['materials'])->keyBy('key');
            $catalogue = FenceMaterial::catalogueByKey();

            foreach (FenceMaterial::PURCHASE_LIST_KEYS as $key) {
                $line = $byKey->get($key);
                if (!$line) {
                    continue;
                }
                $quantity = (float) ($line['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                /** @var FenceMaterial|null $mat */
                $mat = $catalogue->get($key);
                $qtyPart = $this->formatPurchaseQuantityLabel($quantity);

                $longName = $mat && filled($mat->long_name)
                    ? $mat->long_name
                    : (filled($line['long_name'] ?? null) ? (string) $line['long_name'] : (string) ($mat?->name ?? $line['name'] ?? $key));

                $storeSku = $mat && filled($mat->store_sku)
                    ? $mat->store_sku
                    : (filled($line['store_sku'] ?? null) ? (string) $line['store_sku'] : null);

                $model = $mat && filled($mat->model)
                    ? $mat->model
                    : (filled($line['model'] ?? null) ? (string) $line['model'] : null);

                $rows[] = [
                    'quantity_label' => $qtyPart,
                    'long_name_html' => $this->purchaseListCellHtml($longName),
                    'store_sku_html' => $storeSku !== null ? $this->purchaseListCellHtml($storeSku) : null,
                    'model_html' => $model !== null ? $this->purchaseListCellHtml($model) : null,
                ];
            }
        }

        return view('admin.quotations.fence.purchase_list', [
            'quotation' => $fence_quotation,
            'rows' => $rows,
        ]);
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
    protected function validateFenceInputs(Request $request): array
    {
        $rules = [
            'client_name' => ['required', 'string', 'max:160'],
            'sides' => ['required', 'array', 'min:1', 'max:4'],
            'sides.*.length_ft' => ['required', 'numeric', 'min:0.5', 'max:5000'],
            'fence_height_ft' => ['required', 'numeric', Rule::in([6, 8])],
            'board_style' => ['required', Rule::in([
                FenceQuotation::BOARD_STYLE_STANDARD,
                FenceQuotation::BOARD_STYLE_OVERLAPPING,
            ])],
            'overlap_in' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'gate_count' => ['required', 'integer', 'min:0', 'max:10'],
        ];

        $data = $request->validate($rules);

        // Overlap is irrelevant in standard-gap mode; normalise it here
        // so the form doesn't smuggle a stale value.
        if ($data['board_style'] !== FenceQuotation::BOARD_STYLE_OVERLAPPING) {
            $data['overlap_in'] = 0;
        }

        return $data;
    }
}
