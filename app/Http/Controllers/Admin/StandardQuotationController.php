<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use App\Services\ClientProjectStatusService;
use App\Services\StandardQuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Standalone Standard quotation (materials table + labour).
 */
class StandardQuotationController extends Controller
{
    public function index()
    {
        $quotations = StandardQuotation::query()
            ->with('project')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.quotations.standard.index', [
            'quotations' => $quotations,
        ]);
    }

    public function create()
    {
        return view('admin.quotations.standard.create', [
            'quotation' => null,
            'linkableProjects' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $calc = $this->validatedSnapshot($request, new StandardQuotationCalculator());

        $quote = StandardQuotation::create(array_merge($calc['persistence'], [
            'quote_number' => StandardQuotation::generateNumber(),
            'version' => 1,
        ]));

        return redirect()
            ->route('admin.quotations.standard.edit', $quote)
            ->with('success', 'Standard quotation '.$quote->quote_number.' saved.')
            ->with('standard_flash', 'saved');
    }

    public function edit(StandardQuotation $standard_quotation)
    {
        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED])
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        return view('admin.quotations.standard.create', [
            'quotation' => $standard_quotation,
            'linkableProjects' => $linkableProjects,
        ]);
    }

    public function update(Request $request, StandardQuotation $standard_quotation)
    {
        $calc = $this->validatedSnapshot($request, new StandardQuotationCalculator());

        $standard_quotation->fill(array_merge($calc['persistence'], [
            'version' => $standard_quotation->version + 1,
        ]))->save();

        return redirect()
            ->route('admin.quotations.standard.edit', $standard_quotation)
            ->with('success', 'Standard quotation '.$standard_quotation->quote_number.' updated (v'.$standard_quotation->version.').')
            ->with('standard_flash', 'saved');
    }

    public function linkToProject(Request $request, StandardQuotation $standard_quotation)
    {
        if ($standard_quotation->isLinked()) {
            return back()->with('error', 'This standard quotation is already linked to a project.');
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

        if (in_array($project->status, [ClientProject::STATUS_REJECTED, ClientProject::STATUS_COMPLETED], true)) {
            return back()->with('error', 'Cannot link to a rejected or completed project.');
        }

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = 'Standard quotation';
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
        if ($standard_quotation->isLinked()) {
            return back()->with('error', 'This standard quotation is already linked to a project.');
        }

        if ((float) $standard_quotation->grand_total <= 0) {
            return back()->with('error', 'Set a grand total greater than zero before creating a project.');
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
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits (e.g. (123) 456-7890).',
            'fp_client_email.email' => 'Please enter a valid email address.',
        ]);

        $lineDesc = trim((string) ($data['link_line_description'] ?? ''));
        if ($lineDesc === '') {
            $lineDesc = 'Standard quotation';
        }

        $clientName = trim($data['fp_client_name']);
        $projectTitle = trim((string) ($data['fp_project_name'] ?? ''));
        if ($projectTitle === '') {
            $projectTitle = $clientName.' — Standard';
        }
        $projectTitle = Str::limit($projectTitle, 255, '');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $standard_quotation, $lineDesc) {
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

            $this->appendStandardLineToProjectQuotation($project, $standard_quotation, $lineDesc);

            return $project;
        });

        $statusService->onCreated($project, false);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created (fixed price). The standard quotation line was added to the Quotation tab.');
    }

    public function destroy(StandardQuotation $standard_quotation)
    {
        $standard_quotation->delete();

        return redirect()
            ->route('admin.quotations.standard.index')
            ->with('success', 'Standard quotation deleted.');
    }

    /**
     * @return array{persistence: array<string, mixed>}
     */
    protected function validatedSnapshot(Request $request, StandardQuotationCalculator $calculator): array
    {
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:160'],
            'work_description' => ['nullable', 'string', 'max:5000'],
            'materials' => ['nullable', 'array'],
            'materials.*.short_name' => ['nullable', 'string', 'max:60'],
            'materials.*.description' => ['nullable', 'string', 'max:500'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.apply_hst' => ['nullable'],
            'labour_hourly_rate' => ['required', 'numeric', 'min:0'],
            'labour_mode' => ['required', Rule::in(['hours', 'days'])],
            'labour_quantity' => ['required', 'numeric', 'min:0', 'max:100000'],
            'contingency_enabled' => ['nullable'],
            'contingency_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'client_name.required' => 'Client name is required.',
            'labour_hourly_rate.required' => 'Hourly rate is required.',
            'contingency_percent.numeric' => 'Contingency percent must be a number.',
            'contingency_percent.max' => 'Contingency percent cannot exceed 100.',
        ]);

        $materialRows = $data['materials'] ?? [];
        if (!is_array($materialRows)) {
            $materialRows = [];
        }
        foreach ($materialRows as &$mRow) {
            $mRow['apply_hst'] = filter_var($mRow['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN);
        }
        unset($mRow);

        $contingencyEnabled = filter_var($data['contingency_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contingencyPercent = $data['contingency_percent'] ?? null;
        if ($contingencyPercent === null || $contingencyPercent === '') {
            $contingencyPercent = 10.0;
        }

        $computed = $calculator->compute(
            $materialRows,
            (float) $data['labour_hourly_rate'],
            (string) $data['labour_mode'],
            (float) $data['labour_quantity'],
            $contingencyEnabled,
            (float) $contingencyPercent
        );

        $materialsForStorage = array_map(static function (array $m): array {
            return [
                'short_name' => $m['short_name'] ?? '',
                'description' => $m['description'],
                'unit_price' => $m['unit_price'],
                'quantity' => $m['quantity'],
                'apply_hst' => $m['apply_hst'],
            ];
        }, $computed['materials']);

        $workDescription = trim((string) ($data['work_description'] ?? ''));

        return [
            'persistence' => [
                'client_name' => trim($data['client_name']),
                'work_description' => $workDescription === '' ? null : $workDescription,
                'materials' => $materialsForStorage,
                'labour_mode' => $data['labour_mode'],
                'labour_quantity' => round((float) $data['labour_quantity'], 2),
                'labour_hours' => $computed['labour_hours'],
                'labour_hourly_rate' => round((float) $data['labour_hourly_rate'], 2),
                'materials_subtotal_before_hst' => $computed['materials_subtotal_before_hst'],
                'materials_hst' => $computed['materials_hst'],
                'materials_with_hst' => $computed['materials_with_hst'],
                'labour_total' => $computed['labour_total'],
                'grand_total' => $computed['grand_total'],
                'contingency_enabled' => $computed['contingency_enabled'],
                'contingency_percent' => $computed['contingency_percent'],
                'contingency_amount' => $computed['contingency_amount'],
                'contingency_hst' => $computed['contingency_hst'],
            ],
        ];
    }

    protected function appendStandardLineToProjectQuotation(
        ClientProject $project,
        StandardQuotation $standardQuotation,
        string $lineDescription
    ): void {
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

        // Single tax-inclusive bundle: materials (with per-row HST) + untaxed labour.
        // apply_hst false keeps the project quotation from adding another 13%.
        $items[] = [
            'description' => $lineDescription.' (see '.$standardQuotation->quote_number.')',
            'amount' => round((float) $standardQuotation->grand_total, 2),
            'apply_hst' => false,
        ];

        $projectQuote->items = $items;

        // Carry the standard quotation's "Description of the work" onto
        // the project quotation as a top-level field. Rendered as its
        // own block on the PDF / preview. If the project quotation
        // already has its own description, append (separated by a blank
        // line) so nothing the user wrote gets lost.
        $standardWorkDescription = trim((string) ($standardQuotation->work_description ?? ''));
        if ($standardWorkDescription !== '') {
            $existingDescription = trim((string) ($projectQuote->work_description ?? ''));
            $projectQuote->work_description = $existingDescription === ''
                ? $standardWorkDescription
                : $existingDescription."\n\n".$standardWorkDescription;
        }

        $projectQuote->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();

        $standardQuotation->update([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ]);
    }

    protected function normalisePhoneOnRequest(Request $request): void
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
}
