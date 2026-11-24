<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectStatusNoteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_show_page_includes_status_note_field(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
            'status_note' => 'Waiting on Saturday visit',
        ]);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('Quick note for this status')
            ->assertSee('Waiting on Saturday visit');
    }

    public function test_saving_a_note_does_not_change_status(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.update', $project), [
                'status' => ClientProject::STATUS_VISIT_REQUIRED,
                'note' => 'Client prefers evenings',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Status note saved.');

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_REQUIRED, $project->status);
        $this->assertSame('Client prefers evenings', $project->status_note);
        $this->assertDatabaseHas('client_project_status_history', [
            'client_project_id' => $project->id,
            'from_status' => ClientProject::STATUS_VISIT_REQUIRED,
            'to_status' => ClientProject::STATUS_VISIT_REQUIRED,
            'note' => 'Client prefers evenings',
        ]);
    }

    public function test_changing_status_keeps_the_typed_note_on_the_new_status(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
            'status_note' => 'Old note',
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.update', $project), [
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'note' => 'Saturday 10am',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Status updated.');

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_SCHEDULED, $project->status);
        $this->assertSame('Saturday 10am', $project->status_note);
        $this->assertDatabaseHas('client_project_status_history', [
            'client_project_id' => $project->id,
            'from_status' => ClientProject::STATUS_VISIT_REQUIRED,
            'to_status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'note' => 'Saturday 10am',
        ]);
    }

    public function test_changing_status_without_a_new_note_clears_the_previous_note(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
            'status_note' => 'Old note',
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.update', $project), [
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
                'note' => '',
            ])
            ->assertRedirect();

        $this->assertNull($project->fresh()->status_note);
    }

    public function test_automatic_status_change_clears_the_status_note(): void
    {
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
            'status_note' => 'Call before driving out',
        ]);

        $updated = app(ClientProjectStatusService::class)->transition(
            $project,
            ClientProject::STATUS_VISIT_SCHEDULED,
            'Visit date set.'
        );

        $this->assertSame(ClientProject::STATUS_VISIT_SCHEDULED, $updated->status);
        $this->assertNull($updated->status_note);
    }

    public function test_awaiting_client_status_is_available_and_can_be_selected(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('value="awaiting_client"', false)
            ->assertSee('Awaiting Client');

        $this->postJson(route('admin.client-projects.status.update', $project), [
            'status' => ClientProject::STATUS_AWAITING_CLIENT,
            'note' => 'Waiting on client colour choice',
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => ClientProject::STATUS_AWAITING_CLIENT,
                'status_label' => 'Awaiting Client',
                'status_note' => 'Waiting on client colour choice',
            ]);

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_AWAITING_CLIENT, $project->status);
        $this->assertSame('Awaiting Client', $project->statusLabel());
        $this->assertTrue($project->canPromoteToProposalAcceptedFromQuotation());
        $this->assertContains(ClientProject::STATUS_PROPOSAL_ACCEPTED, $project->quickStatusOptions());
    }

    public function test_show_page_renders_click_to_edit_quick_note_empty_state_and_inline_title(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Initial Project Name',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'status_note' => null,
        ]);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('data-cp-inline-title', false)
            ->assertSee('data-cp-project-name-mirror', false)
            ->assertSee('data-cp-show-inline-note', false)
            ->assertSee('No notes');
    }

    public function test_inline_title_update_renames_project_and_rejects_blank(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Old Title',
            'client_name' => 'Louis Chinnam',
        ]);

        $this->patchJson(route('admin.client-projects.name.update', $project), [
            'name' => '   ',
        ])->assertStatus(422);

        $this->patchJson(route('admin.client-projects.name.update', $project), [
            'name' => 'Updated MDF Sideboard Panels',
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'name' => 'Updated MDF Sideboard Panels',
            ]);

        $project->refresh();
        $this->assertSame('Updated MDF Sideboard Panels', $project->name);
        $this->assertSame('Louis Chinnam', $project->client_name);
    }
}
