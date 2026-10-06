<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Services\ClientProjectCalendarService;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectVisitTimeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_store_project_with_visit_date_and_time(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $response = $this->post(route('admin.client-projects.store'), [
            'name' => 'Kitchen reno',
            'contacted_via' => 'email',
            'client_name' => 'Test Client',
            'visit_required' => '1',
            'visit_date' => '2026-10-07',
            'visit_time' => '14:30',
            'billing_type' => 'fixed',
            'send_client_email' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project = ClientProject::where('name', 'Kitchen reno')->first();
        $this->assertNotNull($project);
        $this->assertSame('2026-10-07', $project->visit_date->toDateString());
        $this->assertStringStartsWith('14:30', $project->visit_time);
        $this->assertTrue($project->visit_required);
    }

    public function test_store_project_with_visit_date_without_time(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $response = $this->post(route('admin.client-projects.store'), [
            'name' => 'Basement finish',
            'contacted_via' => 'call',
            'client_name' => 'Another Client',
            'visit_required' => '1',
            'visit_date' => '2026-10-08',
            'billing_type' => 'fixed',
            'send_client_email' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project = ClientProject::where('name', 'Basement finish')->first();
        $this->assertNotNull($project);
        $this->assertSame('2026-10-08', $project->visit_date->toDateString());
        $this->assertNull($project->visit_time);
    }

    public function test_update_project_adds_visit_time(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Deck build',
            'visit_required' => true,
            'visit_date' => '2026-10-10',
            'visit_time' => null,
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Deck build',
            'client_name' => $project->client_name,
            'visit_required' => '1',
            'visit_date' => '2026-10-10',
            'visit_time' => '09:15',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertStringStartsWith('09:15', $project->visit_time);
    }

    public function test_update_project_clears_visit_time_when_visit_required_is_false(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Fence repair',
            'visit_required' => true,
            'visit_date' => '2026-10-12',
            'visit_time' => '10:00',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Fence repair',
            'client_name' => $project->client_name,
            'visit_required' => '0',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertFalse($project->visit_required);
        $this->assertNull($project->visit_date);
        $this->assertNull($project->visit_time);
    }

    /**
     * Invalid time format gets normalized to null (no time set).
     */
    public function test_invalid_visit_time_format_normalized_to_null(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Pool deck',
            'visit_required' => true,
            'visit_date' => '2026-10-15',
            'visit_time' => '10:00',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        // Invalid format gets normalized to null (clears the time)
        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'visit_required' => '1',
            'visit_date' => '2026-10-15',
            'visit_time' => 'invalid',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertNull($project->visit_time);
    }

    public function test_calendar_returns_timed_event_when_visit_time_set(): void
    {
        $project = ClientProject::factory()->create([
            'name' => 'Bathroom reno',
            'client_name' => 'Smith',
            'visit_required' => true,
            'visit_date' => '2026-10-20',
            'visit_time' => '14:00',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $service = new ClientProjectCalendarService();
        $events = $service->fullCalendarEvents();

        $visitEvent = $events->first(fn ($e) => str_starts_with($e['id'], 'visit-'));
        $this->assertNotNull($visitEvent);
        $this->assertFalse($visitEvent['allDay']);
        $this->assertStringContainsString('2026-10-20T14:00', $visitEvent['start']);
        $this->assertStringContainsString('2026-10-20T15:00', $visitEvent['end']);
    }

    public function test_calendar_returns_all_day_event_when_visit_time_not_set(): void
    {
        $project = ClientProject::factory()->create([
            'name' => 'Patio pour',
            'client_name' => 'Johnson',
            'visit_required' => true,
            'visit_date' => '2026-10-21',
            'visit_time' => null,
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $service = new ClientProjectCalendarService();
        $events = $service->fullCalendarEvents();

        $visitEvent = $events->first(fn ($e) => str_starts_with($e['id'], 'visit-'));
        $this->assertNotNull($visitEvent);
        $this->assertTrue($visitEvent['allDay']);
        $this->assertSame('2026-10-21', $visitEvent['start']);
        $this->assertSame('2026-10-22', $visitEvent['end']);
    }

    public function test_visit_date_time_display_shows_time_when_set(): void
    {
        $project = ClientProject::factory()->create([
            'visit_date' => '2026-10-07',
            'visit_time' => '14:30',
        ]);

        $display = $project->visitDateTimeDisplay();
        $this->assertStringContainsString('07/10/2026', $display);
        $this->assertStringContainsString('2:30 PM EST', $display);
    }

    public function test_visit_date_time_display_shows_only_date_when_time_not_set(): void
    {
        $project = ClientProject::factory()->create([
            'visit_date' => '2026-10-07',
            'visit_time' => null,
        ]);

        $display = $project->visitDateTimeDisplay();
        $this->assertSame('07/10/2026', $display);
    }

    /**
     * Calendar timed events must use floating local format (no TZ offset)
     * so FullCalendar displays the exact wall-clock time without conversion.
     */
    public function test_calendar_timed_event_uses_floating_local_format_without_offset(): void
    {
        $project = ClientProject::factory()->create([
            'name' => 'Roof inspection',
            'client_name' => 'Williams',
            'visit_required' => true,
            'visit_date' => '2026-10-25',
            'visit_time' => '09:30',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $service = new ClientProjectCalendarService();
        $events = $service->fullCalendarEvents();

        $visitEvent = $events->first(fn ($e) => str_starts_with($e['id'], 'visit-'));
        $this->assertNotNull($visitEvent);

        // Must be floating local format: 2026-10-25T09:30:00 (no +00:00 or Z suffix)
        $this->assertSame('2026-10-25T09:30:00', $visitEvent['start']);
        $this->assertSame('2026-10-25T10:30:00', $visitEvent['end']);
        $this->assertStringNotContainsString('+', $visitEvent['start']);
        $this->assertStringNotContainsString('Z', $visitEvent['start']);
    }

    /**
     * MySQL TIME returns H:i:s format. The server should accept and normalize it.
     */
    public function test_update_accepts_visit_time_with_seconds(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Siding replacement',
            'visit_required' => true,
            'visit_date' => '2026-10-18',
            'visit_time' => null,
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        // Browser may submit "14:30:00" (H:i:s) when re-saving existing value
        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Siding replacement',
            'client_name' => $project->client_name,
            'visit_required' => '1',
            'visit_date' => '2026-10-18',
            'visit_time' => '14:30:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertStringStartsWith('14:30', $project->visit_time);
    }

    /**
     * Re-saving the edit form when a project already has visit_time should succeed.
     */
    public function test_resave_edit_form_with_existing_visit_time_succeeds(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Window install',
            'visit_required' => true,
            'visit_date' => '2026-10-22',
            'visit_time' => '11:45:00', // MySQL TIME format
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        // The form uses visitTimeForInput() which returns H:i
        $timeForInput = $project->visitTimeForInput();
        $this->assertSame('11:45', $timeForInput);

        // Submit the form with the same values (simulating re-save)
        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Window install',
            'client_name' => $project->client_name,
            'visit_required' => '1',
            'visit_date' => '2026-10-22',
            'visit_time' => $timeForInput,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertStringStartsWith('11:45', $project->visit_time);
    }
}
