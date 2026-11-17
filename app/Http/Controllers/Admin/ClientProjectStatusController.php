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
            $message = "This project has been marked as {$label} and is final.";

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
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
                if ($request->expectsJson()) {
                    return $this->statusJsonResponse($client_project, 'Status unchanged.');
                }

                return back()->with('success', 'Status unchanged.');
            }

            $updated = $service->updateStatusNote($client_project, $note);
            $message = $note ? 'Status note saved.' : 'Status note cleared.';

            if ($request->expectsJson()) {
                return $this->statusJsonResponse($updated, $message);
            }

            return back()->with('success', $message);
        }

        $updated = $service->transition($client_project, $data['status'], $note, true);

        if ($request->expectsJson()) {
            return $this->statusJsonResponse($updated, 'Status updated.');
        }

        return back()->with('success', 'Status updated.');
    }

    public function reopen(
        Request $request,
        ClientProject $client_project,
        ClientProjectStatusService $service,
    ) {
        if (! $client_project->isRejected()) {
            $message = 'Only rejected projects can be reopened.';

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(ClientProject::reopenableStatuses())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $targetStatus = $data['status'];
        $note = filled($data['note'] ?? null)
            ? trim((string) $data['note'])
            : 'Project reopened from Rejected.';

        $updated = $service->transition($client_project, $targetStatus, $note, true);

        $label = ClientProject::statusLabels()[$targetStatus] ?? $targetStatus;
        $message = "Project reopened — status set to {$label}.";

        if ($request->expectsJson()) {
            return $this->statusJsonResponse($updated, $message);
        }

        return back()->with('success', $message);
    }

    private function statusJsonResponse(ClientProject $project, string $message)
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'status' => $project->status,
            'status_label' => $project->statusLabel(),
            'status_color' => $project->statusColor(),
            'status_note' => $project->status_note,
            'is_closed' => $project->isClosed(),
            'is_cancelled' => $project->isCancelled(),
            'is_rejected' => $project->isRejected(),
        ]);
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
