<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesQuotationAdditionalCosts;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationCommuteExpense;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationDebrisRemoval;
use App\Http\Controllers\Admin\Concerns\HandlesQuotationPaymentSchedule;
use App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;
use App\Http\Controllers\Controller;
use App\Mail\BasementClientIntakeMail;
use App\Models\BasementQuotation;
use App\Models\BasementSettings;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\AdditionalCostsService;
use App\Services\BasementQuotationCalculator;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\QuotationAfterSaveRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BasementQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            BasementQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.basement.index', [
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

        $quote = BasementQuotation::create(array_merge($calc, [
            'quote_number' => BasementQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.basement.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Basement quotation saved as draft and linked.' : 'Basement quotation saved as draft.')
                : ($linkedNow
                    ? 'Basement quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Basement quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, BasementQuotation $basement_quotation)
    {
        return $this->formView($basement_quotation, $request);
    }

    public function update(Request $request, BasementQuotation $basement_quotation)
    {
        $intent = $request->input('save_intent') === 'draft' ? 'draft' : 'publish';
        $calc = $this->validatedCalculation($request, $intent, $basement_quotation);

        $basement_quotation->fill(array_merge($calc, [
            'version' => $basement_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($basement_quotation->isLinked() && $basement_quotation->client_project_id) {
            $project = ClientProject::query()->find($basement_quotation->client_project_id);
            if ($project) {
                DB::transaction(function () use ($project, $basement_quotation) {
                    $this->syncLineToProjectQuotation($project, $basement_quotation->fresh());
                });
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($basement_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.basement.edit', $basement_quotation)
            ->with('success', $linkedNow
                ? 'Basement quotation '.$basement_quotation->quote_number.' updated and linked.'
                : 'Basement quotation '.$basement_quotation->quote_number.' updated (v'.$basement_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request, ?BasementQuotation $basement_quotation = null)
    {
        $raw = $this->gatherInputsFromRequest($request, true);
        $calc = (new BasementQuotationCalculator)->calculate($raw);

        return response()->json($calc);
    }

    public function linkToProject(Request $request, BasementQuotation $basement_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($basement_quotation, 'basement')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($basement_quotation, $project);

        if ($basement_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $basement_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $basement_quotation) {
            $this->appendLineToProjectQuotation($project, $basement_quotation);
        });

        return redirect()
            ->route('admin.quotations.basement.edit', $basement_quotation)
            ->with('success', 'Linked to project "'.$project->name.'".');
    }

    public function createProjectAndLink(
        Request $request,
        BasementQuotation $basement_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($basement_quotation, 'basement')) {
            return $resp;
        }
        if ($basement_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $basement_quotation->final_quote_total <= 0) {
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
            $basement_quotation->projectTitleSuffix()
        );

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $basement_quotation) {
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

            $this->appendLineToProjectQuotation($project, $basement_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The basement renovation line was added to the Quotation tab.');
    }

    public function sendIntake(Request $request, BasementQuotation $basement_quotation)
    {
        if (! $basement_quotation->supportsClientIntake()) {
            return back()->with('error', 'Client intake is only available for Full basement and New bathroom quotations.');
        }

        $email = $basement_quotation->intakeClientEmail();
        if ($email === null) {
            return back()->with('error', 'Link this quotation to a project with a client email, or copy the form link instead.');
        }

        $url = $basement_quotation->clientIntakeUrl();
        $name = trim((string) ($basement_quotation->project->client_name ?? $basement_quotation->client_name ?? 'there'));
        if ($name === '') {
            $name = 'there';
        }

        Mail::to($email, $name)->send(new BasementClientIntakeMail($basement_quotation, $url, $name));
        $basement_quotation->client_intake_sent_at = now();
        $basement_quotation->save();

        return back()->with('success', 'Client form emailed to '.$email.'.');
    }

    public function reopenIntake(BasementQuotation $basement_quotation)
    {
        if (! $basement_quotation->supportsClientIntake()) {
            return back()->with('error', 'Client intake is only available for Full basement and New bathroom quotations.');
        }

        $basement_quotation->client_intake_submitted_at = null;
        $basement_quotation->save();

        return back()->with('success', 'The client can submit the form again. Their previous answers stay until they submit.');
    }

    public function destroy(BasementQuotation $basement_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_BASEMENT)
            ->where('source_id', $basement_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $basement_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.basement.index')
            ->with('success', 'Basement quotation deleted.');
    }

    protected function formView(?BasementQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();
        $settings = BasementSettings::current();

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
            $quotation->loadMissing('project');
            if ($quotation->supportsClientIntake()) {
                $quotation->ensureIntakeToken();
            }
            $linkedCpq = ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_BASEMENT,
                (int) $quotation->id
            );
        }

        $previewInputs = BasementQuotationCalculator::defaultInputs();
        $previewInputs['owner_hourly_rate'] = (float) $settings->owner_hourly_rate;
        $previewInputs['helper_hourly_rate'] = (float) $settings->helper_hourly_rate;
        if ($quotation && is_array($quotation->inputs)) {
            foreach ($quotation->inputs as $key => $value) {
                $previewInputs[$key] = $value;
            }
        }
        $initialPreview = (new BasementQuotationCalculator)->calculate($previewInputs, $settings);

        return view('admin.quotations.basement.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'sections' => BasementQuotationCalculator::SECTIONS,
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
    protected function validatedCalculation(Request $request, string $intent, ?BasementQuotation $existing = null): array
    {
        $draft = $intent === 'draft';
        $clientRules = $draft
            ? ['nullable', 'string', 'max:160']
            : ['required', 'string', 'max:160'];

        $this->prepareAdditionalCostMoneyOnRequest($request, 'electrical_lines', 'plumbing_lines');
        $this->prepareDebrisRemovalMoneyOnRequest($request);

        $request->validate(array_merge([
            'client_name' => $clientRules,
            'work_description' => ['nullable', 'string', 'max:20000'],
            'save_intent' => ['nullable', 'in:draft,publish'],
        ], QuotationPaymentSchedule::validationRules(), CommuteExpenseService::commuteValidationRules(), DebrisRemovalExpenseService::validationRules(), AdditionalCostsService::validationRules(), AdditionalCostsService::validationRulesFor('electrical_lines', 'electrical line'), AdditionalCostsService::validationRulesFor('plumbing_lines', 'plumbing line')), array_merge([
            'client_name.required' => 'Client name is required to save a published quotation.',
        ], QuotationPaymentSchedule::validationMessages(), CommuteExpenseService::commuteValidationMessages(), DebrisRemovalExpenseService::validationMessages(), AdditionalCostsService::validationMessages(), AdditionalCostsService::validationMessagesFor('electrical_lines', 'electrical line'), AdditionalCostsService::validationMessagesFor('plumbing_lines', 'plumbing line')));

        $rawInputs = $this->gatherInputsFromRequest($request, $draft);
        if (! $draft) {
            $hints = (new BasementQuotationCalculator)->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
        }

        $calc = (new BasementQuotationCalculator)->calculate($rawInputs);
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

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'basement_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'basement_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'basement_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request, bool $draft = false): array
    {
        $defaults = BasementQuotationCalculator::defaultInputs();
        $floats = [
            'area_sqft', 'ceiling_height_ft',
            'partition_lf', 'bulkhead_lf',
            'bath_tile_sf', 'owner_hourly_rate', 'helper_hourly_rate',
            'material_waste_percent',
        ];
        $optionalQty = [
            'demo_flooring_sf', 'demo_drywall_sf', 'demo_studs_lf', 'demo_baseboard_lf',
            'insulation_wall_sf', 'insulation_ceiling_sf', 'underslab_sf',
            'drywall_wall_sf', 'drywall_ceiling_sf', 'ceiling_sf',
            'flooring_sf', 'baseboard_lf',
            'paint_wall_sf', 'paint_ceiling_sf', 'paint_trim_lf',
            'area_length_ft', 'area_width_ft',
            'perimeter_lf',
        ];
        $ints = [
            'room_count', 'demo_bin_qty', 'header_qty', 'framing_door_openings',
            'outlet_qty', 'switch_qty', 'light_qty', 'detector_qty',
            'drywall_corners', 'drywall_inside_corners', 'drywall_openings', 'finish_level', 'transition_qty',
            'door_qty', 'casing_openings', 'window_trim_qty', 'paint_coats', 'paint_colors',
            'fixture_qty', 'hardware_sets', 'bath_fixture_qty', 'bath_spot_qty',
        ];
        $bools = [
            'include_demolition', 'demo_include_flooring', 'demo_include_studs',
            'demo_include_drywall', 'demo_include_baseboard',
            'panel_upgrade', 'include_plumbing', 'bath_rough_in',
            'sink_laundry', 'wet_bar', 'floor_drain', 'wh_reloc', 'insulate_walls',
            'insulate_ceiling', 'underslab', 'subfloor_prep', 'underlayment',
            'include_bathroom', 'include_vanity', 'include_pedestal_sink', 'include_toilet', 'include_shower',
            'include_tub', 'include_exhaust_fan', 'include_bath_fixture', 'include_bath_spot',
            'include_cleaning', 'include_touchups',
            'client_provides_all', 'include_helper',
        ];
        $strings = ['existing_notes', 'ceiling_type', 'flooring_type', 'bath_type', 'lighting_type', 'scope_preset', 'area_entry_mode', 'room_area_import_id', 'room_area_import_label'];

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
        $out['finish_level'] = BasementQuotationCalculator::normalizeFinishLevel($out['finish_level']);
        foreach ($bools as $key) {
            $out[$key] = $request->boolean($key);
        }
        foreach (['demo_include_flooring', 'demo_include_studs', 'demo_include_drywall', 'demo_include_baseboard'] as $key) {
            if (! $request->exists($key)) {
                $out[$key] = (bool) $defaults[$key];
            }
        }
        foreach ($strings as $key) {
            $out[$key] = trim((string) $request->input($key, $defaults[$key]));
        }
        if (! in_array($out['lighting_type'], ['fixture', 'spot', 'both'], true)) {
            $out['lighting_type'] = 'spot';
        }
        $out['paint_colors'] = ((int) $out['paint_colors'] === 2) ? 2 : 1;
        $out['bath_sink_qty'] = ((int) $request->input('bath_sink_qty', $defaults['bath_sink_qty'])) === 2 ? 2 : 1;
        $out['scope_preset'] = BasementQuotationCalculator::normalizePreset($out['scope_preset'] ?? 'full');
        $out['qty_dims'] = $this->qtyDimsFromRequest($request);
        $out['bath_tile_areas'] = $request->input('bath_tile_areas', []);
        $out = BasementQuotationCalculator::applyAreaEntry($out);

        $sections = array_keys(BasementQuotationCalculator::SECTIONS);
        $provided = $request->input('client_provides_sections', []);
        $out['client_provides_sections'] = is_array($provided)
            ? array_values(array_intersect($sections, array_map('strval', $provided)))
            : [];
        $out['client_provides_bath_items'] = BasementQuotationCalculator::normalizeClientProvidesBathItems([
            'client_provides_bath_items' => $request->input('client_provides_bath_items', []),
        ]);

        if ($request->has('enabled_sections')) {
            $enabled = $request->input('enabled_sections', []);
            $out['enabled_sections'] = is_array($enabled)
                ? array_values(array_intersect($sections, array_map('strval', $enabled)))
                : [];
            if (! in_array('overview', $out['enabled_sections'], true)) {
                array_unshift($out['enabled_sections'], 'overview');
            }
        } else {
            $out['enabled_sections'] = null;
        }

        $out['labor_override'] = $this->nestedNumericMap($request->input('labor_override'), ['hours']);
        $out['materials_override'] = $this->nestedNumericMap($request->input('materials_override'), ['qty', 'unit_price']);
        $out['extra_materials'] = $this->extraMaterialsFromRequest($request);
        $out['electrical_lines'] = AdditionalCostsService::normalizeRows($request->input('electrical_lines', []), 'Electrical work');
        $out['plumbing_lines'] = AdditionalCostsService::normalizeRows($request->input('plumbing_lines', []), 'Plumbing work');
        unset($draft);

        return $out;
    }

    /**
     * @return array<string, array{mode: string, length_ft: float|null, width_ft: float|null}>
     */
    private function qtyDimsFromRequest(Request $request): array
    {
        $raw = $request->input('qty_dims', []);
        if (! is_array($raw)) {
            $raw = [];
        }
        $out = [];
        foreach (BasementQuotationCalculator::qtyLwKeys() as $field) {
            $row = is_array($raw[$field] ?? null) ? $raw[$field] : [];
            $lenRaw = $row['length_ft'] ?? null;
            $widRaw = $row['width_ft'] ?? null;
            $out[$field] = [
                'mode' => BasementQuotationCalculator::normalizeAreaMode($row['mode'] ?? 'sqft'),
                'length_ft' => ($lenRaw === null || $lenRaw === '') ? null : round(max(0, (float) $lenRaw), 4),
                'width_ft' => ($widRaw === null || $widRaw === '') ? null : round(max(0, (float) $widRaw), 4),
            ];
        }

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

    protected function autoLinkIfRequested(BasementQuotation $quote, Request $request, array &$warnings): bool
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

    public function syncLinkedQuote(ClientProject $project, BasementQuotation $quote): void
    {
        $this->syncLineToProjectQuotation($project, $quote);
    }

    protected function syncLineToProjectQuotation(ClientProject $project, BasementQuotation $quote): void
    {
        $items = [[
            'description' => 'Basement renovation (labor)',
            'amount' => $quote->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $quote->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Basement renovation materials',
                'amount' => $materialAmt,
                'apply_hst' => true,
            ];
        }

        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        foreach (AdditionalCostsService::visibleProjectLineItems($inputs['electrical_lines'] ?? [], 'Electrical work') as $line) {
            $items[] = $line;
        }
        foreach (AdditionalCostsService::visibleProjectLineItems($inputs['plumbing_lines'] ?? [], 'Plumbing work') as $line) {
            $items[] = $line;
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
            'source_type' => ClientProjectQuotation::SOURCE_BASEMENT,
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

    protected function appendLineToProjectQuotation(ClientProject $project, BasementQuotation $quote): void
    {
        $this->syncLineToProjectQuotation($project, $quote);

        $quote->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

}
