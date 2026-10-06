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

    public function test_visit_time_validation_rejects_invalid_format(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'visit_required' => true,
            'visit_date' => '2026-10-15',
        ]);

        $response = $this->from(route('admin.client-projects.edit', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => $project->name,
                'client_name' => $project->client_name,
                'visit_required' => '1',
                'visit_date' => '2026-10-15',
                'visit_time' => 'invalid',
            ]);

        $response->assertSessionHasErrors('visit_time');
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
}
