<?php

namespace Tests\Feature\Admin;

use App\Models\AdminCalendarEvent;
use App\Models\ClientProject;
use App\Services\ClientProjectCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class ClientProjectCalendarUpgradeTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_calendar_page_loads_fullcalendar_7_with_toronto_and_english(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.client-projects.calendar'))->assertOk()->getContent();

        $this->assertStringContainsString('fullcalendar@7.1.3/all/global.js', $html);
        $this->assertStringContainsString('fullcalendar@7.1.3/themes/classic/global.js', $html);
        $this->assertStringContainsString('fullcalendar@7.1.3/skeleton.css', $html);
        $this->assertStringContainsString('fullcalendar@7.1.3/themes/classic/theme.css', $html);
        $this->assertStringContainsString('fullcalendar@7.1.3/themes/classic/palette.css', $html);
        $this->assertStringContainsString('admin-project-calendar-fc7.css', $html);
        $this->assertStringContainsString('admin-project-calendar-popover.js', $html);
        $this->assertStringNotContainsString('fullcalendar@6.1.11', $html);
        $this->assertStringContainsString("timeZone: 'America/Toronto'", $html);
        $this->assertStringContainsString("locale: 'en'", $html);
        $this->assertStringContainsString('colorScheme:', $html);
        $this->assertStringContainsString('dayMinHeight: 68', $html);
        $this->assertStringContainsString("moreLinkClick: 'popover'", $html);
        $this->assertStringContainsString('cp-calendar-more-link', $html);
        $this->assertStringContainsString('dayGridMonth,timeGridWeek,timeGridDay,listMonth', $html);
        $this->assertStringContainsString('eventDrop: persistMove', $html);
        $this->assertStringContainsString('eventResize: persistMove', $html);
        $this->assertStringContainsString('client-projects\\/calendar\\/move', $html);
    }

    public function test_full_calendar_events_keep_job_visit_and_personal_shapes(): void
    {
        $job = ClientProject::factory()->create([
            'name' => 'Kitchen',
            'client_name' => 'Ada Client',
            'status' => ClientProject::STATUS_SCHEDULED,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
        ]);
        $visit = ClientProject::factory()->create([
            'name' => 'Bathroom',
            'client_name' => 'Bea Client',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'visit_date' => '2026-11-10',
            'visit_time' => '14:00',
            'visit_done' => false,
        ]);
        $doneVisit = ClientProject::factory()->create([
            'name' => 'Deck check',
            'client_name' => 'Cara Client',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'visit_date' => '2026-11-11',
            'visit_time' => null,
            'visit_done' => true,
        ]);
        $personal = AdminCalendarEvent::query()->create([
            'title' => 'Holiday',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-26',
        ]);

        $events = (new ClientProjectCalendarService())->fullCalendarEvents();

        $jobEvent = $events->first(fn ($row) => ($row['id'] ?? '') === 'job-'.$job->id);
        $this->assertNotNull($jobEvent);
        $this->assertSame('Kitchen — Ada Client', $jobEvent['title']);
        $this->assertTrue($jobEvent['allDay']);
        $this->assertSame('2026-11-02', $jobEvent['start']);
        $this->assertSame('2026-11-05', $jobEvent['end']);
        $this->assertSame($job->statusColor(), $jobEvent['backgroundColor']);
        $this->assertSame($job->statusColor(), $jobEvent['color']);
        $this->assertSame($jobEvent['contrastColor'], $jobEvent['textColor']);
        $this->assertContains($jobEvent['contrastColor'], ['#ffffff', '#111827']);
        $this->assertSame(route('admin.client-projects.show', $job), $jobEvent['url']);
        $this->assertTrue($jobEvent['durationEditable']);
        $this->assertSame($job->id, $jobEvent['extendedProps']['recordId']);

        $visitEvent = $events->first(fn ($row) => ($row['id'] ?? '') === 'visit-'.$visit->id);
        $this->assertNotNull($visitEvent);
        $this->assertFalse($visitEvent['allDay']);
        $this->assertSame('2026-11-10T14:00:00', $visitEvent['start']);
        $this->assertSame('2026-11-10T15:00:00', $visitEvent['end']);
        $this->assertFalse($visitEvent['durationEditable']);
        $this->assertStringNotContainsString('+', $visitEvent['start']);

        $doneEvent = $events->first(fn ($row) => ($row['id'] ?? '') === 'visit-'.$doneVisit->id);
        $this->assertNotNull($doneEvent);
        $this->assertSame('#94a3b8', $doneEvent['backgroundColor']);
        $this->assertStringStartsWith('Visit (done):', $doneEvent['title']);

        $personalEvent = $events->first(fn ($row) => ($row['id'] ?? '') === 'personal-'.$personal->id);
        $this->assertNotNull($personalEvent);
        $this->assertSame('Holiday', $personalEvent['title']);
        $this->assertTrue($personalEvent['allDay']);
        $this->assertSame('2026-12-24', $personalEvent['start']);
        $this->assertSame('2026-12-27', $personalEvent['end']);
        $this->assertSame(AdminCalendarEvent::CALENDAR_COLOR, $personalEvent['backgroundColor']);
        $this->assertSame(AdminCalendarEvent::CALENDAR_COLOR, $personalEvent['color']);
        $this->assertSame('#ffffff', $personalEvent['contrastColor']);
    }

    public function test_rejected_projects_stay_off_the_calendar_and_picker(): void
    {
        $rejected = ClientProject::factory()->create([
            'name' => 'Lost job',
            'status' => ClientProject::STATUS_REJECTED,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
            'visit_date' => '2026-11-02',
        ]);

        $service = new ClientProjectCalendarService();
        $full = $service->fullCalendarEvents();
        $picker = collect($service->schedulePickerEvents());

        $this->assertNull($full->first(fn ($row) => in_array($row['id'] ?? '', ['job-'.$rejected->id, 'visit-'.$rejected->id], true)));
        $this->assertNull($picker->first(fn ($row) => in_array($row['id'] ?? '', ['job-'.$rejected->id, 'visit-'.$rejected->id], true)));
    }

    public function test_picker_events_keep_all_day_blocks_for_jobs_and_visits(): void
    {
        $project = ClientProject::factory()->create([
            'name' => 'Fence',
            'client_name' => 'Dana',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'start_date' => '2026-11-08',
            'end_date' => '2026-11-09',
            'visit_date' => '2026-11-07',
            'visit_time' => '09:15',
        ]);

        $events = collect((new ClientProjectCalendarService())->schedulePickerEvents());
        $job = $events->first(fn ($row) => ($row['id'] ?? '') === 'job-'.$project->id);
        $visit = $events->first(fn ($row) => ($row['id'] ?? '') === 'visit-'.$project->id);

        $this->assertNotNull($job);
        $this->assertTrue($job['allDay']);
        $this->assertSame('2026-11-08', $job['start']);
        $this->assertSame('2026-11-10', $job['end']);
        $this->assertArrayNotHasKey('url', $job);

        $this->assertNotNull($visit);
        $this->assertTrue($visit['allDay']);
        $this->assertSame('2026-11-07', $visit['start']);
        $this->assertSame('2026-11-08', $visit['end']);
    }

    public function test_move_updates_job_span_and_rejects_a_timed_drop(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_SCHEDULED,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
        ]);

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'job',
            'id' => $project->id,
            'start' => '2026-11-10',
            'end' => '2026-11-13',
            'all_day' => true,
        ])->assertOk()
            ->assertJsonPath('event.start', '2026-11-10')
            ->assertJsonPath('event.end', '2026-11-13');

        $project->refresh();
        $this->assertSame('2026-11-10', $project->start_date->toDateString());
        $this->assertSame('2026-11-12', $project->end_date->toDateString());

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'job',
            'id' => $project->id,
            'start' => '2026-11-10T09:00:00',
            'end' => '2026-11-10T17:00:00',
            'all_day' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['all_day']);

        $project->refresh();
        $this->assertSame('2026-11-10', $project->start_date->toDateString());
    }

    public function test_move_updates_visit_date_and_time(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'visit_date' => '2026-11-10',
            'visit_time' => '14:00',
            'visit_done' => true,
        ]);

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'visit',
            'id' => $project->id,
            'start' => '2026-11-18T09:30:00',
            'end' => '2026-11-18T10:30:00',
            'all_day' => false,
        ])->assertOk()
            ->assertJsonPath('event.start', '2026-11-18T09:30:00')
            ->assertJsonPath('event.backgroundColor', '#94a3b8');

        $project->refresh();
        $this->assertSame('2026-11-18', $project->visit_date->toDateString());
        $this->assertSame('09:30', substr((string) $project->visit_time, 0, 5));
        $this->assertTrue((bool) $project->visit_done);

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'visit',
            'id' => $project->id,
            'start' => '2026-11-19',
            'end' => '2026-11-20',
            'all_day' => true,
        ])->assertOk();

        $project->refresh();
        $this->assertSame('2026-11-19', $project->visit_date->toDateString());
        $this->assertNull($project->visit_time);
    }

    public function test_move_updates_personal_events_and_rejects_a_timed_overnight_span(): void
    {
        $this->actingAsAdmin();
        $event = AdminCalendarEvent::query()->create([
            'title' => 'Errand',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'personal',
            'id' => $event->id,
            'start' => '2026-11-04T13:00:00',
            'end' => '2026-11-04T15:30:00',
            'all_day' => false,
        ])->assertOk()
            ->assertJsonPath('event.start', '2026-11-04T13:00:00')
            ->assertJsonPath('event.end', '2026-11-04T15:30:00');

        $event->refresh();
        $this->assertSame('2026-11-04', $event->start_date->toDateString());
        $this->assertSame('13:00', substr((string) $event->start_time, 0, 5));
        $this->assertSame('15:30', substr((string) $event->end_time, 0, 5));

        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'personal',
            'id' => $event->id,
            'start' => '2026-11-04T22:00:00',
            'end' => '2026-11-05T01:00:00',
            'all_day' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['end']);

        $allDay = AdminCalendarEvent::query()->create([
            'title' => 'Break',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
        ]);
        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'personal',
            'id' => $allDay->id,
            'start' => '2026-11-08',
            'end' => '2026-11-11',
            'all_day' => true,
        ])->assertOk()
            ->assertJsonPath('event.end', '2026-11-11');

        $allDay->refresh();
        $this->assertSame('2026-11-08', $allDay->start_date->toDateString());
        $this->assertSame('2026-11-10', $allDay->end_date->toDateString());
        $this->assertNull($allDay->start_time);
    }

    public function test_move_rejects_terminal_projects_and_guests(): void
    {
        $rejected = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_REJECTED,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
        ]);

        $this->actingAsAdmin();
        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'job',
            'id' => $rejected->id,
            'start' => '2026-12-01',
            'end' => '2026-12-03',
            'all_day' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['id']);

        $rejected->refresh();
        $this->assertSame('2026-11-01', $rejected->start_date->toDateString());

        auth()->logout();
        $this->postJson(route('admin.client-projects.calendar.move'), [
            'kind' => 'job',
            'id' => $rejected->id,
            'start' => '2026-12-01',
            'end' => '2026-12-03',
            'all_day' => true,
        ])->assertUnauthorized();
    }
}
