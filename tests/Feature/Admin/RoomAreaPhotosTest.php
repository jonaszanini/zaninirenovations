<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaPhoto;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\ClientProjectLinkedUtilitiesService;
use App\Services\RoomAreaPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaPhotosTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_upload_or_view_photos(): void
    {
        $room = $this->makeRoom();
        $upload = $this->makePngUpload(80, 60);

        $this->post(route('admin.utilities.room-area.rooms.photos.store', $room), [
            'photos' => [$upload],
        ])->assertRedirect();

        $photo = $this->storePhotoDirect($room);
        $this->get(route('admin.utilities.room-area.rooms.photos.show', [
            'room_area_room' => $room,
            'photo' => $photo,
        ]))->assertRedirect();
    }

    public function test_admin_can_upload_resize_and_delete_photos(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $room = $this->makeRoom();
        $upload = $this->makePngUpload(2000, 1000, 'site-wall.png');

        $response = $this->postJson(route('admin.utilities.room-area.rooms.photos.store', $room), [
            'photos' => [$upload],
            'caption' => 'North wall',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photo_count', 1)
            ->assertJsonPath('photos.0.caption', 'North wall');

        $photo = RoomAreaPhoto::query()->first();
        $this->assertNotNull($photo);
        $this->assertSame('North wall', $photo->caption);
        $this->assertLessThanOrEqual(RoomAreaPhotoStorage::MAX_EDGE_PX, max((int) $photo->width, (int) $photo->height));
        $this->assertSame(1600, (int) $photo->width);
        $this->assertSame(800, (int) $photo->height);
        Storage::disk('local')->assertExists($photo->path);

        $this->get(route('admin.utilities.room-area.rooms.photos.show', [
            'room_area_room' => $room,
            'photo' => $photo,
        ]))->assertOk();

        $this->deleteJson(route('admin.utilities.room-area.rooms.photos.destroy', [
            'room_area_room' => $room,
            'photo' => $photo,
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photo_count', 0);

        $this->assertNull(RoomAreaPhoto::query()->find($photo->id));
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_rejects_non_image_upload(): void
    {
        $this->actingAsAdmin();

        $room = $this->makeRoom();
        $pdf = UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf');

        $this->postJson(route('admin.utilities.room-area.rooms.photos.store', $room), [
            'photos' => [$pdf],
        ])->assertStatus(422);
    }

    public function test_photo_from_another_room_is_not_found(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $roomA = $this->makeRoom();
        $roomB = $this->makeRoom();
        $photo = $this->storePhotoDirect($roomA);

        $this->get(route('admin.utilities.room-area.rooms.photos.show', [
            'room_area_room' => $roomB,
            'photo' => $photo,
        ]))->assertNotFound();

        $this->deleteJson(route('admin.utilities.room-area.rooms.photos.destroy', [
            'room_area_room' => $roomB,
            'photo' => $photo,
        ]))->assertNotFound();
    }

    public function test_pdf_export_includes_photos(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $room = $this->makeRoom();
        $this->postJson(route('admin.utilities.room-area.rooms.photos.store', $room), [
            'photos' => [$this->makePngUpload(120, 80, 'job.png')],
            'caption' => 'West window',
        ])->assertOk();

        $response = $this->post(route('admin.utilities.room-area.pdf'), [
            'title' => 'Office — Room plan',
            'rows' => [
                ['label' => 'Floor area', 'value' => '80 sq ft'],
            ],
            'room_id' => $room->id,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $html = view('admin.utilities.room_area_pdf', [
            'title' => 'Office — Room plan',
            'summary' => '',
            'rows' => [['label' => 'Floor area', 'value' => '80 sq ft']],
            'objects' => [],
            'diagram' => '',
            'photos' => [['src' => 'data:image/jpeg;base64,xxx', 'caption' => 'West window']],
            'generatedAt' => 'Oct 7, 2026 9:00 AM',
            'clientProjectName' => null,
        ])->render();

        $this->assertStringContainsString('Site photos', $html);
        $this->assertStringContainsString('West window', $html);
        $this->assertStringContainsString('pdf-photos', $html);
    }

    public function test_linked_project_utilities_include_photo_count(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $clientProject = ClientProject::factory()->create();
        $room = $this->makeRoom($clientProject);
        $this->postJson(route('admin.utilities.room-area.rooms.photos.store', $room), [
            'photos' => [
                $this->makePngUpload(64, 64, 'a.png'),
                $this->makePngUpload(64, 64, 'b.png'),
            ],
        ])->assertOk();

        $linked = app(ClientProjectLinkedUtilitiesService::class)->forProject($clientProject);
        $this->assertSame(2, $linked['room_area'][0]['rooms'][0]['photo_count']);

        $html = view('admin.client_projects._utilities', [
            'project' => $clientProject,
            'linkedUtilities' => $linked,
        ])->render();
        $this->assertStringContainsString('2 photos', $html);
    }

    private function makeRoom(?ClientProject $clientProject = null): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Photo project',
            'client_project_id' => $clientProject?->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Office',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ]],
                'objects' => [],
            ],
        ]);
    }

    private function makePngUpload(int $width, int $height, string $name = 'site.png'): UploadedFile
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 20, 80, 120));
        $path = tempnam(sys_get_temp_dir(), 'rap').'.png';
        imagepng($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function storePhotoDirect(RoomAreaRoom $room): RoomAreaPhoto
    {
        $path = 'room-photos/'.$room->id.'/seed.webp';
        Storage::disk('local')->put($path, 'fake-bytes');

        return $room->photos()->create([
            'path' => $path,
            'caption' => 'Seed',
            'mime' => 'image/webp',
            'width' => 40,
            'height' => 30,
            'sort_order' => 1,
        ]);
    }
}
