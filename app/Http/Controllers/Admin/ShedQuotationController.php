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
use App\Models\ShedQuotation;
use App\Models\ShedSettings;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Services\ShedQuotationCalculator;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\QuotationAfterSaveRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShedQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            ShedQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.shed.index', [
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

        $quote = ShedQuotation::create(array_merge($calc, [
            'quote_number' => ShedQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.shed.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Shed quotation saved as draft and linked.' : 'Shed quotation saved as draft.')
                : ($linkedNow
                    ? 'Shed quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Shed quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, ShedQuotation $shed_quotation)
    {
        return $this->formView($shed_quotation, $request);
    }

    public function update(Request $request, ShedQuotation $shed_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $shed_quotation);

        $shed_quotation->fill(array_merge($calc, [
            'version' => $shed_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($shed_quotation->isLinked() && $shed_quotation->client_project_id) {
            $project = ClientProject::query()->find($shed_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $shed_quotation) {
                    $this->syncLineToProjectQuotation($project, $shed_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($shed_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.shed.edit', $shed_quotation)
            ->with('success', $linkedNow
                ? 'Shed quotation '.$shed_quotation->quote_number.' updated and linked.'
                : 'Shed quotation '.$shed_quotation->quote_number.' updated (v'.$shed_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request, ?ShedQuotation $shed_quotation = null)
    {
        $raw = $this->gatherInputsFromRequest($request);
        $calc = (new ShedQuotationCalculator)->calculate($raw);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, ShedQuotation $shed_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($shed_quotation, 'shed')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($shed_quotation, $project);

        if ($shed_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $shed_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $shed_quotation) {
            $this->appendLineToProjectQuotation($project, $shed_quotation);
        });

        return redirect()
            ->route('admin.quotations.shed.edit', $shed_quotation)
            ->with('success', 'Linked to project "'.$project->name.'".');
    }

    public function createProjectAndLink(
        Request $request,
        ShedQuotation $shed_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($shed_quotation, 'shed')) {
            return $resp;
        }
        if ($shed_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $shed_quotation->final_quote_total <= 0) {
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
            'fp_client_id' => ['nullable', 'integer', 'exists:clients,id'],
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
        $projectTitle = $this->resolveNewProjectTitle(
            $data['fp_project_name'] ?? null,
            $clientName,
            $shed_quotation->projectTitleSuffix()
        );

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $shed_quotation) {
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

            $this->appendLineToProjectQuotation($project, $shed_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The shed line was added to the Quotation tab.');
    }

    public function destroy(ShedQuotation $shed_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_SHED)
            ->where('source_id', $shed_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $shed_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.shed.index')
            ->with('success', 'Shed quotation deleted.');
    }

    protected function formView(?ShedQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();
        $settings = ShedSettings::current();

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
            $linkedCpq = ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_SHED,
                (int) $quotation->id
            );
        }

        $previewInputs = ShedQuotationCalculator::defaultInputs();
        $previewInputs['owner_hourly_rate'] = (float) $settings->owner_hourly_rate;
        $previewInputs['helper_hourly_rate'] = (float) $settings->helper_hourly_rate;
        if ($quotation && is_array($quotation->inputs)) {
            foreach ($quotation->inputs as $key => $value) {
                $previewInputs[$key] = $value;
            }
        }
        $previewInputs['roof_type'] = ShedQuotationCalculator::normalizeRoofType($previewInputs['roof_type'] ?? 'asphalt');
        $previewInputs['siding'] = ShedQuotationCalculator::normalizeSiding($previewInputs['siding'] ?? 'vinyl');
        $initialPreview = (new ShedQuotationCalculator)->calculate($previewInputs, $settings);

        return view('admin.quotations.shed.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'sections' => ShedQuotationCalculator::SECTIONS,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $linkedCpq,
            'initialPreview' => $initialPreview,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCalculation(Request $request, string $intent, ?ShedQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:5000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules()), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages(), AdditionalCostsService::validationMessages()));

        $rawInputs = $this->gatherInputsFromRequest($request);
        if (! $draft) {
            $hints = (new ShedQuotationCalculator)->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
        }

        $calc = (new ShedQuotationCalculator)->calculate($rawInputs);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);
        $workDescription = trim((string) $request->input('work_description'));

        $base = array_merge([
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription === '' ? null : $workDescription,
            'is_draft' => $draft,
            'inputs' => $rawInputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'shed_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'shed_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'shed_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request): array
    {
        $defaults = ShedQuotationCalculator::defaultInputs();
        $floats = [
            'length_ft', 'width_ft', 'wall_height_ft', 'overhang_in',
            'pad_thickness_in', 'gravel_depth_in',
            'owner_hourly_rate', 'helper_hourly_rate',
        ];
        $optionalInt = ['pile_qty', 'skid_qty', 'block_qty'];
        $ints = [
            'roof_pitch', 'joist_spacing_in', 'stud_spacing_in',
            'door_qty', 'window_qty', 'outlet_qty', 'light_qty',
        ];
        $bools = [
            'attached', 'include_site_level', 'wall_sheathing', 'double_door',
            'include_electrical', 'include_subpanel', 'include_loft',
            'include_paint', 'include_ramp', 'include_gutters', 'include_plumbing',
            'client_provides_all', 'include_helper',
        ];
        $strings = [
            'shed_type', 'foundation', 'joist_size', 'floor_sheathing',
            'stud_size', 'siding', 'roof_type', 'door_type', 'window_type', 'interior_finish',
        ];

        $out = $defaults;
        foreach ($floats as $key) {
            $out[$key] = round(max(0, (float) $request->input($key, $defaults[$key])), 4);
        }
        foreach ($optionalInt as $key) {
            $raw = $request->input($key);
            if ($raw === null || $raw === '') {
                $out[$key] = null;
            } else {
                $out[$key] = max(0, (int) $raw);
            }
        }
        foreach ($ints as $key) {
            $out[$key] = max(0, (int) $request->input($key, $defaults[$key]));
        }
        foreach ($bools as $key) {
            $out[$key] = $request->boolean($key);
        }
        foreach ($strings as $key) {
            $out[$key] = trim((string) $request->input($key, $defaults[$key]));
        }
        $out['shed_type'] = ShedQuotationCalculator::normalizeShedType($out['shed_type']);
        if (! array_key_exists($out['foundation'], ShedQuotationCalculator::FOUNDATIONS)) {
            $out['foundation'] = 'skids';
        }
        $out['siding'] = ShedQuotationCalculator::normalizeSiding($out['siding']);
        $out['roof_type'] = ShedQuotationCalculator::normalizeRoofType($out['roof_type']);
        if (! array_key_exists($out['door_type'], ShedQuotationCalculator::DOOR_TYPES)) {
            $out['door_type'] = 'custom';
        }
        if (! array_key_exists($out['window_type'], ShedQuotationCalculator::WINDOW_TYPES)) {
            $out['window_type'] = 'slider';
        }
        $out['interior_finish'] = $out['interior_finish'] === 'finished' ? 'finished' : 'rough';
        $out['joist_size'] = $out['joist_size'] === '2x4' ? '2x4' : '2x6';
        $out['stud_size'] = $out['stud_size'] === '2x6' ? '2x6' : '2x4';
        $out['floor_sheathing'] = $out['floor_sheathing'] === 'plywood_34' ? 'plywood_34' : 'osb_58';

        $sections = array_keys(ShedQuotationCalculator::SECTIONS);
        $provided = $request->input('client_provides_sections', []);
        $out['client_provides_sections'] = is_array($provided)
            ? array_values(array_intersect($sections, array_map('strval', $provided)))
            : [];

        $out['labor_override'] = $this->nestedNumericMap($request->input('labor_override'), ['hours']);
        $out['materials_override'] = $this->nestedNumericMap($request->input('materials_override'), ['qty', 'unit_price']);
        $out['extra_materials'] = $this->extraMaterialsFromRequest($request);

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @param  list<string>  $fields
     * @return array<string, array<string, float>>
     */
    private function nestedNumericMap(mixed $raw, array $fields): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = [];
            foreach ($fields as $field) {
                if (isset($row[$field]) && is_numeric($row[$field])) {
                    $mapped[$field] = round((float) $row[$field], 4);
                }
            }
            if ($mapped !== []) {
                $out[(string) $key] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extraMaterialsFromRequest(Request $request): array
    {
        $rows = $request->input('extra_materials', []);
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['short_name'] ?? ''));
            $qty = round(max(0, (float) ($row['quantity'] ?? $row['qty'] ?? 0)), 2);
            if ($name === '' && $qty <= 0) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'quantity' => $qty,
                'unit' => trim((string) ($row['unit'] ?? 'each')) ?: 'each',
                'unit_price' => round(max(0, (float) ($row['unit_price'] ?? 0)), 4),
                'catalog_material_id' => (int) ($row['catalog_material_id'] ?? 0) ?: null,
                'delivery_cad' => round(max(0, (float) ($row['delivery_cad'] ?? 0)), 2),
                'client_provided' => filter_var($row['client_provided'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $out;
    }

    protected function autoLinkIfRequested(ShedQuotation $quote, Request $request, array &$warnings): bool
    {
        if ($quote->isLinked() || $quote->is_draft) {
            return false;
        }

        $project = $this->quotationLinkCandidateProject($request);
        if (! $project || ! $this->quotationProjectAllowsNewLink($project)) {
            if ($project) {
                $warnings[] = 'This project cannot accept linked quotations anymore. Save your work and link manually.';
            }

            return false;
        }

        if ((float) $quote->final_quote_total <= 0) {
            $warnings[] = 'Set a final quote total greater than zero before it can attach to the project.';

            return false;
        }

        try {
            DB::transaction(function () use ($project, $quote) {
                $this->appendLineToProjectQuotation($project, $quote->fresh());
            });

            return true;
        } catch (\Throwable $e) {
            $warnings[] = 'Could not link to the project automatically: '.$e->getMessage();

            return false;
        }
    }

    protected function syncLineToProjectQuotation(ClientProject $project, ShedQuotation $quote): void
    {
        $items = [[
            'description' => 'Shed construction (labor)',
            'amount' => $quote->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $quote->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Shed materials',
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }

        $this->appendCommuteProjectLine($items, $quote);
        $this->appendDebrisRemovalProjectLine($items, $quote);
        $this->appendAdditionalCostsProjectLines($items, $quote);

        $snapshot = $quote->only([
            'quote_number',
            'client_name',
            'inputs',
            'calculation',
            'final_quote_total',
            'work_description',
            'version',
            'include_commute',
            'commute_amount',
            'commute_estimated_trips',
        ]);

        $row = ClientProjectQuotation::firstOrNew([
            'client_project_id' => $project->id,
            'source_type' => ClientProjectQuotation::SOURCE_SHED,
            'source_id' => $quote->id,
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
        $row->material = $quote->includesMaterialCharge();
        $row->title = $quote->displayTitle();
        $row->items = $items;
        $row->work_description = $quote->scopeDescriptionForPdf();
        $this->applyPaymentScheduleToProjectQuotation($row, $quote);
        $row->source_snapshot = $snapshot;
        $row->save();

        $project->refresh();
        $project->syncFixedContractTotalsFromQuotation();
    }

    protected function appendLineToProjectQuotation(ClientProject $project, ShedQuotation $quote): void
    {
        $this->syncLineToProjectQuotation($project, $quote);

        $quote->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }
}
