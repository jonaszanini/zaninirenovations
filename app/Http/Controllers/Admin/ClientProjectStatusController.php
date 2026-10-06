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
        if ($client_project->isClosed()) {
            $label = $client_project->isCancelled() ? 'Cancelled' : 'Rejected';

            return back()->with('error', "This project has been marked as {$label} and is final.");
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ClientProject::statuses())],
            'note' => [
                Rule::requiredIf(fn () => $request->input('status') === ClientProject::STATUS_CANCELLED),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $note = isset($data['note']) ? trim((string) $data['note']) : '';
        $note = $note !== '' ? $note : null;

        if ($data['status'] === $client_project->status) {
            $unchanged = (filled($client_project->status_note) ? trim((string) $client_project->status_note) : null) === $note;
            if ($unchanged) {
                return back()->with('success', 'Status unchanged.');
            }

            $service->updateStatusNote($client_project, $note);

            return back()->with('success', $note ? 'Status note saved.' : 'Status note cleared.');
        }

        $service->transition($client_project, $data['status'], $note, true);

        return back()->with('success', 'Status updated.');
    }

    public function reopen(
        Request $request,
        ClientProject $client_project,
        ClientProjectStatusService $service,
    ) {
        if (! $client_project->isRejected()) {
            return back()->with('error', 'Only rejected projects can be reopened.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ClientProject::reopenableStatuses())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $targetStatus = $data['status'];
        $note = filled($data['note'] ?? null)
            ? trim((string) $data['note'])
            : 'Project reopened from Rejected.';

        $service->transition($client_project, $targetStatus, $note, true);

        $label = ClientProject::statusLabels()[$targetStatus] ?? $targetStatus;

        return back()->with('success', "Project reopened — status set to {$label}.");
    }

    /**
     * Quick one-click status change (no note required). Used by header buttons.
     */
    public function quick(
        Request $request,
        ClientProject $client_project,
        ClientProjectStatusService $service,
    ) {
        if ($client_project->isClosed()) {
            $label = $client_project->isCancelled() ? 'Cancelled' : 'Rejected';

            return back()->with('error', "This project has been marked as {$label} and is final.");
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ClientProject::statuses())],
        ]);

        $newStatus = $data['status'];

        if ($newStatus === $client_project->status) {
            return back()->with('success', 'Status unchanged.');
        }

        if ($newStatus === ClientProject::STATUS_CANCELLED) {
            return back()->with('error', 'Use the full status form to cancel a project (requires a reason).');
        }

        $allowed = $client_project->quickStatusOptions();
        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', 'Invalid status transition.');
        }

        $label = ClientProject::statusLabels()[$newStatus] ?? $newStatus;
        $service->transition($client_project, $newStatus, "Quick status change to {$label}.", false);

        return back()->with('success', "Status updated to {$label}.");
    }
}
