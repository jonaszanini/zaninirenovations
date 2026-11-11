<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectOverviewInlineEditTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_overview_renders_compact_grid_and_inline_visit_schedule_controls(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen renovation',
            'client_name' => 'Jane Chen',
            'visit_required' => true,
            'visit_date' => '2026-11-12',
            'visit_time' => '10:00:00',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('class="cp-overview-grid"', false)
            ->assertSee('data-cp-overview-edit="visit"', false)
            ->assertSee('data-cp-overview-edit="schedule"', false)
            ->assertSee('name="return_tab"', false)
            ->assertSee('id="cp-overview-visit-form"', false)
            ->assertSee('id="cp-overview-schedule-form"', false)
            ->assertSee('aria-label="Edit visit"', false)
            ->assertSee('aria-label="Edit schedule"', false)
            ->assertDontSee('Required — date not set yet', false)
            ->getContent();

        $this->assertStringContainsString('Save visit', $html);
        $this->assertStringContainsString('Save schedule', $html);
        $this->assertStringContainsString('On site visit before quoting', $html);
        $this->assertStringContainsString('Project start and end as shown on the Project Calendar', $html);

        $pendingVisit = ClientProject::factory()->create([
            'visit_required' => true,
            'visit_date' => null,
            'start_date' => null,
            'end_date' => null,
        ]);
        $pendingHtml = $this->get(route('admin.client-projects.show', $pendingVisit))
            ->assertOk()
            ->assertSee('Required, date not set yet', false)
            ->assertDontSee('Required — date not set yet', false)
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/data-cp-overview-schedule-value>\s*Not set/',
            $pendingHtml
        );

        $css = (string) file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertMatchesRegularExpression(
            '/\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*1fr/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*1024px\)\s*\{[^}]*\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s',
            $css
        );
    }

    public function test_overview_inline_visit_save_updates_date_and_time(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Deck build',
            'client_name' => 'Alex Rivera',
            'visit_required' => true,
            'visit_date' => '2026-11-10',
            'visit_time' => '09:00:00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Deck build',
            'client_name' => 'Alex Rivera',
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-18',
            'visit_time' => '14:30',
        ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame('2026-11-18', $project->visit_date->toDateString());
        $this->assertStringStartsWith('14:30', (string) $project->visit_time);
        $this->assertTrue((bool) $project->visit_required);
        $this->assertSame('2026-12-01', $project->start_date->toDateString());
        $this->assertSame('2026-12-05', $project->end_date->toDateString());

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('data-cp-overview-visit-value', false)
            ->assertSee('18/11/2026', false)
            ->assertSee('2:30 PM EST', false);
    }

    public function test_overview_inline_visit_save_promotes_visit_required_status(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Bath remodel',
            'client_name' => 'Sam Patel',
            'visit_required' => true,
            'visit_date' => null,
            'visit_time' => null,
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Bath remodel',
            'client_name' => 'Sam Patel',
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-19',
            'visit_time' => '11:00',
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_SCHEDULED, $project->status);
        $this->assertTrue(
            $project->statusHistory()->where('to_status', ClientProject::STATUS_VISIT_SCHEDULED)->exists()
        );
    }

    public function test_overview_inline_schedule_save_updates_start_and_end(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Fence install',
            'client_name' => 'Riley Ng',
            'visit_required' => true,
            'visit_date' => '2026-11-08',
            'visit_time' => '09:15:00',
            'start_date' => null,
            'end_date' => null,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Fence install',
            'client_name' => 'Riley Ng',
            'return_tab' => 'overview',
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-26',
        ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame('2026-11-24', $project->start_date->toDateString());
        $this->assertSame('2026-11-26', $project->end_date->toDateString());
        $this->assertSame('2026-11-08', $project->visit_date->toDateString());
        $this->assertStringStartsWith('09:15', (string) $project->visit_time);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('24/11/2026', false)
            ->assertSee('26/11/2026', false);
    }

    public function test_overview_inline_schedule_rejects_end_before_start(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Patio',
            'client_name' => 'Morgan Lee',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
        ]);

        $response = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Patio',
                'client_name' => 'Morgan Lee',
                'return_tab' => 'overview',
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHasErrors('end_date');

        $project->refresh();
        $this->assertSame('2026-11-01', $project->start_date->toDateString());
        $this->assertSame('2026-11-03', $project->end_date->toDateString());
    }

    public function test_overview_inline_visit_rejects_invalid_date(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Siding',
            'client_name' => 'Chris Wu',
            'visit_required' => true,
            'visit_date' => '2026-11-05',
            'visit_time' => '08:00:00',
        ]);

        $response = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Siding',
                'client_name' => 'Chris Wu',
                'return_tab' => 'overview',
                'visit_required' => '1',
                'visit_date' => 'not-a-date',
                'visit_time' => '10:00',
            ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHasErrors('visit_date');

        $project->refresh();
        $this->assertSame('2026-11-05', $project->visit_date->toDateString());
        $this->assertStringStartsWith('08:00', (string) $project->visit_time);
    }

    public function test_guest_cannot_save_overview_inline_visit_or_schedule(): void
    {
        $project = ClientProject::factory()->create([
            'visit_required' => true,
            'visit_date' => '2026-11-05',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-30',
            'visit_time' => '15:00',
        ])->assertRedirect(route('login'));

        $this->put(route('admin.client-projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'return_tab' => 'overview',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
        ])->assertRedirect(route('login'));

        $project->refresh();
        $this->assertSame('2026-11-05', $project->visit_date->toDateString());
        $this->assertSame('2026-11-20', $project->start_date->toDateString());
        $this->assertSame('2026-11-21', $project->end_date->toDateString());
    }

    public function test_edit_details_and_calendar_tab_behavior_is_unchanged(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Windows',
            'client_name' => 'Taylor Brooks',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
        ]);

        $this->get(route('admin.client-projects.edit', $project))
            ->assertOk()
            ->assertSee('id="cp-edit-project-form"', false)
            ->assertSee('id="cp-edit-visit-date"', false)
            ->assertSee('id="cp-start-date"', false)
            ->assertDontSee('cp-overview-grid', false)
            ->assertDontSee('cp-overview-visit-form', false);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('id="cp-schedule-dates-form"', false)
            ->assertSee('id="cp-visit-date"', false)
            ->assertSee('id="cp-start"', false);

        $this->from(route('admin.client-projects.edit', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => '',
                'client_name' => 'Taylor Brooks',
                'billing_type' => ClientProject::BILLING_FIXED,
            ])
            ->assertRedirect(route('admin.client-projects.edit', $project));

        $calendarFail = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Windows',
                'client_name' => 'Taylor Brooks',
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ]);
        $calendarFail->assertSessionHasErrors('end_date');
        $this->assertStringContainsString('#schedule', (string) $calendarFail->headers->get('Location'));
        $this->assertStringNotContainsString('#overview', (string) $calendarFail->headers->get('Location'));
    }
}
