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
use App\Models\TrimmingQuotation;
use App\Models\TrimmingSettings;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Services\TrimmingQuotationCalculator;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationBuildingLabor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrimmingQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            TrimmingQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.trimming.index', [
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

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $quote = TrimmingQuotation::create(array_merge($calc, [
            'quote_number' => TrimmingQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.trimming.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Trimming quotation saved as draft and linked.' : 'Trimming quotation saved as draft.')
                : ($linkedNow
                    ? 'Trimming quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Trimming quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, TrimmingQuotation $trimming_quotation)
    {
        return $this->formView($trimming_quotation, $request);
    }

    public function update(Request $request, TrimmingQuotation $trimming_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $trimming_quotation);

        $trimming_quotation->fill(array_merge($calc, [
            'version' => $trimming_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($trimming_quotation->isLinked() && $trimming_quotation->client_project_id) {
            $project = ClientProject::query()->find($trimming_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $trimming_quotation) {
                    $this->syncLineToProjectQuotation($project, $trimming_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($trimming_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.trimming.edit', $trimming_quotation)
            ->with('success', $linkedNow
                ? 'Trimming quotation '.$trimming_quotation->quote_number.' updated and linked.'
                : 'Trimming quotation '.$trimming_quotation->quote_number.' updated (v'.$trimming_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request, ?TrimmingQuotation $trimming_quotation = null)
    {
        $raw = $this->gatherInputsFromRequest($request);
        $calc = (new TrimmingQuotationCalculator)->calculate($raw);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, TrimmingQuotation $trimming_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($trimming_quotation, 'trimming')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($trimming_quotation, $project);

        if ($trimming_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $trimming_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $trimming_quotation) {
            $this->appendLineToProjectQuotation($project, $trimming_quotation);
        });

        return redirect()
            ->route('admin.quotations.trimming.edit', $trimming_quotation)
            ->with('success', 'Linked to project "'.$project->name.'".');
    }

    public function createProjectAndLink(
        Request $request,
        TrimmingQuotation $trimming_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($trimming_quotation, 'trimming')) {
            return $resp;
        }
        if ($trimming_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $trimming_quotation->final_quote_total <= 0) {
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
            $trimming_quotation->projectTitleSuffix()
        );

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $trimming_quotation) {
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

            $this->appendLineToProjectQuotation($project, $trimming_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The trimming line was added to the Quotation tab.');
    }

    public function destroy(TrimmingQuotation $trimming_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_TRIMMING)
            ->where('source_id', $trimming_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $trimming_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.trimming.index')
            ->with('success', 'Trimming quotation deleted.');
    }

    protected function formView(?TrimmingQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();
        $settings = TrimmingSettings::current();

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $previewInputs = TrimmingQuotationCalculator::defaultInputs();
        $previewInputs['owner_hourly_rate'] = (float) $settings->owner_hourly_rate;
        $previewInputs['helper_hourly_rate'] = (float) $settings->helper_hourly_rate;
        $roomAreaImport = app(\App\Services\RoomAreaQuotePrefill::class)->forRequest($request, 'trimming');
        $linkProject = $this->mergeRoomAreaLinkProject(
            $this->quotationLinkCandidateProject($request),
            $quotation ? null : $roomAreaImport
        );
        if (! $quotation && $roomAreaImport) {
            $previewInputs = array_merge($previewInputs, $roomAreaImport['inputs']);
        }

        $linkedCpq = null;
        if ($quotation) {
            $linkedCpq = ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_TRIMMING,
                (int) $quotation->id
            );
        }
        if ($quotation && is_array($quotation->inputs)) {
            foreach ($quotation->inputs as $key => $value) {
                $previewInputs[$key] = $value;
            }
        }
        $initialPreview = (new TrimmingQuotationCalculator)->calculate($previewInputs, $settings);

        return view('admin.quotations.trimming.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'families' => TrimmingQuotationCalculator::FAMILIES,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $linkedCpq,
            'initialPreview' => $initialPreview,
            'roomAreaImport' => $quotation ? null : $roomAreaImport,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCalculation(Request $request, string $intent, ?TrimmingQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:20000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules(), QuotationBuildingLabor::validationRules()), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages(), AdditionalCostsService::validationMessages(), QuotationBuildingLabor::validationMessages()));

        $rawInputs = $this->gatherInputsFromRequest($request);
        if (! $draft) {
            $hints = (new TrimmingQuotationCalculator)->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
        }

        $calc = (new TrimmingQuotationCalculator)->calculate($rawInputs);
        $final = (float) ($calc['summary']['final_quote_total'] ?? 0);
        $workDescription = \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage(
            $request->input('work_description')
        );

        $base = array_merge([
            'client_name' => trim((string) $request->input('client_name')) ?: null,
            'work_description' => $workDescription,
            'is_draft' => $draft,
            'inputs' => $rawInputs,
            'calculation' => $calc,
            'final_quote_total' => round($final, 2),
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'trimming_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'trimming_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'trimming_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request): array
    {
        $defaults = TrimmingQuotationCalculator::defaultInputs();
        $floats = [
            'floor_lf', 'crown_lf', 'cove_lf', 'built_up_crown_lf',
            'chair_rail_lf', 'picture_rail_lf', 'box_moulding_lf',
            'wainscot_wall_lf', 'wainscot_height_in',
            'owner_hourly_rate', 'helper_hourly_rate',
        ];
        $optionalQty = [
            'casing_lf', 'window_casing_lf', 'architrave_lf',
            'plinth_qty', 'rosette_qty', 'stool_apron_qty', 'jamb_qty',
            'baseboard_lf', 'base_cap_lf', 'built_up_baseboard_lf', 'shoe_lf', 'quarter_round_lf',
            'remove_lf', 'paint_lf', 'stain_lf', 'picture_frame_window_qty',
        ];
        $ints = ['door_qty', 'window_qty', 'paint_coats', 'pocket_door_qty', 'french_door_qty'];
        $bools = [
            'include_casing', 'include_architrave', 'include_plinth', 'include_rosettes',
            'include_stool_apron', 'include_jamb_extensions', 'include_interior_doors',
            'include_slab_swap', 'include_picture_frame_windows',
            'include_baseboard', 'include_base_cap', 'include_built_up_baseboard', 'include_shoe', 'include_quarter_round',
            'include_crown', 'include_cove', 'include_built_up_crown',
            'include_chair_rail', 'include_picture_rail', 'include_box_moulding', 'include_wainscoting',
            'include_remove_existing', 'include_caulk_paint', 'include_stain', 'client_provides_all', 'include_helper',
        ];

        $out = $defaults;
        foreach ($floats as $key) {
            $out[$key] = round(max(0, (float) $request->input($key, $defaults[$key])), 4);
        }
        foreach ($optionalQty as $key) {
            $raw = $request->input($key);
            if ($raw === null || $raw === '') {
                $out[$key] = null;
            } else {
                $out[$key] = round(max(0, (float) $raw), 4);
            }
        }
        foreach ($ints as $key) {
            $out[$key] = max(0, (int) $request->input($key, $defaults[$key]));
        }
        foreach ($bools as $key) {
            $out[$key] = $request->boolean($key);
        }
        $out['wainscot_style'] = TrimmingQuotationCalculator::normalizeWainscotStyle($request->input('wainscot_style'));
        $out['paint_coats'] = max(1, min(4, (int) $out['paint_coats']));

        $families = array_keys(TrimmingQuotationCalculator::FAMILIES);
        $enabled = $request->input('enabled_families', []);
        $out['enabled_families'] = is_array($enabled)
            ? array_values(array_intersect($families, array_map('strval', $enabled)))
            : [];

        $provided = $request->input('client_provides_sections', []);
        $out['client_provides_sections'] = is_array($provided)
            ? array_values(array_intersect(array_merge($families, ['prep_finish']), array_map('strval', $provided)))
            : [];

        $out['labor_override'] = $this->nestedNumericMap($request->input('labor_override'), ['hours']);
        $out['materials_override'] = $this->nestedNumericMap($request->input('materials_override'), ['qty', 'unit_price']);
        $out['extra_materials'] = $this->extraMaterialsFromRequest($request);

        return QuotationBuildingLabor::mergeIntoInputs($out, $request);
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

    protected function autoLinkIfRequested(TrimmingQuotation $quote, Request $request, array &$warnings): bool
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

    protected function syncLineToProjectQuotation(ClientProject $project, TrimmingQuotation $quote): void
    {
        $items = [[
            'description' => 'Interior trimming (labor)',
            'amount' => $quote->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $quote->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Trimming materials',
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
            'source_type' => ClientProjectQuotation::SOURCE_TRIMMING,
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

    protected function appendLineToProjectQuotation(ClientProject $project, TrimmingQuotation $quote): void
    {
        $this->syncLineToProjectQuotation($project, $quote);

        $quote->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }
}
