<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\TileGroutType;
use App\Models\TileMortarType;
use App\Models\TileProduct;
use App\Models\TileQuotation;
use App\Services\TileQuotationCalculator;
use Illuminate\Http\Request;

class TileQuotationController extends Controller
{
    use \App\Http\Controllers\Admin\Concerns\ResolvesQuotationProjectLink;

    public function index(Request $request)
    {
        $quotations = $this->applyUnlinkedOnlyQuotationIndexFilter(
            TileQuotation::query()->with('project')->orderByDesc('id'),
            $request
        )->paginate(30)->withQueryString();

        return view('admin.quotations.tile.index', [
            'quotations' => $quotations,
            'linkProject' => $this->quotationIndexLinkProject($request),
            'filterUnlinkedOnly' => $this->quotationIndexFiltersUnlinkedOnly($request),
        ]);
    }

    public function create(Request $request)
    {
        $linkProject = null;
        if ($request->filled('project')) {
            $linkProject = ClientProject::query()->with('photos')->find($request->query('project'));
        }

        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.quotations.tile.create', compact('linkProject', 'mortarTypes', 'groutTypes', 'products'));
    }

    public function store(Request $request, TileQuotationCalculator $calculator)
    {
        $data = $request->validate([
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string'],
            'total_area_sqft' => ['nullable', 'numeric', 'min:0'],
            'length_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'width_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'waste_margin_percent' => ['required', 'numeric', 'min:0'],
            'tile_product_id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'include_tile_material' => ['nullable', 'boolean'],
            'trowel_size' => ['required', 'string', 'in:1/4x1/4,1/4x3/8,1/2x1/2'],
            'mortar_type_id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'joint_width_decimal' => ['required', 'numeric', 'min:0'],
            'grout_type_id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'labor_rate' => ['required', 'numeric', 'min:0'],

            'is_draft' => ['nullable', 'string'],
        ]);

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $calculator->compute(
            (float) ($data['length_ft'] ?? 0),
            (float) ($data['width_ft'] ?? 0),
            (float) $data['waste_margin_percent'],
            empty($data['tile_product_id']) ? null : (int) $data['tile_product_id'],
            $includeTile,
            $data['trowel_size'],
            empty($data['mortar_type_id']) ? null : (int) $data['mortar_type_id'],
            (float) $data['joint_width_decimal'],
            empty($data['grout_type_id']) ? null : (int) $data['grout_type_id'],
            (float) $data['labor_rate'],
            (float) ($data['total_area_sqft'] ?? 0)
        );

        $quotation = new TileQuotation();
        $quotation->client_project_id = empty($data['client_project_id']) ? null : (int) $data['client_project_id'];
        $quotation->quote_number = TileQuotation::generateNumber();
        $quotation->client_name = $data['client_name'] ?? null;
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = $data['work_description'] ?? null;
        
        $quotation->inputs = [
            'total_area_sqft' => (float) ($data['total_area_sqft'] ?? 0),
            'length_ft' => (float) ($data['length_ft'] ?? 0),
            'width_ft' => (float) ($data['width_ft'] ?? 0),
            'waste_margin_percent' => (float) $data['waste_margin_percent'],
            'tile_product_id' => $data['tile_product_id'] ?? null,
            'include_tile_material' => $includeTile,
            'trowel_size' => $data['trowel_size'],
            'mortar_type_id' => $data['mortar_type_id'] ?? null,
            'joint_width_decimal' => (float) $data['joint_width_decimal'],
            'grout_type_id' => $data['grout_type_id'] ?? null,
            'labor_rate' => (float) $data['labor_rate'],
        ];
        $quotation->calculation = $calculation;
        $quotation->final_quote_total = $calculation['grand_total'];
        
        if ($quotation->client_project_id) {
            $quotation->linked_at = now();
        }
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
            return redirect()
                ->route('admin.projects.show', ['project' => $quotation->client_project_id, 'tab' => 'quotation'])
                ->with('success', 'Tile quotation created and linked.');
        }

        return redirect()->route('admin.quotations.index')->with('success', 'Tile quotation created.');
    }

    public function edit(Request $request, TileQuotation $quotation)
    {
        $mortarTypes = TileMortarType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $quotation->load('project.photos');
        $returnToProject = $request->query('return') === 'project';
        $linkProject = null;

        return view('admin.quotations.tile.create', compact('quotation', 'mortarTypes', 'groutTypes', 'products', 'returnToProject', 'linkProject'));
    }

    public function update(Request $request, TileQuotation $quotation, TileQuotationCalculator $calculator)
    {
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:160'],
            'work_description' => ['nullable', 'string'],
            'total_area_sqft' => ['nullable', 'numeric', 'min:0'],
            'length_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'width_ft' => ['nullable', 'numeric', 'min:0', 'required_without:total_area_sqft'],
            'waste_margin_percent' => ['required', 'numeric', 'min:0'],
            'tile_product_id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'include_tile_material' => ['nullable', 'boolean'],
            'trowel_size' => ['required', 'string', 'in:1/4x1/4,1/4x3/8,1/2x1/2'],
            'mortar_type_id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'joint_width_decimal' => ['required', 'numeric', 'min:0'],
            'grout_type_id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'labor_rate' => ['required', 'numeric', 'min:0'],
            'is_draft' => ['nullable', 'string'],
        ]);

        $includeTile = filter_var($data['include_tile_material'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $calculation = $calculator->compute(
            (float) ($data['length_ft'] ?? 0),
            (float) ($data['width_ft'] ?? 0),
            (float) $data['waste_margin_percent'],
            empty($data['tile_product_id']) ? null : (int) $data['tile_product_id'],
            $includeTile,
            $data['trowel_size'],
            empty($data['mortar_type_id']) ? null : (int) $data['mortar_type_id'],
            (float) $data['joint_width_decimal'],
            empty($data['grout_type_id']) ? null : (int) $data['grout_type_id'],
            (float) $data['labor_rate'],
            (float) ($data['total_area_sqft'] ?? 0)
        );

        if (! $quotation->isLinked()) {
            $quotation->client_name = $data['client_name'] ?? null;
        }
        $quotation->is_draft = $request->input('is_draft') === '1';
        $quotation->work_description = $data['work_description'] ?? null;
        
        $quotation->inputs = [
            'total_area_sqft' => (float) ($data['total_area_sqft'] ?? 0),
            'length_ft' => (float) ($data['length_ft'] ?? 0),
            'width_ft' => (float) ($data['width_ft'] ?? 0),
            'waste_margin_percent' => (float) $data['waste_margin_percent'],
            'tile_product_id' => $data['tile_product_id'] ?? null,
            'include_tile_material' => $includeTile,
            'trowel_size' => $data['trowel_size'],
            'mortar_type_id' => $data['mortar_type_id'] ?? null,
            'joint_width_decimal' => (float) $data['joint_width_decimal'],
            'grout_type_id' => $data['grout_type_id'] ?? null,
            'labor_rate' => (float) $data['labor_rate'],
        ];
        $quotation->calculation = $calculation;
        $quotation->final_quote_total = $calculation['grand_total'];
        $quotation->version += 1;
        
        $quotation->save();

        if ($quotation->client_project_id) {
            $this->syncProjectQuotation($quotation);
        }

        if ($request->query('return') === 'project' && $quotation->client_project_id) {
            return redirect()
                ->route('admin.projects.show', ['project' => $quotation->client_project_id, 'tab' => 'quotation'])
                ->with('success', 'Tile quotation updated.');
        }

        return redirect()->route('admin.quotations.index')->with('success', 'Tile quotation updated.');
    }

    public function destroy(TileQuotation $quotation)
    {
        $projectId = $quotation->client_project_id;
        
        if ($projectId) {
            ClientProjectQuotation::query()
                ->where('client_project_id', $projectId)
                ->where('source_type', ClientProjectQuotation::SOURCE_TILE)
                ->where('source_id', $quotation->id)
                ->delete();
        }

        $quotation->delete();

        return back()->with('success', 'Tile quotation deleted.');
    }

    public function linkToProject(Request $request, TileQuotation $quotation)
    {
        $data = $request->validate([
            'client_project_id' => ['required', 'integer', 'exists:client_projects,id'],
        ]);

        if ($quotation->isLinked()) {
            return back()->with('error', 'Already linked to a project.');
        }

        $quotation->client_project_id = $data['client_project_id'];
        $quotation->linked_at = now();
        $quotation->save();

        $this->syncProjectQuotation($quotation);

        return redirect()
            ->route('admin.projects.show', ['project' => $quotation->client_project_id, 'tab' => 'quotation'])
            ->with('success', 'Quotation linked successfully.');
    }

    public function calculateLive(Request $request, TileQuotationCalculator $calculator)
    {
        $totalAreaSqft = (float) $request->input('total_area_sqft', 0);
        $lengthFt = (float) $request->input('length_ft', 0);
        $widthFt = (float) $request->input('width_ft', 0);
        
        if (($lengthFt <= 0 || $widthFt <= 0) && $totalAreaSqft <= 0) {
            return response()->json(['valid' => false]);
        }

        $includeTile = filter_var($request->input('include_tile_material', false), FILTER_VALIDATE_BOOLEAN);

        $calculation = $calculator->compute(
            $lengthFt,
            $widthFt,
            (float) $request->input('waste_margin_percent', 10),
            empty($request->input('tile_product_id')) ? null : (int) $request->input('tile_product_id'),
            $includeTile,
            $request->input('trowel_size', '1/4x1/4'),
            empty($request->input('mortar_type_id')) ? null : (int) $request->input('mortar_type_id'),
            (float) $request->input('joint_width_decimal', 0.125),
            empty($request->input('grout_type_id')) ? null : (int) $request->input('grout_type_id'),
            (float) $request->input('labor_rate', 0),
            $totalAreaSqft
        );

        $html = view('admin.quotations.tile._live_breakdown', compact('calculation'))->render();

        return response()->json([
            'valid' => true,
            'html' => $html,
            'calculation' => $calculation,
        ]);
    }

    protected function syncProjectQuotation(TileQuotation $quotation): void
    {
        if (! $quotation->client_project_id) {
            return;
        }

        $row = ClientProjectQuotation::query()->firstOrNew([
            'client_project_id' => $quotation->client_project_id,
            'source_type' => ClientProjectQuotation::SOURCE_TILE,
            'source_id' => $quotation->id,
        ]);

        $row->title = $quotation->displayTitle();
        $row->status = $quotation->is_draft ? 'draft' : 'approved';
        $row->display_order = $row->exists ? $row->display_order : 999;
        
        $row->pre_tax_labor_amount = $quotation->labourPreTaxAmountForProjectQuotation();
        $row->pre_tax_materials_amount = $quotation->materialsPreTaxAmountForProjectQuotation();
        
        $row->summary_description = $quotation->summaryDescription();
        $row->work_description = $quotation->scopeDescriptionForPdf();

        $row->save();
    }
}
