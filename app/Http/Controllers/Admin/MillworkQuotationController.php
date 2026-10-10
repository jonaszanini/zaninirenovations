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
use App\Models\Material;
use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\AdditionalCostsService;
use App\Services\ClientProjectStatusService;
use App\Services\CommuteExpenseService;
use App\Services\DebrisRemovalExpenseService;
use App\Services\Millwork\MillworkPartBuilder;
use App\Services\MillworkQuotationCalculator;
use App\Services\QuotationPaymentSchedule;
use App\Support\MaterialCatalogPickerOptions;
use App\Support\PurchaseListPrintMode;
use App\Support\QuotationAfterSaveRedirect;
use App\Support\QuotationSaveIntent;
use App\Support\QuotationBuildingLabor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MillworkQuotationController extends Controller
{
    use HandlesQuotationAdditionalCosts;
    use HandlesQuotationCommuteExpense;
    use HandlesQuotationDebrisRemoval;
    use HandlesQuotationPaymentSchedule;
    use ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            MillworkQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.millwork.index', [
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
        $intent = QuotationSaveIntent::fromRequest($request);
        $calc = $this->validatedCalculation($request, $intent, null);

        $quote = MillworkQuotation::create(array_merge($calc, [
            'quote_number' => MillworkQuotation::generateNumber(),
            'version' => 1,
        ]));

        $warnings = [];
        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($quote->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.millwork.edit', $quote->fresh())
            ->with('success', $intent === 'draft'
                ? ($linkedNow ? 'Millwork quotation saved as draft and linked.' : 'Millwork quotation saved as draft.')
                : ($linkedNow
                    ? 'Millwork quotation '.$quote->quote_number.' saved and linked to this project.'
                    : 'Millwork quotation '.$quote->quote_number.' saved.'));

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function edit(Request $request, MillworkQuotation $millwork_quotation)
    {
        return $this->formView($millwork_quotation, $request);
    }

    public function update(Request $request, MillworkQuotation $millwork_quotation)
    {
        $intent = QuotationSaveIntent::fromRequest($request);
        $calc = $this->validatedCalculation($request, $intent, $millwork_quotation);

        $millwork_quotation->fill(array_merge($calc, [
            'version' => $millwork_quotation->version + 1,
        ]))->save();

        $warnings = [];
        if ($millwork_quotation->isLinked() && $millwork_quotation->client_project_id) {
            $fresh = $millwork_quotation->fresh();
            if ($fresh && $fresh->hasUnplacedCutParts()) {
                $warnings[] = MillworkQuotation::unplacedAdminMessage().' The project price was not updated.';
            } else {
                $project = ClientProject::query()->find($millwork_quotation->client_project_id);
                if ($project && $fresh) {
                    DB::transaction(function () use ($project, $fresh) {
                        $this->syncLineToProjectQuotation($project, $fresh);
                    });
                }
            }
        }

        $hubProjectRequested = $this->quotationLinkCandidateProject($request);
        $newlyLinked = $this->autoLinkIfRequested($millwork_quotation->fresh(), $request, $warnings);
        $linkedNow = $newlyLinked && $hubProjectRequested;

        $resp = redirect()
            ->route('admin.quotations.millwork.edit', $millwork_quotation)
            ->with('success', $linkedNow
                ? 'Millwork quotation '.$millwork_quotation->quote_number.' updated and linked.'
                : 'Millwork quotation '.$millwork_quotation->quote_number.' updated (v'.$millwork_quotation->version.').');

        return QuotationAfterSaveRedirect::apply($request, $warnings === [] ? $resp : $resp->with('warning', implode(' ', $warnings)));
    }

    public function preview(Request $request, ?MillworkQuotation $millwork_quotation = null)
    {
        $this->prepareAdditionalCostMoneyOnRequest($request);
        $this->prepareDebrisRemovalMoneyOnRequest($request);
        $raw = $this->gatherInputsFromRequest($request);
        $upgrade = $request->boolean('upgrade_cut_plan');
        $cutPlanVersion = $this->resolveCutPlanVersion($request, $millwork_quotation);
        $raw['_cut_plan_version'] = $cutPlanVersion;

        $calc = (new MillworkQuotationCalculator)->calculate($raw, null, $cutPlanVersion);
        if ($upgrade && $millwork_quotation && $millwork_quotation->usesLegacyCutBuilder()) {
            $notice = $this->recalcNoticePayload($millwork_quotation, $calc, $request);
            if ($notice !== null) {
                $calc['recalc_notice'] = $notice;
            }
        }

        return response()->json($calc);
    }

    public function purchaseList(Request $request, MillworkQuotation $millwork_quotation)
    {
        $calc = is_array($millwork_quotation->calculation) ? $millwork_quotation->calculation : [];
        $materials = array_merge(
            is_array($calc['materials'] ?? null) ? $calc['materials'] : [],
            is_array($calc['extra_materials'] ?? null) ? $calc['extra_materials'] : []
        );
        $catalogIds = [];
        foreach ($materials as $row) {
            $id = (int) ($row['catalog_material_id'] ?? 0);
            if ($id > 0) {
                $catalogIds[$id] = $id;
            }
        }
        $catalogById = $catalogIds === []
            ? collect()
            : Material::query()->whereIn('id', array_values($catalogIds))->get()->keyBy('id');

        $rows = [];
        foreach ($materials as $row) {
            if (! is_array($row) || ! empty($row['client_provided'])) {
                continue;
            }
            $qty = (float) ($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $catalogId = (int) ($row['catalog_material_id'] ?? 0);
            $catalog = $catalogId > 0 ? $catalogById->get($catalogId) : null;
            $name = trim((string) ($row['label'] ?? ''));
            if ($name === '' && $catalog) {
                $name = $catalog->displayName();
            }
            if ($name === '') {
                $name = 'Material';
            }
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            $url = $catalog ? (string) ($catalog->storeProductUrl() ?? '') : '';
            $sku = $catalog ? trim((string) ($catalog->store_sku ?? '')) : '';
            $unit = trim((string) ($row['unit'] ?? 'ea')) ?: 'ea';
            $rows[] = [
                'name_html' => htmlspecialchars($name, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true),
                'short_name_html' => null,
                'store_sku_html' => $sku !== '' ? htmlspecialchars($sku, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true) : null,
                'url' => $url !== '' ? $url : null,
                'quantity' => $qty,
                'unit_html' => htmlspecialchars($unit, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', true),
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $qty, 2),
            ];
        }

        return view('admin.quotations.standard.purchase_list', [
            'quotation' => $millwork_quotation,
            'rows' => $rows,
            'showPrices' => PurchaseListPrintMode::showsPrices($request->query('prices')),
        ]);
    }

    public function cutList(MillworkQuotation $millwork_quotation)
    {
        $calc = is_array($millwork_quotation->calculation) ? $millwork_quotation->calculation : [];
        $cut = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];
        $layouts = is_array($cut['layouts'] ?? null) ? $cut['layouts'] : [];

        return view('admin.quotations.millwork.cut_list', [
            'quotation' => $millwork_quotation,
            'cut' => $cut,
            'layouts' => $layouts,
        ]);
    }

    public function linkToProject(Request $request, MillworkQuotation $millwork_quotation)
    {
        if ($resp = $this->guardQuotationAlreadyLinked($millwork_quotation, 'millwork')) {
            return $resp;
        }

        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
            'link_line_description' => ['nullable', 'string', 'max:500'],
        ]);

        $project = ClientProject::query()->findOrFail($data['client_project_id']);
        $this->prepareQuotationForProjectLink($millwork_quotation, $project);

        if ($millwork_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');
        }
        if ((float) $millwork_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before linking.');
        }
        if ($millwork_quotation->hasUnplacedCutParts()) {
            return back()->with('error', MillworkQuotation::unplacedAdminMessage());
        }
        if ($resp = $this->guardProjectClosedToNewQuotations($project)) {
            return $resp;
        }

        DB::transaction(function () use ($project, $millwork_quotation) {
            $this->appendLineToProjectQuotation($project, $millwork_quotation);
        });

        return redirect()
            ->route('admin.quotations.millwork.edit', $millwork_quotation)
            ->with('success', 'Linked to project "'.$project->name.'".');
    }

    public function createProjectAndLink(
        Request $request,
        MillworkQuotation $millwork_quotation,
        ClientProjectStatusService $statusService
    ) {
        if ($resp = $this->guardQuotationAlreadyLinked($millwork_quotation, 'millwork')) {
            return $resp;
        }
        if ($millwork_quotation->is_draft) {
            return back()->with('error', 'Publish the quotation before creating a project.');
        }
        if ((float) $millwork_quotation->final_quote_total <= 0) {
            return back()->with('error', 'Set a final quote total greater than zero before creating a project.');
        }
        if ($millwork_quotation->hasUnplacedCutParts()) {
            return back()->with('error', MillworkQuotation::unplacedAdminMessage());
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
            $millwork_quotation->projectTitleSuffix()
        );

        $project = DB::transaction(function () use ($data, $projectTitle, $clientName, $millwork_quotation) {
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

            $this->appendLineToProjectQuotation($project, $millwork_quotation);

            return $project;
        });

        $this->finalizeCreatedProjectFromQuotation($project, $statusService);

        return redirect()
            ->to(route('admin.client-projects.show', $project).'#quotation')
            ->with('success', 'Project created. The millwork line was added to the Quotation tab.');
    }

    public function destroy(MillworkQuotation $millwork_quotation)
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_MILLWORK)
            ->where('source_id', $millwork_quotation->id)
            ->first();

        if ($cpq) {
            ClientProjectQuotation::deleteWithLinkedCalculatorSource($cpq);
        } else {
            $millwork_quotation->delete();
        }

        return redirect()
            ->route('admin.quotations.millwork.index')
            ->with('success', 'Millwork quotation deleted.');
    }

    protected function formView(?MillworkQuotation $quotation, ?Request $request = null)
    {
        $request = $request ?? request();
        $settings = MillworkSettings::current();

        $linkableProjects = ClientProject::query()
            ->whereNotIn('status', ClientProject::statusesClosedToNewQuotations())
            ->orderByDesc('id')
            ->get(['id', 'name', 'client_name', 'status']);

        $roomAreaImport = ! $quotation
            ? app(\App\Services\RoomAreaQuotePrefill::class)->forRequest($request, 'millwork')
            : null;
        $linkProject = $this->mergeRoomAreaLinkProject(
            $this->quotationLinkCandidateProject($request),
            $roomAreaImport
        );
        if ($linkProject && ! $this->quotationProjectAllowsNewLink($linkProject)) {
            $linkProject = null;
        }

        $linkedCpq = null;
        if ($quotation) {
            $linkedCpq = ClientProjectQuotation::findForSource(
                ClientProjectQuotation::SOURCE_MILLWORK,
                (int) $quotation->id
            );
        }

        $previewInputs = MillworkQuotationCalculator::defaultInputs();
        $previewInputs['owner_hourly_rate'] = (float) $settings->owner_hourly_rate;
        $previewInputs['helper_hourly_rate'] = (float) $settings->helper_hourly_rate;
        if (! $quotation && $roomAreaImport) {
            $previewInputs = array_merge($previewInputs, $roomAreaImport['inputs']);
        }
        if ($quotation && is_array($quotation->inputs)) {
            foreach ($quotation->inputs as $key => $value) {
                $previewInputs[$key] = $value;
            }
        }
        $cutPlanVersion = $quotation
            ? $quotation->cutPlanVersion()
            : MillworkQuotationCalculator::CUT_PLAN_VERSION;
        $previewInputs['_cut_plan_version'] = $cutPlanVersion;
        $initialPreview = (new MillworkQuotationCalculator)->calculate(
            $previewInputs,
            $settings,
            $cutPlanVersion
        );

        return view('admin.quotations.millwork.create', [
            'quotation' => $quotation,
            'settings' => $settings,
            'sections' => MillworkQuotationCalculator::SECTIONS,
            'linkableProjects' => $linkableProjects,
            'linkProject' => $linkProject,
            'linkedCpq' => $linkedCpq,
            'initialPreview' => $initialPreview,
            'cutPlanVersion' => $cutPlanVersion,
            'builderVersion' => $cutPlanVersion,
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
            'catalog' => $settings->resolvedCatalog(),
            'roomAreaImport' => $quotation ? null : $roomAreaImport,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedCalculation(Request $request, string $intent, ?MillworkQuotation $existing = null): array
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
        $cutPlanVersion = $this->resolveCutPlanVersion($request, $existing);
        $rawInputs['_cut_plan_version'] = $cutPlanVersion;

        if (! $draft) {
            $hints = (new MillworkQuotationCalculator)->validationHints($rawInputs, false);
            if ($hints !== []) {
                throw ValidationException::withMessages([
                    'calculation' => implode(' ', $hints),
                ]);
            }
        }

        $calc = (new MillworkQuotationCalculator)->calculate($rawInputs, null, $cutPlanVersion);
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
            'cut_plan_version' => $cutPlanVersion,
        ], $this->paymentScheduleFromRequest($request));

        $commute = $this->commutePersistenceFromRequest($request, $existing, 'millwork_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'millwork_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'millwork_quotations');

        return $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatherInputsFromRequest(Request $request): array
    {
        $defaults = MillworkQuotationCalculator::defaultInputs();
        $out = $defaults;
        $out['owner_hourly_rate'] = round(max(0, (float) $request->input('owner_hourly_rate', $defaults['owner_hourly_rate'])), 4);
        $out['helper_hourly_rate'] = round(max(0, (float) $request->input('helper_hourly_rate', $defaults['helper_hourly_rate'])), 4);
        $out['include_install'] = $request->boolean('include_install');
        $out['include_helper'] = $request->boolean('include_helper');
        $out['client_provides_all'] = $request->boolean('client_provides_all');
        $out['waste_pct'] = $this->boundedPct($request->input('waste_pct', $defaults['waste_pct']));
        $out['edge_waste_pct'] = $this->boundedPct($request->input('edge_waste_pct', $defaults['edge_waste_pct']));
        $out['finish_waste_pct'] = $this->boundedPct($request->input('finish_waste_pct', $defaults['finish_waste_pct']));
        $out['markup_pct'] = $this->boundedPct($request->input('markup_pct', $defaults['markup_pct']));
        $out['delivery_amount'] = round(max(0, (float) $request->input('delivery_amount', $defaults['delivery_amount'])), 2);
        $out['spare_sheets'] = max(0, (int) $request->input('spare_sheets', $defaults['spare_sheets']));
        foreach ([
            'sheet_id', 'back_sheet_id', 'edge_banding_id', 'paint_id', 'stain_id',
            'hinge_id', 'pull_id', 'screw_id', 'nail_id', 'slide_id', 'glue_id',
            'rod_id', 'pin_id', 'trim_id',
        ] as $key) {
            $out[$key] = trim((string) $request->input($key, $defaults[$key] ?? ''));
        }
        $out['pieces'] = $this->piecesFromRequest($request);
        $out['labor_override'] = $this->nestedNumericMap($request->input('labor_override'), ['hours']);
        $out['materials_override'] = $this->nestedNumericMap($request->input('materials_override'), ['qty', 'unit_price']);
        $out['extra_materials'] = $this->extraMaterialsFromRequest($request);

        return QuotationBuildingLabor::mergeIntoInputs($out, $request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function piecesFromRequest(Request $request): array
    {
        $raw = $request->input('pieces', []);
        if (! is_array($raw) || $raw === []) {
            return MillworkQuotationCalculator::defaultInputs()['pieces'];
        }
        $out = [];
        foreach ($raw as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $type = MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
            $row = MillworkQuotationCalculator::defaultPiece($type);
            $row['type'] = $type;
            $row['qty'] = max(0, (int) ($piece['qty'] ?? 1));
            $row['unit'] = strtolower((string) ($piece['unit'] ?? 'in')) === 'ft' ? 'ft' : 'in';
            $row['name'] = trim((string) ($piece['name'] ?? ''));
            $row['room'] = trim((string) ($piece['room'] ?? ''));
            $row['style'] = \App\Services\Millwork\MillworkPartBuilder::normalizeCabinetStyle((string) ($piece['style'] ?? 'base'));
            $row['profile'] = \App\Services\Millwork\MillworkPartBuilder::normalizeTrimProfile((string) ($piece['profile'] ?? 'baseboard'));
            foreach ([
                'width', 'height', 'depth', 'length', 'thickness', 'linear_ft', 'stock_length_ft',
            ] as $dim) {
                $row[$dim] = is_numeric($piece[$dim] ?? null) ? round((float) $piece[$dim], 4) : ($piece[$dim] ?? '');
            }
            foreach ([
                'edge_banding', 'include_back', 'include_doors', 'include_toe_kick', 'same_size',
            ] as $flag) {
                $row[$flag] = filter_var($piece[$flag] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $row['finish'] = MillworkPartBuilder::normalizeFinish((string) ($piece['finish'] ?? 'none'));
            $row['fastener'] = (($piece['fastener'] ?? 'screws') === 'nails') ? 'nails' : 'screws';
            $row['left_side'] = (($piece['left_side'] ?? 'panel') === 'wall') ? 'wall' : 'panel';
            $row['right_side'] = (($piece['right_side'] ?? 'panel') === 'wall') ? 'wall' : 'panel';
            $row['sides'] = (($piece['sides'] ?? 'panel') === 'wall') ? 'wall' : 'panel';
            $row['below'] = (($piece['below'] ?? 'divisions') === 'drawers') ? 'drawers' : 'divisions';
            $row['overlay'] = MillworkPartBuilder::normalizeOverlay((string) ($piece['overlay'] ?? 'full'));
            $style = strtolower((string) ($piece['hinge_style'] ?? 'overlay'));
            $row['hinge_style'] = in_array($style, ['overlay', 'inset', 'soft_close'], true) ? $style : 'overlay';
            foreach (['door_count', 'shelf_count', 'drawer_count', 'division_count', 'columns', 'rows', 'rod_count', 'openings'] as $intKey) {
                $row[$intKey] = max(0, (int) ($piece[$intKey] ?? ($row[$intKey] ?? 0)));
            }
            if (array_key_exists('pull_qty', $piece) && $piece['pull_qty'] !== '' && $piece['pull_qty'] !== null) {
                $row['pull_qty'] = max(0, (int) $piece['pull_qty']);
            } else {
                unset($row['pull_qty']);
            }
            $sets = [];
            foreach (is_array($piece['sets'] ?? null) ? $piece['sets'] : [] as $set) {
                if (! is_array($set)) {
                    continue;
                }
                $sets[] = [
                    'qty' => max(0, (int) ($set['qty'] ?? 0)),
                    'length' => $set['length'] ?? '',
                    'width' => $set['width'] ?? '',
                    'depth' => $set['depth'] ?? '',
                    'height' => $set['height'] ?? '',
                    'thickness' => $set['thickness'] ?? '',
                    'unit' => strtolower((string) ($set['unit'] ?? $row['unit'])) === 'ft' ? 'ft' : 'in',
                ];
            }
            $row['sets'] = $sets !== [] ? $sets : $row['sets'];
            $out[] = $row;
        }

        return $out !== [] ? $out : MillworkQuotationCalculator::defaultInputs()['pieces'];
    }

    /**
     * @param  mixed  $raw
     * @param  list<string>  $fields
     * @return array<string, array<string, float>>
     */
    private function nestedNumericMap($raw, array $fields): array
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

    private function boundedPct($value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(max(0, min(100, (float) $value)), 2);
    }

    protected function autoLinkIfRequested(MillworkQuotation $quote, Request $request, array &$warnings): bool
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
        if ($quote->hasUnplacedCutParts()) {
            $warnings[] = MillworkQuotation::unplacedAdminMessage();

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

    protected function syncLineToProjectQuotation(ClientProject $project, MillworkQuotation $quote): void
    {
        if ($this->quotationIsDraft($quote)) {
            return;
        }

        $items = [[
            'description' => 'Custom millwork (labour)',
            'amount' => $quote->labourPreTaxAmountForProjectQuotation(),
            'apply_hst' => true,
        ]];

        $materialAmt = $quote->materialsPreTaxAmountForProjectQuotation();
        if ($materialAmt > 0) {
            $items[] = [
                'description' => 'Custom millwork materials',
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
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
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

    protected function appendLineToProjectQuotation(ClientProject $project, MillworkQuotation $quote): void
    {
        if ($this->quotationIsDraft($quote)) {
            return;
        }

        $this->syncLineToProjectQuotation($project, $quote);

        $quote->update(array_merge([
            'client_project_id' => $project->id,
            'linked_at' => now(),
        ], $this->quotationLinkClientNameAttributes($project)));
    }

    protected function resolveCutPlanVersion(Request $request, ?MillworkQuotation $existing): int
    {
        if ($request->boolean('upgrade_cut_plan')) {
            return MillworkQuotationCalculator::CUT_PLAN_VERSION;
        }
        if ($existing) {
            return $existing->cutPlanVersion();
        }

        return MillworkQuotationCalculator::CUT_PLAN_VERSION;
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return array{was: float, now: float, was_label: string, now_label: string}|null
     */
    protected function recalcNoticePayload(MillworkQuotation $quote, array $calc, Request $request): ?array
    {
        $was = round((float) ($quote->final_quote_total ?? 0), 2);
        $now = $this->previewTotalWithExtras((float) ($calc['summary']['grand_total'] ?? 0), $request, $quote);
        if (abs($was - $now) < 0.01) {
            return null;
        }

        return [
            'was' => $was,
            'now' => $now,
            'was_label' => ClientProject::formatMoney($was),
            'now_label' => ClientProject::formatMoney($now),
        ];
    }

    protected function previewTotalWithExtras(float $grand, Request $request, ?MillworkQuotation $existing): float
    {
        $base = ['final_quote_total' => round($grand, 2)];
        $commute = $this->commutePersistenceFromRequest($request, $existing, 'millwork_quotations');
        $merged = $this->mergeCommuteIntoPersistence($base, $commute, 'final_quote_total');
        $debris = $this->debrisRemovalPersistenceFromRequest($request, $existing, 'millwork_quotations');
        $merged = $this->mergeDebrisRemovalIntoPersistence($merged, $debris, 'final_quote_total');
        $additional = $this->additionalCostsPersistenceFromRequest($request, $existing, 'millwork_quotations');
        $merged = $this->mergeAdditionalCostsIntoPersistence($merged, $additional, 'final_quote_total');

        return round((float) ($merged['final_quote_total'] ?? $grand), 2);
    }
}
