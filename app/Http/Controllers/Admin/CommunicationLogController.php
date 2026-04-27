<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use Illuminate\Http\Request;

/**
 * Read-only "Email / SMS log" page in the admin sidebar.
 *
 * Displays every row in client_communication_logs (newest first) with
 * filters by channel, reason, direction, and free-text search across
 * client name / project name / recipient / description / subject.
 */
class CommunicationLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'channel' => in_array($request->query('channel'), [
                ClientCommunicationLog::CHANNEL_EMAIL,
                ClientCommunicationLog::CHANNEL_SMS,
            ], true) ? (string) $request->query('channel') : '',

            'reason' => array_key_exists((string) $request->query('reason'), ClientCommunicationLog::REASON_LABELS)
                ? (string) $request->query('reason')
                : '',

            'direction' => in_array($request->query('direction'), [
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
            ], true) ? (string) $request->query('direction') : '',

            'q' => trim((string) $request->query('q', '')),
        ];

        $logs = ClientCommunicationLog::query()
            ->with('project:id,name,client_name')
            ->channel($filters['channel'] ?: null)
            ->reason($filters['reason'] ?: null)
            ->direction($filters['direction'] ?: null)
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $needle = '%'.$filters['q'].'%';
                $q->where(function ($w) use ($needle) {
                    $w->where('project_name_snapshot', 'like', $needle)
                        ->orWhere('client_name_snapshot', 'like', $needle)
                        ->orWhere('recipient', 'like', $needle)
                        ->orWhere('subject', 'like', $needle)
                        ->orWhere('description', 'like', $needle);
                });
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.communications.index', [
            'logs' => $logs,
            'filters' => $filters,
            'reasonOptions' => ClientCommunicationLog::REASON_LABELS,
        ]);
    }
}
