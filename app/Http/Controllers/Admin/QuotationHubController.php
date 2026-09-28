<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Services\QuotationClientEmailMessageAiService;
use App\Services\QuotationWorkDescriptionAiService;
use App\Support\QuotationClientEmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hub landing page for standalone quotations: Fence (structural calculator)
 * and Standard (materials + labour grid), with room for more card types later.
 */
class QuotationHubController extends Controller
{
    public function index(Request $request)
    {
        $hubProject = null;
        $hubProjectError = null;

        if ($request->filled('project')) {
            $id = (int) $request->query('project');
            $project = ClientProject::query()->find($id);
            if (! $project) {
                $hubProjectError = 'That project was not found.';
            } elseif (in_array($project->status, ClientProject::statusesClosedToNewQuotations(), true)) {
                $hubProjectError = 'This project no longer accepts new quotations.';
            } else {
                $hubProject = $project;
            }
        }

        return view('admin.quotations.index', [
            'hubProject' => $hubProject,
            'hubProjectError' => $hubProjectError,
        ]);
    }

    public function aiClientEmailMessage(Request $request, QuotationClientEmailMessageAiService $service): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.QuotationClientEmailMessage::MAX_LENGTH],
        ]);

        try {
            return response()->json(['message' => $service->refine($data['message'])]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function aiWorkDescription(Request $request, QuotationWorkDescriptionAiService $service): JsonResponse
    {
        $data = $request->validate([
            'contractor_notes' => ['nullable', 'string', 'max:5000'],
            'quotation_type' => ['nullable', 'string', 'max:32'],
            'context' => ['nullable', 'array'],
            'mode' => ['nullable', 'in:improve,from_quotation'],
        ]);

        try {
            $mode = $data['mode'] ?? 'improve';
            if ($mode === 'from_quotation') {
                return response()->json([
                    'description' => $service->composeFromQuotation(
                        (string) ($data['quotation_type'] ?? ''),
                        $data['context'] ?? null,
                    ),
                ]);
            }

            return response()->json([
                'description' => $service->suggest(
                    (string) ($data['contractor_notes'] ?? ''),
                    $data['quotation_type'] ?? null,
                    $data['context'] ?? null,
                ),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
