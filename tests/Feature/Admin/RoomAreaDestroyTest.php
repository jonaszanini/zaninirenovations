<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\RoomAreaController;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaPhotoStorage;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class RoomAreaDestroyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_destroy_room_returns_ok_when_layers_unlocked(): void
    {
        $vertices = ['editor' => ['layers_locked' => false]];
        $canvas = Mockery::mock(RoomAreaCanvasData::class);
        $canvas->shouldReceive('isLayersLocked')->once()->with($vertices)->andReturn(false);
        $this->app->instance(RoomAreaCanvasData::class, $canvas);

        $room = new class(['vertices' => $vertices, 'id' => 1]) extends RoomAreaRoom
        {
            public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
            {
                $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
                $relation->shouldReceive('delete')->once()->andReturn(0);

                return $relation;
            }
        };
        $room = Mockery::mock($room)->makePartial();
        $room->shouldReceive('delete')->once()->andReturnTrue();

        $photos = Mockery::mock(RoomAreaPhotoStorage::class);
        $photos->shouldReceive('deleteDirectory')->once()->with(1);
        $this->app->instance(RoomAreaPhotoStorage::class, $photos);

        $response = app(RoomAreaController::class)->destroyRoom($room);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }

    public function test_destroy_room_returns_422_when_layers_locked(): void
    {
        $vertices = ['editor' => ['layers_locked' => true]];
        $canvas = Mockery::mock(RoomAreaCanvasData::class);
        $canvas->shouldReceive('isLayersLocked')->once()->with($vertices)->andReturn(true);
        $this->app->instance(RoomAreaCanvasData::class, $canvas);

        $room = Mockery::mock(RoomAreaRoom::class)->makePartial();
        $room->shouldReceive('getAttribute')->with('vertices')->andReturn($vertices);
        $room->shouldNotReceive('delete');

        $response = app(RoomAreaController::class)->destroyRoom($room);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'ok' => false,
            'message' => 'Unlock layers to delete this room.',
        ], $response->getData(true));
    }

    public function test_destroy_project_returns_ok_when_all_rooms_unlocked(): void
    {
        $canvas = Mockery::mock(RoomAreaCanvasData::class);
        $canvas->shouldReceive('isLayersLocked')->once()->andReturn(false);
        $this->app->instance(RoomAreaCanvasData::class, $canvas);

        $innerRoom = new class(['vertices' => ['editor' => ['layers_locked' => false]], 'id' => 2]) extends RoomAreaRoom
        {
            public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
            {
                $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
                $relation->shouldReceive('delete')->once()->andReturn(0);

                return $relation;
            }
        };

        $project = Mockery::mock(RoomAreaProject::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('rooms')->andReturn(new Collection([$innerRoom]));
        $project->background_path = null;
        $project->shouldReceive('delete')->once()->andReturnTrue();

        $response = app(RoomAreaController::class)->destroyProject($project);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
    }

    public function test_destroy_project_returns_422_when_any_room_layers_locked(): void
    {
        $canvas = Mockery::mock(RoomAreaCanvasData::class);
        $canvas->shouldReceive('isLayersLocked')->once()->andReturn(true);
        $this->app->instance(RoomAreaCanvasData::class, $canvas);

        $room = Mockery::mock(RoomAreaRoom::class)->makePartial();
        $room->shouldReceive('getAttribute')->with('vertices')->andReturn(['editor' => ['layers_locked' => true]]);

        $project = Mockery::mock(RoomAreaProject::class)->makePartial();
        $project->shouldReceive('getAttribute')->with('rooms')->andReturn(new Collection([$room]));
        $project->shouldNotReceive('delete');

        $response = app(RoomAreaController::class)->destroyProject($project);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'ok' => false,
            'message' => 'Unlock layers to delete this project.',
        ], $response->getData(true));
    }
}
