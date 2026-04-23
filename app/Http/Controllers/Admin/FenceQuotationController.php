<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Services\FenceQuotationCalculator;
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
        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        /** @var ClientProject $project */
        $project = ClientProject::query()->findOrFail($data['client_project_id']);

        if (in_array($project->status, [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED], true)) {
            return back()->with('error', 'Cannot link to a rejected or completed project.');
        }

        // Append the fence line to the project's main quotation. If the
        // project has no quotation yet, create a minimal draft so the
        // line item has somewhere to live.
        DB::transaction(function () use ($project, $fence_quotation) {
            /** @var ClientProjectQuotation $projectQuote */
            $projectQuote = $project->quotation()->firstOrNew([]);

            if (!$projectQuote->exists) {
                $projectQuote->client_project_id = $project->id;
                $projectQuote->type = ClientProjectQuotation::TYPE_QUOTATION;
                $projectQuote->labour = true;
                $projectQuote->material = true;
                $projectQuote->quotation_number = ClientProjectQuotation::generateNumber(
                    ClientProjectQuotation::TYPE_QUOTATION
                );
                $projectQuote->items = [];
                $projectQuote->version = 1;
            }

            $items = is_array($projectQuote->items) ? $projectQuote->items : [];
            $items[] = [
                'description' => $fence_quotation->summaryDescription()
                    .' (see fence quote '.$fence_quotation->quote_number.')',
                // We bundle materials + HST + labour into one amount so the
                // project quotation's own HST line doesn't double-charge.
                // The project quotation recalculates HST on this single
                // number using its own items-subtotal rule, so we pass the
                // pre-tax portion only (materials + labour).
                'amount' => round(
                    (float) $fence_quotation->materials_subtotal + (float) $fence_quotation->labour_total,
                    2
                ),
            ];

            $projectQuote->items = $items;
            $projectQuote->save();

            $fence_quotation->update([
                'client_project_id' => $project->id,
                'linked_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.quotations.fence.edit', $fence_quotation)
            ->with('success', 'Linked to project "'.$project->name.'". A line item was added to the project\'s Quotation.');
    }

    public function destroy(FenceQuotation $fence_quotation)
    {
        $fence_quotation->delete();

        return redirect()
            ->route('admin.quotations.fence.index')
            ->with('success', 'Fence quotation deleted.');
    }

    // =================================================================
    //   Helpers
    // =================================================================

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
