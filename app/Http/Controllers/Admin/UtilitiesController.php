<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\UtilityCalculation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class UtilitiesController extends Controller
{
    /**
     * Landing page that lists every available utility tool. New tools can be
     * added to the $tools array (and given their own route + page) without
     * touching the layout.
     */
    public function index()
    {
        $tools = [
            [
                'name' => 'Pillar & Spindle',
                'icon' => 'pillar_spindle',
                'route' => route('admin.utilities.pillar-spindle'),
                'available' => true,
            ],
            [
                'name' => 'Measurement Conversion',
                'icon' => 'measurement',
                'route' => route('admin.utilities.measurement-conversion'),
                'available' => true,
            ],
            [
                'name' => 'Lumber / Board Material',
                'icon' => 'lumber_board',
                'route' => route('admin.utilities.lumber-board'),
                'available' => true,
            ],
        ];

        return view('admin.utilities.index', [
            'tools' => $tools,
        ]);
    }

    /**
     * Pillar & Spindle Layout Calculator. All maths run live in the browser;
     * this method only feeds the page the saved calculations for this tool.
     */
    public function pillarSpindle()
    {
        $saved = UtilityCalculation::query()
            ->where('tool', UtilityCalculation::TOOL_PILLAR_SPINDLE)
            ->latest()
            ->get(['id', 'name', 'payload', 'created_at']);

        return view('admin.utilities.pillar_spindle', [
            'savedCalculations' => $saved,
        ]);
    }

    /**
     * Lumber / Board Material Calculator. Packing runs in the browser; this
     * method only supplies saved calculations for this tool.
     */
    public function lumberBoard(Request $request)
    {
        $saved = UtilityCalculation::query()
            ->where('tool', UtilityCalculation::TOOL_LUMBER_BOARD)
            ->with('clientProject:id,name,client_name')
            ->latest()
            ->get(['id', 'name', 'client_project_id', 'payload', 'created_at']);

        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED, ClientProject::STATUS_COMPLETED])
            ->orderBy('client_name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_name']);

        $linkProjectId = max(0, (int) $request->query('project', 0)) ?: null;
        $linkProject = $linkProjectId
            ? ClientProject::query()->find($linkProjectId, ['id', 'name', 'client_name'])
            : null;

        if ($linkProject && ! $clientProjects->contains('id', $linkProject->id)) {
            $clientProjects->prepend($linkProject);
        }

        return view('admin.utilities.lumber_board', [
            'savedCalculations' => $saved,
            'clientProjects' => $clientProjects,
            'linkProject' => $linkProject,
        ]);
    }

    /**
     * PDF export for a Lumber / Board Material calculation (diagrams as PNG).
     */
    public function lumberBoardPdf(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.label' => ['required', 'string', 'max:120'],
            'rows.*.value' => ['required', 'string', 'max:120'],
            'breakdown' => ['nullable', 'array'],
            'breakdown.*.label' => ['required', 'string', 'max:120'],
            'breakdown.*.value' => ['required', 'string', 'max:120'],
            'diagrams' => ['nullable', 'array'],
            'diagrams.*' => ['nullable', 'string'],
        ]);

        $diagrams = [];
        foreach ($data['diagrams'] ?? [] as $diagram) {
            if (is_string($diagram) && str_starts_with($diagram, 'data:image/png;base64,')) {
                $diagrams[] = $diagram;
            }
        }

        $pdf = Pdf::loadView('admin.utilities.lumber_board_pdf', [
            'title' => $data['title'] ?? 'Lumber / Board Material',
            'summary' => $data['summary'] ?? '',
            'rows' => $data['rows'],
            'breakdown' => $data['breakdown'] ?? [],
            'diagrams' => $diagrams,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream('lumber-board-material.pdf');
    }

    /**
     * Persist a named calculation (generic for any utility tool).
     */
    public function storeCalculation(Request $request)
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'payload' => ['required', 'array'],
        ]);

        $calculation = UtilityCalculation::create([
            'tool' => $data['tool'],
            'name' => $data['name'],
            'client_project_id' => $data['client_project_id'] ?? null,
            'payload' => $data['payload'],
        ]);

        $calculation->load('clientProject:id,name,client_name');

        return response()->json([
            'ok' => true,
            'calculation' => $this->utilityCalculationJson($calculation),
        ]);
    }

    /**
     * Update a saved calculation (same tool only).
     */
    public function updateCalculation(Request $request, UtilityCalculation $utility_calculation)
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'payload' => ['required', 'array'],
        ]);

        if ($data['tool'] !== $utility_calculation->tool) {
            return response()->json(['message' => 'Tool mismatch.'], 422);
        }

        $utility_calculation->update([
            'name' => $data['name'],
            'client_project_id' => $data['client_project_id'] ?? null,
            'payload' => $data['payload'],
        ]);

        $utility_calculation->load('clientProject:id,name,client_name');

        return response()->json([
            'ok' => true,
            'calculation' => $this->utilityCalculationJson($utility_calculation),
        ]);
    }

    public function destroyCalculation(UtilityCalculation $utility_calculation)
    {
        $utility_calculation->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Render a PDF of a Pillar & Spindle result. The diagram is rendered in
     * the browser and posted here as a PNG data URI so dompdf can embed it
     * without needing SVG support.
     */
    public function pillarSpindlePdf(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:400'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.label' => ['required', 'string', 'max:120'],
            'rows.*.value' => ['required', 'string', 'max:120'],
            'marks' => ['nullable', 'array'],
            'marks.*.n' => ['required', 'string', 'max:10'],
            'marks.*.start' => ['required', 'string', 'max:40'],
            'marks.*.end' => ['required', 'string', 'max:40'],
            'diagram' => ['nullable', 'string'],
        ]);

        $diagram = $data['diagram'] ?? '';
        if ($diagram !== '' && ! str_starts_with($diagram, 'data:image/png;base64,')) {
            $diagram = '';
        }

        $pdf = Pdf::loadView('admin.utilities.pillar_spindle_pdf', [
            'title' => $data['title'] ?? 'Pillar & Spindle Layout',
            'summary' => $data['summary'] ?? '',
            'rows' => $data['rows'],
            'marks' => $data['marks'] ?? [],
            'diagram' => $diagram,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream('pillar-spindle-layout.pdf');
    }

    /** @return array<string, mixed> */
    private function utilityCalculationJson(UtilityCalculation $calculation): array
    {
        return [
            'id' => $calculation->id,
            'name' => $calculation->name,
            'client_project_id' => $calculation->client_project_id,
            'client_project_name' => $calculation->clientProject?->linkSelectLabel(),
            'payload' => $calculation->payload,
            'created_at' => $calculation->created_at?->toIso8601String(),
            'created_label' => $calculation->created_at?->format('M j, Y g:i A'),
            'saved_label' => $calculation->updated_at?->format('M j, Y g:i A'),
        ];
    }
}
