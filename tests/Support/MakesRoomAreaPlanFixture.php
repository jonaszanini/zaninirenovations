<?php

namespace Tests\Support;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;

trait MakesRoomAreaPlanFixture
{
    /**
     * @return array{client: ClientProject, project: RoomAreaProject, kitchen: RoomAreaRoom, bath: RoomAreaRoom, phase: RoomAreaPhase}
     */
    protected function makePlanFixture(array $projectOverrides = []): array
    {
        $client = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $project = RoomAreaProject::create(array_merge([
            'name' => 'Maple kitchen',
            'client_project_id' => $client->id,
            'scale_pixels_per_foot' => 12,
        ], $projectOverrides));
        $phase = RoomAreaPhase::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rough-in',
            'sort_order' => 1,
        ]);
        $kitchen = $this->makePlanRoom($project, 'Kitchen', 144, 120, $phase->id, [
            'id' => 'obj_1',
            'type' => 'door',
            'subtype' => 'single',
            'wall_id' => 'wall_p1_0_1',
            'width' => 36,
            'offset' => 12,
        ]);
        $bath = $this->makePlanRoom($project, 'Bath', 96, 96);

        return [
            'client' => $client,
            'project' => $project->fresh(['phases', 'rooms', 'clientProject']),
            'kitchen' => $kitchen,
            'bath' => $bath,
            'phase' => $phase,
        ];
    }

    protected function makePlanRoom(
        RoomAreaProject $project,
        string $name,
        float $widthPx,
        float $heightPx,
        ?int $phaseId = null,
        ?array $door = null
    ): RoomAreaRoom {
        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'phase_id' => $phaseId,
            'name' => $name,
            'scale_pixels_per_foot' => 12,
            'area_sqft' => ($widthPx / 12) * ($heightPx / 12),
            'wall_height_ft' => 8,
            'flooring_waste_percent' => 10,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $widthPx, 'y' => 0],
                            ['x' => $widthPx, 'y' => $heightPx],
                            ['x' => 0, 'y' => $heightPx],
                        ],
                    ]],
                ]],
                'objects' => $door ? [$door] : [],
            ],
        ]);
    }

    protected function makePublishedQuote(ClientProject $client, array $overrides = []): StandardQuotation
    {
        return StandardQuotation::create(array_merge([
            'client_project_id' => $client->id,
            'quote_number' => 'Q-RA-SIGN-'.uniqid(),
            'client_name' => $client->client_name ?: 'Pat Owner',
            'display_title' => 'Kitchen — Room Area',
            'is_draft' => false,
            'version' => 1,
            'grand_total' => 515.28,
            'materials_subtotal_before_hst' => 456.00,
            'materials' => [['description' => 'LVP', 'quantity' => 88, 'unit_price' => 4.5]],
            'works' => [['name' => 'LVP', 'qty' => 88, 'unit_price' => 4.5]],
        ], $overrides));
    }

    protected function makeDraftQuote(ClientProject $client, array $overrides = []): StandardQuotation
    {
        return $this->makePublishedQuote($client, array_merge([
            'quote_number' => 'Q-RA-DRAFT-'.uniqid(),
            'is_draft' => true,
        ], $overrides));
    }

    protected function makeRevisingQuote(ClientProject $client): StandardQuotation
    {
        $quote = $this->makePublishedQuote($client, [
            'quote_number' => 'Q-RA-REV-'.uniqid(),
            'is_draft' => true,
            'version' => 2,
        ]);
        ClientProjectQuotation::query()->create([
            'client_project_id' => $client->id,
            'title' => 'Standard quotation',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-RA-'.uniqid(),
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_id' => $quote->id,
            'source_snapshot' => ['quote_number' => $quote->quote_number],
            'items' => [
                ['description' => 'Labour and materials', 'amount' => 456, 'apply_hst' => true],
            ],
        ]);

        return $quote->fresh();
    }
}
