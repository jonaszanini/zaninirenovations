<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminCalendarEvent;
use App\Models\ClientProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminCalendarEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $event = AdminCalendarEvent::create($data);
        $this->rememberSubmitSucceeded($request);

        return response()->json([
            'ok' => true,
            'event' => $event->toFullCalendarEvent(),
        ], 201);
    }

    public function update(Request $request, AdminCalendarEvent $admin_calendar_event): JsonResponse
    {
        $data = $this->validated($request);

        $admin_calendar_event->update($data);

        return response()->json([
            'ok' => true,
            'event' => $admin_calendar_event->fresh()->toFullCalendarEvent(),
        ]);
    }

    public function destroy(AdminCalendarEvent $admin_calendar_event): JsonResponse
    {
        $admin_calendar_event->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     start_date: string,
     *     end_date: string,
     *     start_time: ?string,
     *     end_time: ?string
     * }
     */
    protected function validated(Request $request): array
    {
        $request->merge([
            'start_time' => ClientProject::normalizeTimeToHi($request->input('start_time')),
            'end_time' => ClientProject::normalizeTimeToHi($request->input('end_time')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $data['description'] = filled($data['description'] ?? null)
            ? trim((string) $data['description'])
            : null;

        $data['start_time'] = filled($data['start_time'] ?? null) ? $data['start_time'] : null;
        $data['end_time'] = filled($data['end_time'] ?? null) ? $data['end_time'] : null;

        if (filled($data['end_time']) && ! filled($data['start_time'])) {
            throw ValidationException::withMessages([
                'end_time' => ['End time requires a start time.'],
            ]);
        }

        if (filled($data['start_time']) && filled($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after the start time.'],
            ]);
        }

        if (filled($data['start_time']) && $data['start_date'] !== $data['end_date']) {
            throw ValidationException::withMessages([
                'start_time' => ['Timed personal events must use the same start and end date.'],
            ]);
        }

        return $data;
    }
}
