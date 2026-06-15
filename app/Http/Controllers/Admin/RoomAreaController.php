<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\PolygonAreaService;
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
            ->orderBy('name')
            ->get(['id', 'name']);

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

        $room_area_project->delete();

        return response()->json(['ok' => true]);
    }

    public function storeRoom(Request $request, RoomAreaProject $room_area_project, PolygonAreaService $areaService)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'vertices' => ['required', 'array', 'min:3'],
            'vertices.*.x' => ['required', 'numeric'],
            'vertices.*.y' => ['required', 'numeric'],
        ]);

        $scale = (float) ($room_area_project->scale_pixels_per_foot ?? 0);
        $validation = $areaService->validateRoom($data['vertices'], $scale);

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        $room = $room_area_project->rooms()->create([
            'name' => $data['name'],
            'vertices' => $data['vertices'],
            'area_sqft' => $validation['area_sqft'],
        ]);

        return response()->json([
            'ok' => true,
            'room' => $this->roomPayload($room),
        ]);
    }

    public function destroyRoom(RoomAreaRoom $room_area_room)
    {
        $room_area_room->delete();

        return response()->json(['ok' => true]);
    }

    public function validateRoom(Request $request, PolygonAreaService $areaService)
    {
        $data = $request->validate([
            'vertices' => ['required', 'array', 'min:3'],
            'vertices.*.x' => ['required', 'numeric'],
            'vertices.*.y' => ['required', 'numeric'],
            'scale_pixels_per_foot' => ['required', 'numeric', 'gt:0'],
        ]);

        $validation = $areaService->validateRoom(
            $data['vertices'],
            (float) $data['scale_pixels_per_foot']
        );

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        return response()->json([
            'ok' => true,
            'area_sqft' => $validation['area_sqft'],
            'area_sqm' => round($validation['area_sqft'] * 0.092903, 2),
        ]);
    }

    public function uploadBackground(Request $request, RoomAreaProject $room_area_project)
    {
        $data = $request->validate([
            'background' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        if ($room_area_project->background_path) {
            Storage::disk('local')->delete($room_area_project->background_path);
        }

        $path = $data['background']->store('room-plans', 'local');
        $room_area_project->background_path = $path;
        $room_area_project->save();

        return response()->json([
            'ok' => true,
            'background_url' => route('admin.utilities.room-area.background', $room_area_project),
        ]);
    }

    public function background(RoomAreaProject $room_area_project): Response
    {
        if (! $room_area_project->background_path || ! Storage::disk('local')->exists($room_area_project->background_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($room_area_project->background_path);
    }

    public function destroyBackground(RoomAreaProject $room_area_project)
    {
        if ($room_area_project->background_path) {
            Storage::disk('local')->delete($room_area_project->background_path);
            $room_area_project->background_path = null;
            $room_area_project->save();
        }

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
            'background_url' => $project->background_path
                ? route('admin.utilities.room-area.background', $project)
                : null,
            'rooms' => $project->rooms->map(fn (RoomAreaRoom $room) => $this->roomPayload($room))->values(),
        ];
    }

    private function roomPayload(RoomAreaRoom $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'vertices' => $room->vertices,
            'area_sqft' => (float) $room->area_sqft,
        ];
    }
}
