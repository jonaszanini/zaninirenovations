<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\MeasurementProject;
use App\Models\MeasurementRow;
use App\Services\MeasurementConversionService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MeasurementConversionController extends Controller
{
    public function index()
    {
        $projects = MeasurementProject::query()
            ->with([
                'rows',
                'clientProject:id,name',
            ])
            ->orderBy('name')
            ->get();

        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED, ClientProject::STATUS_COMPLETED])
            ->orderBy('name')
            ->get(['id', 'name']);

        $converter = new MeasurementConversionService;

        return view('admin.utilities.measurement_conversion', [
            'projects' => $projects,
            'clientProjects' => $clientProjects,
            'unitLabels' => MeasurementConversionService::unitLabels(),
            'projectsJson' => $projects->map(function (MeasurementProject $project) use ($converter) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_project_id' => $project->client_project_id,
                    'client_project_name' => $project->clientProject?->name,
                    'rows' => $project->rows->map(fn (MeasurementRow $row) => $converter->rowPayload($row))->values(),
                ];
            })->values(),
        ]);
    }

    public function storeProject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
        ]);

        $project = MeasurementProject::create([
            'name' => $data['name'],
            'client_project_id' => $data['client_project_id'] ?? null,
        ]);

        $project->load('clientProject:id,name');

        return response()->json([
            'ok' => true,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'client_project_id' => $project->client_project_id,
                'client_project_name' => $project->clientProject?->name,
                'rows' => [],
            ],
        ]);
    }

    public function updateProject(Request $request, MeasurementProject $measurement_project)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
        ]);

        $measurement_project->fill($data);
        $measurement_project->save();
        $measurement_project->load('clientProject:id,name');

        return response()->json([
            'ok' => true,
            'project' => [
                'id' => $measurement_project->id,
                'name' => $measurement_project->name,
                'client_project_id' => $measurement_project->client_project_id,
                'client_project_name' => $measurement_project->clientProject?->name,
            ],
        ]);
    }

    public function destroyProject(MeasurementProject $measurement_project)
    {
        $measurement_project->delete();

        return response()->json(['ok' => true]);
    }

    public function storeRow(Request $request, MeasurementProject $measurement_project, MeasurementConversionService $converter)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'source_unit' => ['required', 'string', 'in:'.implode(',', MeasurementConversionService::unitKeys())],
            'source_value' => ['required', 'string', 'max:80'],
        ]);

        try {
            $baseMm = $converter->parseToMillimetres($data['source_unit'], $data['source_value']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        if ($baseMm < 0) {
            return response()->json(['ok' => false, 'message' => 'Measurement must be zero or positive.'], 422);
        }

        $sortOrder = (int) $measurement_project->rows()->max('sort_order') + 1;

        $row = $measurement_project->rows()->create([
            'label' => $data['label'] ?? null,
            'source_unit' => $data['source_unit'],
            'source_value' => trim($data['source_value']),
            'base_mm' => round($baseMm, 6),
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'ok' => true,
            'row' => $converter->rowPayload($row),
        ]);
    }

    public function destroyRow(MeasurementRow $measurement_row)
    {
        $measurement_row->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, MeasurementConversionService $converter)
    {
        $data = $request->validate([
            'source_unit' => ['required', 'string', 'in:'.implode(',', MeasurementConversionService::unitKeys())],
            'source_value' => ['required', 'string', 'max:80'],
        ]);

        try {
            $baseMm = $converter->parseToMillimetres($data['source_unit'], $data['source_value']);
            if ($baseMm < 0) {
                throw new InvalidArgumentException('Measurement must be zero or positive.');
            }

            return response()->json([
                'ok' => true,
                'formats' => $converter->formatsFromMillimetres($baseMm),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
