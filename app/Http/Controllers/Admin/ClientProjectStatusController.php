<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles status transitions posted from the project detail page.
 * Business rules & side-effects live in ClientProjectStatusService.
 */
class ClientProjectStatusController extends Controller
{
    public function update(
        Request $request,
        ClientProject $client_project,
        ClientProjectStatusService $service
    ) {
        $data = $request->validate([
            'status' => ['required', Rule::in(ClientProject::statuses())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($client_project->isRejected()) {
            return back()->with('error', 'This project has been marked as Rejected and is final.');
        }

        $service->transition($client_project, $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Status updated.');
    }
}
