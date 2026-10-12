<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuickStatusChangeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_quick_status_change_updates_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.quick', $project), [
                'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            ClientProject::STATUS_VISIT_SCHEDULED,
            $project->fresh()->status
        );
    }

    public function test_quick_status_rejects_invalid_transitions(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.quick', $project), [
                'status' => ClientProject::STATUS_COMPLETED,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            ClientProject::STATUS_VISIT_REQUIRED,
            $project->fresh()->status
        );
    }

    public function test_quick_status_cannot_cancel_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.status.quick', $project), [
                'status' => ClientProject::STATUS_CANCELLED,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            ClientProject::STATUS_QUOTE_SENT,
            $project->fresh()->status
        );
    }

    public function test_quick_status_options_returns_correct_next_statuses(): void
    {
        $project = ClientProject::factory()->make([
            'status' => ClientProject::STATUS_QUOTE_SENT,
        ]);

        $options = $project->quickStatusOptions();

        $this->assertContains(ClientProject::STATUS_PROPOSAL_ACCEPTED, $options);
        $this->assertContains(ClientProject::STATUS_REJECTED, $options);
        $this->assertNotContains(ClientProject::STATUS_COMPLETED, $options);
    }
}
