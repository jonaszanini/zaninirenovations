<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\PolygonAreaService;
use App\Services\RoomAreaCanvasData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class RoomAreaController extends Controller
{
    public function index()
    {
        $projects = RoomAreaProject::query()
            ->with([
                'rooms',
                'clientProject:id,name',
            ])
            ->orderBy('name')
            ->get();

        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED, ClientProject::STATUS_COMPLETED])
            ->orderBy('client_name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_name']);

        return view('admin.utilities.room_area', [
            'projects' => $projects,
            'clientProjects' => $clientProjects,
            'projectsJson' => $projects->map(fn (RoomAreaProject $project) => $this->projectPayload($project))->values(),
        ]);
    }

    public function storeProject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
        ]);

        $project = RoomAreaProject::create([
            'name' => $data['name'],
            'client_project_id' => $data['client_project_id'] ?? null,
        ]);

        $project->load('clientProject:id,name');

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($project),
        ]);
    }

    public function updateProject(Request $request, RoomAreaProject $room_area_project)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'scale_pixels_per_foot' => ['nullable', 'numeric', 'gt:0'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ]);

        $room_area_project->fill($data);
        $room_area_project->save();
        $room_area_project->load(['clientProject:id,name', 'rooms']);

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($room_area_project),
        ]);
    }

    public function destroyProject(RoomAreaProject $room_area_project)
    {
        if ($room_area_project->background_path) {
            Storage::disk('local')->delete($room_area_project->background_path);
        }

        foreach ($room_area_project->rooms as $room) {
            $this->deleteRoomBackgroundFile($room);
        }

        $room_area_project->delete();

        return response()->json(['ok' => true]);
    }

    public function storeRoom(Request $request, RoomAreaProject $room_area_project, RoomAreaCanvasData $canvasData)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'canvas' => ['nullable', 'array'],
            'canvas.v' => ['nullable', 'integer'],
            'canvas.groups' => ['nullable', 'array'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'vertices' => ['nullable', 'array'],
            'vertices.*.x' => ['required_with:vertices', 'numeric'],
            'vertices.*.y' => ['required_with:vertices', 'numeric'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'scale_pixels_per_foot' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $scale = (float) ($data['scale_pixels_per_foot'] ?? $room_area_project->scale_pixels_per_foot ?? 0);
        $rawCanvas = $data['canvas'] ?? $data['vertices'] ?? null;
        $normalized = $canvasData->normalize($rawCanvas);
        $validation = $canvasData->validate($normalized, $scale);

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        $room = $room_area_project->rooms()->create([
            'name' => $data['name'],
            'vertices' => $normalized,
            'canvas_width' => $data['canvas_width'] ?? null,
            'canvas_height' => $data['canvas_height'] ?? null,
            'scale_pixels_per_foot' => $data['scale_pixels_per_foot'] ?? null,
            'area_sqft' => $validation['area_sqft'],
        ]);

        $this->syncProjectCanvasDimensions($room_area_project, $data);

        return response()->json([
            'ok' => true,
            'room' => $this->roomPayload($room, $canvasData),
        ]);
    }

    public function updateRoom(Request $request, RoomAreaRoom $room_area_room, RoomAreaCanvasData $canvasData)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'canvas' => ['nullable', 'array'],
            'canvas.v' => ['nullable', 'integer'],
            'canvas.groups' => ['nullable', 'array'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'vertices' => ['nullable', 'array'],
            'vertices.*.x' => ['required_with:vertices', 'numeric'],
            'vertices.*.y' => ['required_with:vertices', 'numeric'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'scale_pixels_per_foot' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $scale = (float) ($data['scale_pixels_per_foot'] ?? $room_area_room->scale_pixels_per_foot ?? $room_area_room->project->scale_pixels_per_foot ?? 0);
        $rawCanvas = $data['canvas'] ?? $data['vertices'] ?? null;
        $normalized = $canvasData->normalize($rawCanvas);
        $validation = $canvasData->validate($normalized, $scale);

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        $room_area_room->update([
            'name' => $data['name'],
            'vertices' => $normalized,
            'canvas_width' => $data['canvas_width'] ?? null,
            'canvas_height' => $data['canvas_height'] ?? null,
            'scale_pixels_per_foot' => $data['scale_pixels_per_foot'] ?? $room_area_room->scale_pixels_per_foot,
            'area_sqft' => $validation['area_sqft'],
        ]);

        $this->syncProjectCanvasDimensions($room_area_room->project, $data);

        return response()->json([
            'ok' => true,
            'room' => $this->roomPayload($room_area_room->fresh(), $canvasData),
        ]);
    }

    public function destroyRoom(RoomAreaRoom $room_area_room)
    {
        $this->deleteRoomBackgroundFile($room_area_room);
        $room_area_room->delete();

        return response()->json(['ok' => true]);
    }

    public function validateRoom(Request $request, RoomAreaCanvasData $canvasData)
    {
        $data = $request->validate([
            'canvas' => ['nullable', 'array'],
            'canvas.v' => ['nullable', 'integer'],
            'canvas.groups' => ['nullable', 'array'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'vertices' => ['nullable', 'array'],
            'vertices.*.x' => ['required_with:vertices', 'numeric'],
            'vertices.*.y' => ['required_with:vertices', 'numeric'],
            'scale_pixels_per_foot' => ['required', 'numeric', 'gt:0'],
        ]);

        $rawCanvas = $data['canvas'] ?? $data['vertices'] ?? null;
        $normalized = $canvasData->normalize($rawCanvas);
        $validation = $canvasData->validate($normalized, (float) $data['scale_pixels_per_foot']);

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        return response()->json([
            'ok' => true,
            'area_sqft' => $validation['area_sqft'],
            'area_sqm' => round($validation['area_sqft'] * 0.092903, 2),
            'group_summaries' => $validation['group_summaries'],
        ]);
    }

    public function uploadBackground(Request $request, RoomAreaRoom $room_area_room)
    {
        $data = $request->validate([
            'background' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $this->deleteRoomBackgroundFile($room_area_room);

        $path = $data['background']->store('room-plans', 'local');
        $room_area_room->background_path = $path;
        $room_area_room->save();

        return response()->json([
            'ok' => true,
            'background_url' => route('admin.utilities.room-area.rooms.background', $room_area_room),
        ]);
    }

    public function background(RoomAreaRoom $room_area_room): Response
    {
        if (! $room_area_room->background_path || ! Storage::disk('local')->exists($room_area_room->background_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($room_area_room->background_path);
    }

    public function destroyBackground(RoomAreaRoom $room_area_room)
    {
        $this->deleteRoomBackgroundFile($room_area_room);
        $room_area_room->save();

        return response()->json(['ok' => true]);
    }

    private function projectPayload(RoomAreaProject $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'client_project_id' => $project->client_project_id,
            'client_project_name' => $project->clientProject?->name,
            'scale_pixels_per_foot' => $project->scale_pixels_per_foot,
            'canvas_width' => $project->canvas_width,
            'canvas_height' => $project->canvas_height,
            'rooms' => $project->rooms->map(fn (RoomAreaRoom $room) => $this->roomPayload($room))->values(),
        ];
    }

    private function roomPayload(RoomAreaRoom $room, ?RoomAreaCanvasData $canvasData = null): array
    {
        $canvasData ??= app(RoomAreaCanvasData::class);
        $canvas = $canvasData->normalize($room->vertices);
        $scale = (float) ($room->scale_pixels_per_foot ?? $room->project?->scale_pixels_per_foot ?? 0);
        $groupSummaries = $scale > 0
            ? $canvasData->groupSummaries($canvas, $scale)
            : [];

        return [
            'id' => $room->id,
            'name' => $room->name,
            'vertices' => $room->vertices,
            'canvas' => $canvas,
            'group_summaries' => $groupSummaries,
            'canvas_width' => $room->canvas_width,
            'canvas_height' => $room->canvas_height,
            'scale_pixels_per_foot' => $room->scale_pixels_per_foot,
            'background_url' => $room->background_path
                ? route('admin.utilities.room-area.rooms.background', $room)
                : null,
            'area_sqft' => (float) $room->area_sqft,
        ];
    }

    private function deleteRoomBackgroundFile(RoomAreaRoom $room): void
    {
        if (! $room->background_path) {
            return;
        }

        Storage::disk('local')->delete($room->background_path);
        $room->background_path = null;
    }

    private function syncProjectCanvasDimensions(RoomAreaProject $project, array $data): void
    {
        if (empty($data['canvas_width']) || empty($data['canvas_height'])) {
            return;
        }

        $project->update([
            'canvas_width' => $data['canvas_width'],
            'canvas_height' => $data['canvas_height'],
        ]);
    }
}
