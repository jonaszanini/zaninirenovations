<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminCalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCalendarEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $event = AdminCalendarEvent::create($data);

        return response()->json([
            'event' => $event->toFullCalendarEvent(),
        ], 201);
    }

    public function update(Request $request, AdminCalendarEvent $admin_calendar_event): JsonResponse
    {
        $data = $this->validated($request);

        $admin_calendar_event->update($data);

        return response()->json([
            'event' => $admin_calendar_event->fresh()->toFullCalendarEvent(),
        ]);
    }

    public function destroy(AdminCalendarEvent $admin_calendar_event): JsonResponse
    {
        $admin_calendar_event->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{title: string, description: ?string, start_date: string, end_date: string}
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $data['description'] = filled($data['description'] ?? null)
            ? trim((string) $data['description'])
            : null;

        return $data;
    }
}
