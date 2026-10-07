<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaLayoutQuoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function searchLayouts(Request $request, RoomAreaLayoutQuoteService $layouts)
    {
        $id = trim((string) $request->get('id', ''));
        $q = trim((string) $request->get('q', ''));
        $clientProjectId = $request->integer('client_project_id');

        return response()->json($layouts->search(
            $q,
            $clientProjectId > 0 ? $clientProjectId : null,
            $id !== '' ? $id : null,
        ));
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
            $this->deleteRoomPdfFile($room);
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
            'canvas.objects' => ['nullable', 'array'],
            'canvas.objects.*.id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.type' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.subtype' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.wall_id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.width' => ['nullable', 'numeric'],
            'canvas.objects.*.offset' => ['nullable', 'numeric'],
            'canvas.objects.*.swing' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.hinge' => ['nullable', 'string', 'max:20'],
            'canvas.objects.*.height' => ['nullable', 'numeric'],
            'canvas.objects.*.sill_height' => ['nullable', 'numeric'],
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
            'wall_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
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
            'wall_height_ft' => $canvasData->normalizeWallHeight($data['wall_height_ft'] ?? null),
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
            'canvas.objects' => ['nullable', 'array'],
            'canvas.objects.*.id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.type' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.subtype' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.wall_id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.width' => ['nullable', 'numeric'],
            'canvas.objects.*.offset' => ['nullable', 'numeric'],
            'canvas.objects.*.swing' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.hinge' => ['nullable', 'string', 'max:20'],
            'canvas.objects.*.height' => ['nullable', 'numeric'],
            'canvas.objects.*.sill_height' => ['nullable', 'numeric'],
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
            'wall_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
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
            'wall_height_ft' => array_key_exists('wall_height_ft', $data)
                ? $canvasData->normalizeWallHeight($data['wall_height_ft'])
                : $room_area_room->wall_height_ft,
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
        $this->deleteRoomPdfFile($room_area_room);
        $room_area_room->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Stream a room-plan PDF. When attach=1 and a saved room is posted, also
     * write the file under storage/app/room-plans and keep the path on the room
     * so the linked client project can open it.
     */
    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.label' => ['required', 'string', 'max:120'],
            'rows.*.value' => ['required', 'string', 'max:120'],
            'objects' => ['nullable', 'array'],
            'objects.*.type' => ['nullable', 'string', 'max:40'],
            'objects.*.subtype' => ['nullable', 'string', 'max:40'],
            'objects.*.width' => ['nullable', 'numeric'],
            'objects.*.offset' => ['nullable', 'numeric'],
            'objects.*.wall_id' => ['nullable', 'string', 'max:80'],
            'diagram' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer', 'exists:room_area_rooms,id'],
            'attach' => ['nullable'],
            'wall_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
        ]);

        $diagram = '';
        if (is_string($data['diagram'] ?? null) && str_starts_with($data['diagram'], 'data:image/png;base64,')) {
            $diagram = $data['diagram'];
        }

        $room = null;
        if (! empty($data['room_id'])) {
            $room = RoomAreaRoom::query()->with('project.clientProject')->find($data['room_id']);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $room?->name ? $room->name.' — Room plan' : 'Room plan';
        }

        $objects = [];
        foreach ($data['objects'] ?? [] as $object) {
            if (! is_array($object)) {
                continue;
            }
            $type = strtolower(trim((string) ($object['type'] ?? '')));
            if (! in_array($type, RoomAreaCanvasData::OBJECT_TYPES, true)) {
                continue;
            }
            $objects[] = [
                'type' => $type,
                'subtype' => trim((string) ($object['subtype'] ?? '')),
                'width' => isset($object['width']) ? (float) $object['width'] : null,
                'offset' => isset($object['offset']) ? (float) $object['offset'] : null,
                'wall_id' => trim((string) ($object['wall_id'] ?? '')),
            ];
        }

        $pdf = Pdf::loadView('admin.utilities.room_area_pdf', [
            'title' => $title,
            'summary' => $data['summary'] ?? '',
            'rows' => $data['rows'],
            'objects' => $objects,
            'diagram' => $diagram,
            'generatedAt' => now()->format('M j, Y g:i A'),
            'clientProjectName' => $room?->project?->clientProject?->name,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = $this->pdfFilename($title);

        if ($request->boolean('attach') && $room) {
            $this->persistRoomPdf($room, $pdf->output());
        }

        return $pdf->stream($filename);
    }

    public function downloadPdf(RoomAreaRoom $room_area_room): Response
    {
        if (! $room_area_room->pdf_path || ! Storage::disk('local')->exists($room_area_room->pdf_path)) {
            abort(404);
        }

        $filename = $this->pdfFilename($room_area_room->name.' — Room plan');

        return Storage::disk('local')->response($room_area_room->pdf_path, $filename);
    }

    public function validateRoom(Request $request, RoomAreaCanvasData $canvasData)
    {
        $data = $request->validate([
            'canvas' => ['nullable', 'array'],
            'canvas.v' => ['nullable', 'integer'],
            'canvas.groups' => ['nullable', 'array'],
            'canvas.objects' => ['nullable', 'array'],
            'canvas.objects.*.id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.type' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.subtype' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.wall_id' => ['nullable', 'string', 'max:80'],
            'canvas.objects.*.width' => ['nullable', 'numeric'],
            'canvas.objects.*.offset' => ['nullable', 'numeric'],
            'canvas.objects.*.swing' => ['nullable', 'string', 'max:40'],
            'canvas.objects.*.hinge' => ['nullable', 'string', 'max:20'],
            'canvas.objects.*.height' => ['nullable', 'numeric'],
            'canvas.objects.*.sill_height' => ['nullable', 'numeric'],
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
        $metrics = $scale > 0
            ? $canvasData->layoutMetrics($canvas, $scale, $room->resolvedWallHeightFt())
            : $canvasData->emptyLayoutMetrics($room->resolvedWallHeightFt());

        return [
            'id' => $room->id,
            'name' => $room->name,
            'vertices' => $room->vertices,
            'canvas' => $canvas,
            'group_summaries' => $groupSummaries,
            'metrics' => $metrics,
            'canvas_width' => $room->canvas_width,
            'canvas_height' => $room->canvas_height,
            'scale_pixels_per_foot' => $room->scale_pixels_per_foot,
            'wall_height_ft' => $room->resolvedWallHeightFt(),
            'background_url' => $room->background_path
                ? route('admin.utilities.room-area.rooms.background', $room)
                : null,
            'pdf_url' => $room->pdf_path
                ? route('admin.utilities.room-area.rooms.pdf', $room)
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

    private function deleteRoomPdfFile(RoomAreaRoom $room): void
    {
        if (! $room->pdf_path) {
            return;
        }

        Storage::disk('local')->delete($room->pdf_path);
        $room->pdf_path = null;
    }

    private function persistRoomPdf(RoomAreaRoom $room, string $binary): void
    {
        $path = 'room-plans/room-'.$room->id.'-plan.pdf';
        if ($room->pdf_path && $room->pdf_path !== $path) {
            Storage::disk('local')->delete($room->pdf_path);
        }
        Storage::disk('local')->put($path, $binary);
        $room->pdf_path = $path;
        $room->save();
    }

    private function pdfFilename(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'room-plan';
        }

        return $slug.'.pdf';
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
