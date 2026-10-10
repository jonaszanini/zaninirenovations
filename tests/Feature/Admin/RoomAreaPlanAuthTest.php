<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\MakesRoomAreaPlanFixture;

class RoomAreaPlanAuthTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesRoomAreaPlanFixture;

    public function test_guest_is_redirected_from_plan_export_phase_and_signature_routes(): void
    {
        $fixture = $this->makePlanFixture();
        $project = $fixture['project'];
        $phase = $fixture['phase'];
        $room = $fixture['bath'];

        $this->get(route('admin.utilities.room-area.projects.plan', $project))->assertRedirect();
        $this->get(route('admin.utilities.room-area.projects.export.csv', $project))->assertRedirect();
        $this->get(route('admin.utilities.room-area.projects.export.xlsx', $project))->assertRedirect();
        $this->post(route('admin.utilities.room-area.projects.phases.store', $project), [
            'name' => 'Finish',
        ])->assertRedirect();
        $this->put(route('admin.utilities.room-area.phases.update', $phase), [
            'name' => 'Rough',
        ])->assertRedirect();
        $this->delete(route('admin.utilities.room-area.phases.destroy', $phase))->assertRedirect();
        $this->patch(route('admin.utilities.room-area.rooms.phase', $room), [
            'phase_id' => $phase->id,
        ])->assertRedirect();
        $this->post(route('admin.utilities.room-area.projects.signature', $project), [
            'signer_name' => 'Pat',
            'standard_quotation_id' => 1,
            'quote_version' => 1,
            'signature' => 'x',
        ])->assertRedirect();
        $this->post(route('admin.utilities.room-area.projects.signature.void', $project), [
            'reason' => 'No',
        ])->assertRedirect();
    }

    public function test_missing_project_returns_404_for_pdf_csv_excel_and_signature(): void
    {
        $this->actingAsAdmin();
        $missing = 999999;

        $this->get('/admin/utilities/room-area/projects/'.$missing.'/plan.pdf')->assertNotFound();
        $this->get('/admin/utilities/room-area/projects/'.$missing.'/measurements.csv')->assertNotFound();
        $this->get('/admin/utilities/room-area/projects/'.$missing.'/measurements.xlsx')->assertNotFound();
        $this->postJson('/admin/utilities/room-area/projects/'.$missing.'/phases', ['name' => 'A'])->assertNotFound();
        $this->postJson('/admin/utilities/room-area/projects/'.$missing.'/signature', [
            'signer_name' => 'Pat',
            'standard_quotation_id' => 1,
            'quote_version' => 1,
            'signature' => 'x',
        ])->assertNotFound();
        $this->postJson('/admin/utilities/room-area/projects/'.$missing.'/signature/void', [
            'reason' => 'No',
        ])->assertNotFound();
    }

    public function test_mismatched_phase_or_missing_phase_returns_404(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $other = RoomAreaProject::create([
            'name' => 'Other project',
            'scale_pixels_per_foot' => 12,
        ]);
        $foreignPhase = RoomAreaPhase::create([
            'room_area_project_id' => $other->id,
            'name' => 'Foreign',
            'sort_order' => 1,
        ]);

        $this->patchJson(route('admin.utilities.room-area.rooms.phase', $fixture['bath']), [
            'phase_id' => $foreignPhase->id,
        ])->assertNotFound();

        $this->putJson('/admin/utilities/room-area/phases/999999', ['name' => 'Nope'])->assertNotFound();
        $this->deleteJson('/admin/utilities/room-area/phases/999999')->assertNotFound();
        $this->patchJson('/admin/utilities/room-area/rooms/999999/phase', [
            'phase_id' => $fixture['phase']->id,
        ])->assertNotFound();
    }
}
