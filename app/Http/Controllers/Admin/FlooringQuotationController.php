<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationAdditionalCosts;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FlooringQuotation;
use App\Models\FlooringType;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\FlooringQuotationCalculator;
use App\Services\QuotationPaymentSchedule;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\SafeOutboundHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlooringQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            FlooringQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.flooring.index', [
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

        $quote = FlooringQuotation::create(array_merge($calc, [
            'quote_number' => FlooringQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkFlooringIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.flooring.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Flooring quotation saved as draft and linked.' : 'Flooring quotation saved as draft.')
                : ($linkedNow
                    ? 'Flooring quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Flooring quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, FlooringQuotation $flooring_quotation)
    {
        return $this->formView($flooring_quotation, $request);
    }

    public function update(Request $request, FlooringQuotation $flooring_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $flooring_quotation);

        $flooring_quotation->fill(array_merge($calc, [
            'version' => $flooring_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($flooring_quotation->isLinked() && $flooring_quotation->client_project_id) {
            $project = ClientProject::query()->find($flooring_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $flooring_quotation) {
                    $this->syncFlooringLineToProjectQuotation($project, $flooring_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkFlooringIfRequested($flooring_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.flooring.edit', $flooring_quotation)
            ->with('success', $linkedNow
                ? 'Flooring quotation '.$flooring_quotation->quote_number.' updated and linked.'
                : 'Flooring quotation '.$flooring_quotation->quote_number.' updated (v'.$flooring_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request, ?FlooringQuotation $flooring_quotation = null)
    {
        $raw = $this->gatherInputsFromRequest($request, true);
        $calc = (new FlooringQuotationCalculator)->calculate($raw);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, FlooringQuotation $flooring_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($flooring_quotation, 'flooring')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($flooring_quotation, $project);

        if ($flooring_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $flooring_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $flooring_quotation) {
            $this->appendFlooringLineToProjectQuotation($project, $flooring_quotation);
        });

        return redirect()
            ->route('admin.quotations.flooring.edit', $flooring_quotation)
            ->with('success', 'Linked to project "'.$project->name.'".');
    }

    public function createProjectAndLink(
        Request $request,
        FlooringQuotation $flooring_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($flooring_quotation, 'flooring')) {
            return $resp;
        }
        if ($flooring_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $flooring_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before creating a project.');
        }

        $this->normalisePhoneOnRequest($request);

        if (! filled(trim((string) $request->input('fp_client_address', '')))) {
            $request->merge([
                'fp_client_address' => null,
                'fp_client_address_place_id' => null,
                'fp_client_address_latitude' => null,
                'fp_client_address_longitude' => null,
            ]);
        }

        $data = $request->validate([
            'fp_project_name' => ['nullable', 'string', 'max:255'],
            'fp_client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'fp_client_name' => ['required', 'string', 'max:255'],
            'fp_client_email' => ['nullable', 'email', 'max:255'],
            'fp_client_phone' => ['nullable', 'digits:10'],
            'fp_client_address' => ['nullable', 'string', 'max:500'],
            'fp_client_address_place_id' => ['nullable', 'string', 'max:255'],
            'fp_client_address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'fp_client_address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'fp_client_phone.digits' => 'Phone number must have exactly 10 digits.',
        ]);

        $clientName = trim($data['fp_client_name']);
        $projectTitle = $this->resolveNewProjectTitle($data['fp_project_name'] ?? null, $clientName, 'Flooring');

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $flooring_quotation) {
            $project = ClientProject::create([
                'name' => $projectTitle,
                'client_id' => $data['fp_client_id'] ?: null,
                'client_name' => $clientName,
                'client_email' => $data['fp_client_email'] ?: null,
                'client_phone' => $data['fp_client_phone'] ?: null,
                'client_address' => $data['fp_client_address'] ?: null,
                'client_address_place_id' => $data['fp_client_address_place_id'] ?: null,
                'client_address_latitude' => $data['fp_client_address_latitude'] ?? null,
                'client_address_longitude' => $data['fp_client_address_longitude'] ?? null,
                'billing_type' => ClientProject::BILLING_FIXED,
                'hourly_rate' => null,
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'status_changed_at' => now(),
            ]);

            $this->appendFlooringLineToProjectQuotation($project, $flooring_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The flooring line was added to the Quotation tab.');
    }

    public function destroy(FlooringQuotation $flooring_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_FLOORING)
            ->where('source_id', $flooring_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $flooring_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.flooring.index')
            ->with('success', 'Flooring quotation deleted.');
    }

    protected function formView(?FlooringQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();

        $settings = \App\Models\FlooringSettings::current();
        $flooringTypes = FlooringType::query()->activeOrdered()->get();

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
                ->where('source_type', ClientProjectQuotation::SOURCE_FLOORING)
                ->where('source_id', $quotation->id)
                ->first();
        }

        $previewInputs = FlooringQuotationCalculator::defaultInputs();
        if ($quotation && is_array($quotation->inputs)) {
            foreach ($quotation->inputs as $key => $value) {
                $previewInputs[$key] = $value;
            }
        }
        $initialPreview = (new FlooringQuotationCalculator)->calculate(
            $previewInputs,
            $settings,
            $flooringTypes
        );

        return view('admin.quotations.flooring.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'flooringTypes' => $flooringTypes,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $linkedCpq,
            'initialPreview' => $initialPreview,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCalculation(Request $request, string $intent, ?FlooringQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:5000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], QuotationPaymentSchedule::validationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules()), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), DebrisRemovalExpenseService::validationMessages(), AdditionalCostsService::validationMessages()));

        $rawInputs = $this->gatherInputsFromRequest($request, $draft);
        if (! $draft) {
            $engine = new FlooringQuotationCalculator;
            $hints = $engine->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
        }

        $calc = (new FlooringQuotationCalculator)->calculate($rawInputs);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);
        $workDescription = trim((string) $request->input('work_description'));

        $localImages = $existing->inputs['material_local_images'] ?? [];
        if (! $draft && ! empty($rawInputs['include_material_details'])) {
            $urls = [
                $rawInputs['material_image_url_1'] ?? '',
                $rawInputs['material_image_url_2'] ?? '',
                $rawInputs['material_image_url_3'] ?? '',
                $rawInputs['material_image_url_4'] ?? '',
            ];
            
            $newLocalImages = [];
            foreach ($urls as $i => $url) {
                if ($url !== '') {
                    $hash = md5($url);
                    $filename = "quotations/materials/{$hash}.jpg";
                    $absolutePath = storage_path("app/{$filename}");
                    
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($filename)) {
                        $newLocalImages[] = $absolutePath;
                    } else {
                        try {
                            $response = SafeOutboundHttp::get($url, [
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            ], 30);
                            if ($response && $response->successful() && str_starts_with((string) $response->header('Content-Type', ''), 'image/')) {
                                \Illuminate\Support\Facades\Storage::disk('local')->put($filename, $response->body());
                                $newLocalImages[] = $absolutePath;
                            } else {
                                $newLocalImages[] = $url;
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning('Image download failed, falling back to remote URL', ['url' => $url, 'error' => $e->getMessage()]);
                            $newLocalImages[] = $url;
                        }
                    }
                }
            }
            $localImages = $newLocalImages;
        }
        $rawInputs['material_local_images'] = $localImages;

        $base = array_merge([
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription === '' ? null : $workDescription,
            'is_draft' => $draft,
            'inputs' => $rawInputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ], $this->paymentScheduleFromRequest($request));

        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'flooring_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($base, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'flooring_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request, bool $draft = false): array
    {
        if ($draft) {
            $data = $request->validate([
                'flooring_type_id' => ['nullable', 'integer', 'exists:flooring_types,id'],
                'square_footage' => ['nullable', 'numeric', 'min:0'],
                'shoe_moulding_linear_ft' => ['nullable', 'numeric', 'min:0'],
                'closet_door_qty' => ['nullable', 'integer', 'min:0'],
                'material_cost_per_sqft' => ['nullable', 'numeric', 'min:0'],
                'material_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'material_delivery_fee' => ['nullable', 'numeric', 'min:0'],
                'material_name' => ['nullable', 'string', 'max:160'],
                'material_color' => ['nullable', 'string', 'max:120'],
                'material_url' => ['nullable', 'url', 'max:500'],
                'material_image_url_1' => ['nullable', 'url', 'max:500'],
                'material_image_url_2' => ['nullable', 'url', 'max:500'],
                'material_image_url_3' => ['nullable', 'url', 'max:500'],
                'material_image_url_4' => ['nullable', 'url', 'max:500'],
            ]);
        } else {
            $data = $request->validate([
                'flooring_type_id' => ['required', 'integer', 'exists:flooring_types,id'],
                'square_footage' => ['required', 'numeric', 'min:0.01'],
                'shoe_moulding_linear_ft' => ['nullable', 'numeric', 'min:0'],
                'closet_door_qty' => ['nullable', 'integer', 'min:0'],
                'material_cost_per_sqft' => ['nullable', 'numeric', 'min:0'],
                'material_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'material_delivery_fee' => ['nullable', 'numeric', 'min:0'],
                'material_name' => ['nullable', 'string', 'max:160'],
                'material_color' => ['nullable', 'string', 'max:120'],
                'material_url' => ['nullable', 'url', 'max:500'],
                'material_image_url_1' => ['nullable', 'url', 'max:500'],
                'material_image_url_2' => ['nullable', 'url', 'max:500'],
                'material_image_url_3' => ['nullable', 'url', 'max:500'],
                'material_image_url_4' => ['nullable', 'url', 'max:500'],
            ]);
        }

        return [
            'flooring_type_id' => isset($data['flooring_type_id']) ? (int) $data['flooring_type_id'] : null,
            'square_footage' => round(max(0, (float) ($data['square_footage'] ?? 0)), 2),
            'remove_carpet' => $request->boolean('remove_carpet'),
            'rescrew_subfloor' => $request->boolean('rescrew_subfloor'),
            'install_shoe_moulding' => $request->boolean('install_shoe_moulding'),
            'shoe_moulding_linear_ft' => round(max(0, (float) ($data['shoe_moulding_linear_ft'] ?? 0)), 2),
            'caulk_paint_shoe_moulding' => $request->boolean('caulk_paint_shoe_moulding'),
            'closet_doors' => $request->boolean('closet_doors'),
            'closet_door_qty' => max(0, (int) ($data['closet_door_qty'] ?? 0)),
            'include_material_details' => $request->boolean('include_material_details'),
            'material_cost_per_sqft' => round(max(0, (float) ($data['material_cost_per_sqft'] ?? 0)), 4),
            'material_waste_percent' => round(max(0, (float) ($data['material_waste_percent'] ?? 5)), 2),
            'material_delivery_fee' => round(max(0, (float) ($data['material_delivery_fee'] ?? 60)), 2),
            'material_name' => trim((string) ($data['material_name'] ?? '')),
            'material_color' => trim((string) ($data['material_color'] ?? '')),
            'material_url' => trim((string) ($data['material_url'] ?? '')),
            'material_image_url_1' => trim((string) ($data['material_image_url_1'] ?? '')),
            'material_image_url_2' => trim((string) ($data['material_image_url_2'] ?? '')),
            'material_image_url_3' => trim((string) ($data['material_image_url_3'] ?? '')),
            'material_image_url_4' => trim((string) ($data['material_image_url_4'] ?? '')),
        ];
    }

    protected function autoLinkFlooringIfRequested(FlooringQuotation $fq, Request $request, array &$warnings): bool
    {
        if ($fq->isLinked() || $fq->is_draft) {
            return false;
        }

        $project = $this->quotationLinkCandidateProject($request);
        if (! $project || ! $this->quotationProjectAllowsNewLink($project)) {
            if ($project) {
                $warnings[] = 'This project cannot accept linked quotations anymore. Save your work and link manually.';
            }

            return false;
        }

        if ((float) $fq->final_quote_total <= 0) {
            $warnings[] = 'Set a final quote total greater than zero before it can attach to the project.';

            return false;
        }

        try {
            DB::transaction(function () use ($project, $fq) {
                $this->appendFlooringLineToProjectQuotation($project, $fq->fresh());
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncFlooringLineToProjectQuotation(ClientProject $project, FlooringQuotation $flooringQuotation): void
    {
        $items = [[
            'description' => 'Flooring installation (labor)',
            'amount' => $flooringQuotation->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $flooringQuotation->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $inputs = is_array($flooringQuotation->inputs) ? $flooringQuotation->inputs : [];
            $materialName = trim((string) ($inputs['material_name'] ?? ''));
            $label = $materialName !== '' ? 'Materials — '.$materialName : 'Client-selected flooring material';
            $label .= "\nNote: Material price is subject to change depending on store price at the time of purchase.";
            $items[] = [
                'description' => $label,
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }

        $this->appendDebrisRemovalProjectLine($items, $flooringQuotation);
        $this->appendAdditionalCostsProjectLines($items, $flooringQuotation);

        $snapshot = $flooringQuotation->only([
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
            'source_type' => ClientProjectQuotation::SOURCE_FLOORING,
            'source_id' => $flooringQuotation->id,
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
        $row->material = $flooringQuotation->includesMaterialCharge();
        $row->title = $flooringQuotation->displayTitle();
        $row->items = $items;
        $row->work_description = $flooringQuotation->scopeDescriptionForPdf();
        $this->applyPaymentScheduleToProjectQuotation($row, $flooringQuotation);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendFlooringLineToProjectQuotation(ClientProject $project, FlooringQuotation $flooringQuotation): void
    {
        $this->syncFlooringLineToProjectQuotation($project, $flooringQuotation);

        $flooringQuotation->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }
}
