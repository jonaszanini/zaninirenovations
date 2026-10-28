<?php

namespace Tests\Feature\Admin;

use App\Models\AdminCalendarEvent;
use App\Services\ClientProjectCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class AdminCalendarPersonalEventTimeTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_store_personal_event_with_times(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Dentist',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('event.allDay', false);
        $response->assertJsonPath('event.title', 'Dentist');
        $response->assertJsonPath('event.extendedProps.timeLabel', '9:00 AM–11:00 AM');
        $response->assertJsonPath('event.end', '2026-11-03T11:00:00');

        $event = AdminCalendarEvent::query()->where('title', 'Dentist')->first();
        $this->assertNotNull($event);
        $this->assertSame('09:00', substr((string) $event->start_time, 0, 5));
        $this->assertSame('11:00', substr((string) $event->end_time, 0, 5));
    }

    public function test_store_personal_event_without_times_stays_all_day(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Holiday',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-26',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('event.allDay', true);
        $response->assertJsonPath('event.title', 'Holiday');
        $response->assertJsonPath('event.extendedProps.timeLabel', null);

        $event = AdminCalendarEvent::query()->where('title', 'Holiday')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->start_time);
        $this->assertNull($event->end_time);
    }

    public function test_store_rejects_end_time_before_start_time(): void
    {
        $this->actingAsAdmin();

        $before = AdminCalendarEvent::query()->count();

        $response = $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Bad slot',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => '14:00',
            'end_time' => '13:30',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_time']);
        $this->assertSame($before, AdminCalendarEvent::query()->count());
    }

    public function test_store_rejects_invalid_time_strings(): void
    {
        $this->actingAsAdmin();

        $before = AdminCalendarEvent::query()->count();

        $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Bad time',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => 'abc',
        ])->assertUnprocessable()->assertJsonValidationErrors(['start_time']);

        $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Bad time',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => '9:00 AM',
        ])->assertUnprocessable()->assertJsonValidationErrors(['start_time']);

        $this->assertSame($before, AdminCalendarEvent::query()->count());
    }

    public function test_store_rejects_array_start_time_with_422(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.calendar.personal-events.store'), [
            'title' => 'Array time',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => ['09', '00'],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_update_with_invalid_time_does_not_wipe_existing_times(): void
    {
        $this->actingAsAdmin();

        $event = AdminCalendarEvent::query()->create([
            'title' => 'Keep times',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->putJson(route('admin.calendar.personal-events.update', $event), [
            'title' => 'Keep times',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-05',
            'start_time' => '9:00 AM',
            'end_time' => '11:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_time']);

        $event->refresh();
        $this->assertSame('10:00', substr((string) $event->start_time, 0, 5));
        $this->assertSame('11:00', substr((string) $event->end_time, 0, 5));
    }

    public function test_start_only_timed_event_has_no_end_in_payload(): void
    {
        $event = AdminCalendarEvent::query()->create([
            'title' => 'Quick errand',
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'start_time' => '23:30:00',
            'end_time' => null,
        ]);

        $payload = $event->toFullCalendarEvent();
        $this->assertFalse($payload['allDay']);
        $this->assertSame('2026-11-12T23:30:00', $payload['start']);
        $this->assertArrayNotHasKey('end', $payload);
        $this->assertSame('11:30 PM', $payload['extendedProps']['timeLabel']);
    }

    public function test_schedule_picker_feed_uses_date_only_for_timed_personal_events(): void
    {
        AdminCalendarEvent::query()->create([
            'title' => 'Blocked day',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-20',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $event = AdminCalendarEvent::query()->where('title', 'Blocked day')->first();
        $this->assertNotNull($event);

        $events = (new ClientProjectCalendarService())->schedulePickerEvents();
        $personal = collect($events)->first(
            fn ($row) => ($row['extendedProps']['eventId'] ?? null) === $event->id
        );

        $this->assertNotNull($personal);
        $this->assertSame('2026-11-20', $personal['start']);
        $this->assertSame('2026-11-21', $personal['end']);
        $this->assertTrue($personal['allDay']);
        $this->assertStringNotContainsString('T', $personal['start']);
    }

    public function test_time_range_display_and_calendar_payload(): void
    {
        $event = AdminCalendarEvent::query()->create([
            'title' => 'Team lunch',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
            'start_time' => '12:30:00',
            'end_time' => '13:30:00',
        ]);

        $this->assertSame('12:30 PM–1:30 PM', $event->timeRangeDisplayLabel());

        $payload = $event->toFullCalendarEvent();
        $this->assertFalse($payload['allDay']);
        $this->assertSame('Team lunch', $payload['title']);
        $this->assertSame('2026-11-10T12:30:00', $payload['start']);
        $this->assertSame('2026-11-10T13:30:00', $payload['end']);

        $fromService = (new ClientProjectCalendarService())->fullCalendarEvents()
            ->first(fn ($row) => ($row['extendedProps']['eventId'] ?? null) === $event->id);
        $this->assertNotNull($fromService);
        $this->assertSame('12:30 PM–1:30 PM', $fromService['extendedProps']['timeLabel']);
    }

    public function test_calendar_form_includes_time_inputs_and_clear_buttons(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.client-projects.calendar'))->assertOk()->getContent();

        $this->assertStringContainsString('id="cp-personal-event-start-time"', $html);
        $this->assertStringContainsString('id="cp-personal-event-end-time"', $html);
        $this->assertStringContainsString('data-clear-personal-time="start"', $html);
        $this->assertStringContainsString('data-clear-personal-time="end"', $html);
        $this->assertStringContainsString('aria-label="Clear start time"', $html);
        $this->assertStringContainsString('cp-personal-event-times', $html);
        $this->assertStringContainsString('id="cp-personal-event-time-notice"', $html);
    }
}
