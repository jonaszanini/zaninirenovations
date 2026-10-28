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
        $data = $this->validated($request, $admin_calendar_event);

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
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?AdminCalendarEvent $existing = null): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];

        if ($request->exists('start_time')) {
            $rules['start_time'] = ['nullable', 'string'];
        }
        if ($request->exists('end_time')) {
            $rules['end_time'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        $data['description'] = filled($data['description'] ?? null)
            ? trim((string) $data['description'])
            : null;

        if ($request->exists('start_time')) {
            $data['start_time'] = $this->parseOptionalTimeField($request->input('start_time'), 'start_time');
        } elseif ($existing === null) {
            $data['start_time'] = null;
        } else {
            unset($data['start_time']);
        }

        if ($request->exists('end_time')) {
            $data['end_time'] = $this->parseOptionalTimeField($request->input('end_time'), 'end_time');
        } elseif ($existing === null) {
            $data['end_time'] = null;
        } else {
            unset($data['end_time']);
        }

        $startTime = array_key_exists('start_time', $data) ? $data['start_time'] : $existing?->start_time;
        $endTime = array_key_exists('end_time', $data) ? $data['end_time'] : $existing?->end_time;

        if (filled($endTime) && ! filled($startTime)) {
            throw ValidationException::withMessages([
                'end_time' => ['End time requires a start time.'],
            ]);
        }

        if (filled($startTime) && filled($endTime) && $endTime <= $startTime) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must be after the start time.'],
            ]);
        }

        if (filled($startTime) && $data['start_date'] !== $data['end_date']) {
            throw ValidationException::withMessages([
                'start_time' => ['Timed personal events must use the same start and end date.'],
            ]);
        }

        return $data;
    }

    /**
     * @return string|null normalized H:i, or null when cleared
     */
    private function parseOptionalTimeField(mixed $raw, string $field): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw)) {
            throw ValidationException::withMessages([
                $field => ['The '.$field.' must be a string.'],
            ]);
        }

        $normalized = ClientProject::normalizeTimeToHi($raw);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                $field => ['Enter a valid time (HH:MM).'],
            ]);
        }

        return $normalized;
    }
}
