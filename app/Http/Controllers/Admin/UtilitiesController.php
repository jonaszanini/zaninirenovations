<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                'name' => 'Pillar & Spindle Layout Calculator',
                'description' => 'Work out how many pillars or spindles you need, the exact spacing between them, and preview the layout before you build.',
                'icon' => 'fa-arrows-h',
                'route' => route('admin.utilities.pillar-spindle'),
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
     * Persist a named calculation (generic for any utility tool).
     */
    public function storeCalculation(Request $request)
    {
        $data = $request->validate([
            'tool' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'payload' => ['required', 'array'],
        ]);

        $calculation = UtilityCalculation::create([
            'tool' => $data['tool'],
            'name' => $data['name'],
            'payload' => $data['payload'],
        ]);

        return response()->json([
            'ok' => true,
            'calculation' => [
                'id' => $calculation->id,
                'name' => $calculation->name,
                'payload' => $calculation->payload,
                'created_at' => $calculation->created_at?->toIso8601String(),
                'created_label' => $calculation->created_at?->format('M j, Y g:i A'),
            ],
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
            'diagram' => $diagram,
            'generatedAt' => now()->format('M j, Y g:i A'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream('pillar-spindle-layout.pdf');
    }
}
