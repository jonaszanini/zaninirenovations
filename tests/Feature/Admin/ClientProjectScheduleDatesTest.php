<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectScheduleDatesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_use_these_dates_saves_only_the_chosen_range(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Barn studs',
            'notes' => 'Leave this note alone',
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
            'start_date' => null,
            'end_date' => null,
        ]);

        $response = $this->post(route('admin.client-projects.schedule-dates.update', $project), [
            '_method' => 'PUT',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-22',
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('#schedule', $location);
        $response->assertSessionHas('success', 'Start and end dates saved.');

        $project->refresh();
        $this->assertSame('2026-10-20', $project->start_date->toDateString());
        $this->assertSame('2026-10-22', $project->end_date->toDateString());
        $this->assertSame('Barn studs', $project->name);
        $this->assertSame('Leave this note alone', $project->notes);
        $this->assertSame(ClientProject::STATUS_PROPOSAL_ACCEPTED, $project->status);
    }

    public function test_same_day_start_and_end_is_saved(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->put(route('admin.client-projects.schedule-dates.update', $project), [
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
        ])->assertSessionHas('success', 'Start and end dates saved.');

        $project->refresh();
        $this->assertSame('2026-10-20', $project->start_date->toDateString());
        $this->assertSame('2026-10-20', $project->end_date->toDateString());
    }

    public function test_end_before_start_is_rejected(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
        ]);

        $response = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.schedule-dates.update', $project), [
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-19',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('#schedule', (string) $response->headers->get('Location'));
        $response->assertSessionHasErrors('end_date');

        $project->refresh();
        $this->assertSame('2026-10-01', $project->start_date->toDateString());
        $this->assertSame('2026-10-02', $project->end_date->toDateString());
    }

    public function test_project_page_wires_the_picker_button_to_save(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('data-save-url="'.route('admin.client-projects.schedule-dates.update', $project).'"', false)
            ->assertSee('Use these dates', false);
    }
}
