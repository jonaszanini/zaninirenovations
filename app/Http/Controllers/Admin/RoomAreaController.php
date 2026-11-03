<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\RoomAreaPhoto;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaAssemblyDefaults;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaFinishMath;
use App\Services\RoomAreaLayoutQuoteService;
use App\Services\RoomAreaPhotoStorage;
use App\Support\MaterialCatalogPickerOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class RoomAreaController extends Controller
{
    public function resolveEngine(Request $request): string
    {
        return 'openplan3d';
    }

    public function index(Request $request)
    {
        $engine = 'openplan3d';
        $projects = RoomAreaProject::query()
            ->with([
                'rooms.photos',
                'rooms.assemblies',
                'clientProject:id,name',
            ])
            ->orderBy('name')
            ->get();

        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED, ClientProject::STATUS_COMPLETED])
            ->orderBy('client_name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_name']);

        $view = 'admin.utilities.room_area';

        return view($view, array_merge([
            'engine' => $engine,
            'projects' => $projects,
            'clientProjects' => $clientProjects,
            'projectsJson' => $projects->map(fn (RoomAreaProject $project) => $this->projectPayload($project))->values(),
        ], $this->assemblyPageData()));
    }

    public function clientProject(Request $request, ClientProject $client_project, ?RoomAreaRoom $room_area_room = null)
    {
        $engine = 'openplan3d';
        if ($room_area_room) {
            $room_area_room->loadMissing('project');
            if ((int) ($room_area_room->project?->client_project_id) !== (int) $client_project->id) {
                abort(404);
            }
        }

        $projects = RoomAreaProject::query()
            ->where('client_project_id', $client_project->id)
            ->with([
                'rooms.photos',
                'rooms.assemblies',
                'clientProject:id,name',
            ])
            ->orderBy('name')
            ->get();

        if ($projects->isEmpty()) {
            $created = RoomAreaProject::create([
                'name' => $client_project->name ?: 'Room plans',
                'client_project_id' => $client_project->id,
            ]);
            $created->load(['rooms.photos', 'clientProject:id,name']);
            $projects = collect([$created]);
        }

        $clientProjects = ClientProject::query()
            ->whereKey($client_project->id)
            ->get(['id', 'name', 'client_name']);

        $pageTitle = $room_area_room?->name ?: 'Room Area';
        $view = 'admin.client_projects.room_area';

        return view($view, array_merge([
            'engine' => $engine,
            'clientProject' => $client_project,
            'projects' => $projects,
            'clientProjects' => $clientProjects,
            'projectsJson' => $projects->map(fn (RoomAreaProject $project) => $this->projectPayload($project))->values(),
            'pageTitle' => $pageTitle,
            'backUrl' => route('admin.client-projects.show', $client_project).'#utilities',
            'initialRoomId' => $room_area_room?->id,
        ], $this->assemblyPageData()));
    }

    public function guide(Request $request)
    {
        return view('admin.utilities.room_area_guide', [
            'clientProject' => null,
            'room' => null,
            'backUrl' => route('admin.utilities.room-area', $this->standaloneEditorReturnParams($request)),
        ]);
    }

    public function clientProjectGuide(ClientProject $client_project, ?RoomAreaRoom $room_area_room = null)
    {
        if ($room_area_room) {
            $room_area_room->loadMissing('project');
            if ((int) ($room_area_room->project?->client_project_id) !== (int) $client_project->id) {
                abort(404);
            }
        }

        return view('admin.utilities.room_area_guide', [
            'clientProject' => $client_project,
            'room' => $room_area_room,
            'backUrl' => route('admin.client-projects.utilities.room-area', array_filter([
                'client_project' => $client_project,
                'room_area_room' => $room_area_room?->id,
            ])),
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
            'wall_thickness_in' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ]);

        if (array_key_exists('scale_pixels_per_foot', $data)
            && (float) $data['scale_pixels_per_foot'] !== (float) $room_area_project->scale_pixels_per_foot) {
            $room_area_project->loadMissing('rooms');
            foreach ($room_area_project->rooms as $room) {
                if (app(RoomAreaCanvasData::class)->isLayersLocked($room->vertices)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Unlock layers to recalibrate scale.',
                    ], 422);
                }
            }
        }

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
        $canvasData = app(RoomAreaCanvasData::class);
        foreach ($room_area_project->rooms as $room) {
            if ($canvasData->isLayersLocked($room->vertices)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unlock layers to delete this project.',
                ], 422);
            }
        }

        if ($room_area_project->background_path) {
            Storage::disk('local')->delete($room_area_project->background_path);
        }

        foreach ($room_area_project->rooms as $room) {
            $this->deleteRoomBackgroundFile($room);
            $this->deleteRoomPdfFile($room);
            $this->deleteRoomPhotos($room);
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
            'canvas.objects.*.casing_sides' => ['nullable', 'integer', 'min:0', 'max:4'],
            'canvas.objects.*.door_face' => ['nullable', 'string', 'in:interior,exterior'],
            'canvas.objects.*.x' => ['nullable', 'numeric'],
            'canvas.objects.*.y' => ['nullable', 'numeric'],
            'canvas.objects.*.depth' => ['nullable', 'numeric'],
            'canvas.objects.*.rotation' => ['nullable', 'numeric'],
            'canvas.wall_thickness_in' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.editor' => ['nullable', 'array'],
            'canvas.editor.layers_locked' => ['nullable', 'boolean'],
            'canvas.editor.dimensions_mode' => ['nullable', 'string', 'in:all,main,area,off'],
            'canvas.editor.unit_system' => ['nullable', 'string', 'in:imperial,metric'],
            'canvas.editor.measure_unit' => ['nullable', 'string', 'in:meter,inch,feet_decimal,feet_inch_frac'],
            'canvas.editor.angle_snap' => ['nullable', 'boolean'],
            'canvas.editor.template' => ['nullable', 'string', 'in:rect,l,u,t,alcove'],
            'plan' => ['nullable', 'array'],
            'vertices' => ['nullable', 'array'],
            'vertices.*.x' => ['required_with:vertices', 'numeric'],
            'vertices.*.y' => ['required_with:vertices', 'numeric'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'scale_pixels_per_foot' => ['nullable', 'numeric', 'gt:0'],
            'wall_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'ceiling_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'flooring_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flooring_direction' => ['nullable', 'integer', 'in:0,90'],
            'flooring_box_sqft' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);

        $bridge = app(\App\Services\OpenPlan3DMeasurementBridge::class);
        $plan = $data['plan'] ?? null;
        if (is_array($plan)) {
            if (! $bridge->isPlan($plan)) {
                return response()->json(['ok' => false, 'message' => 'The provided plan data is not a valid OpenPlan3D model.'], 422);
            }
            $planCheck = app(\App\Services\OpenPlan3DPlanValidator::class)->validate($plan);
            if (! $planCheck['valid']) {
                return response()->json(['ok' => false, 'message' => $planCheck['message']], 422);
            }
            $finish = [
                'wall_height_ft' => $data['wall_height_ft'] ?? null,
                'ceiling_height_ft' => $data['ceiling_height_ft'] ?? null,
                'flooring_waste_percent' => $data['flooring_waste_percent'] ?? null,
                'flooring_direction' => $data['flooring_direction'] ?? null,
                'flooring_box_sqft' => $data['flooring_box_sqft'] ?? null,
            ];
            $metrics = $this->openPlanLayoutMetricsOr422($bridge, $plan, null, $finish);
            if ($metrics instanceof JsonResponse) {
                return $metrics;
            }
            if (! $this->openPlanHasRoomOrWall($plan, $metrics)) {
                return response()->json(['ok' => false, 'message' => 'The drawing must contain at least one closed room or wall.'], 422);
            }

            $room = $room_area_project->rooms()->create([
                'name' => $data['name'],
                'vertices' => $plan,
                'canvas_width' => $data['canvas_width'] ?? null,
                'canvas_height' => $data['canvas_height'] ?? null,
                'scale_pixels_per_foot' => isset($data['scale_pixels_per_foot'])
                    ? round((float) $data['scale_pixels_per_foot'], 4)
                    : null,
                'area_sqft' => $metrics['area_sqft'],
                'wall_height_ft' => $metrics['wall_height_ft'],
                'ceiling_height_ft' => $metrics['ceiling_height_ft'],
                'flooring_waste_percent' => $metrics['flooring_waste_percent'],
                'flooring_direction' => $metrics['flooring_direction'],
                'flooring_box_sqft' => $metrics['flooring_box_sqft'],
            ]);

            return response()->json([
                'ok' => true,
                'room' => $this->roomPayload($room, $canvasData),
                'project' => $this->projectPayload($room_area_project->fresh(['clientProject', 'rooms.photos', 'rooms.assemblies'])),
            ]);
        }

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
            'scale_pixels_per_foot' => isset($data['scale_pixels_per_foot'])
                ? round((float) $data['scale_pixels_per_foot'], 4)
                : null,
            'area_sqft' => $validation['area_sqft'],
            'wall_height_ft' => $canvasData->normalizeWallHeight($data['wall_height_ft'] ?? null),
        ] + $this->finishAttributes($data, $canvasData));

        $this->syncProjectCanvasDimensions($room_area_project, $data);
        $this->syncProjectWallThickness($room_area_project, $normalized);

        return response()->json([
            'ok' => true,
            'room' => $this->roomPayload($room, $canvasData),
            'project' => $this->projectPayload($room_area_project->fresh(['clientProject', 'rooms.photos', 'rooms.assemblies'])),
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
            'canvas.objects.*.casing_sides' => ['nullable', 'integer', 'min:0', 'max:4'],
            'canvas.objects.*.door_face' => ['nullable', 'string', 'in:interior,exterior'],
            'canvas.objects.*.x' => ['nullable', 'numeric'],
            'canvas.objects.*.y' => ['nullable', 'numeric'],
            'canvas.objects.*.depth' => ['nullable', 'numeric'],
            'canvas.objects.*.rotation' => ['nullable', 'numeric'],
            'canvas.wall_thickness_in' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.editor' => ['nullable', 'array'],
            'canvas.editor.layers_locked' => ['nullable', 'boolean'],
            'canvas.editor.dimensions_mode' => ['nullable', 'string', 'in:all,main,area,off'],
            'canvas.editor.unit_system' => ['nullable', 'string', 'in:imperial,metric'],
            'canvas.editor.measure_unit' => ['nullable', 'string', 'in:meter,inch,feet_decimal,feet_inch_frac'],
            'canvas.editor.angle_snap' => ['nullable', 'boolean'],
            'canvas.editor.template' => ['nullable', 'string', 'in:rect,l,u,t,alcove'],
            'plan' => ['nullable', 'array'],
            'vertices' => ['nullable', 'array'],
            'vertices.*.x' => ['required_with:vertices', 'numeric'],
            'vertices.*.y' => ['required_with:vertices', 'numeric'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'scale_pixels_per_foot' => ['nullable', 'numeric', 'gt:0'],
            'wall_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'ceiling_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'flooring_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flooring_direction' => ['nullable', 'integer', 'in:0,90'],
            'flooring_box_sqft' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'confirm_zero_area' => ['sometimes', 'boolean'],
        ]);

        $bridge = app(\App\Services\OpenPlan3DMeasurementBridge::class);
        $plan = $data['plan'] ?? null;
        if (is_array($plan)) {
            if (! $bridge->isPlan($plan)) {
                return response()->json(['ok' => false, 'message' => 'The provided plan data is not a valid OpenPlan3D model.'], 422);
            }
            $existingPlan = is_array($room_area_room->vertices) ? $room_area_room->vertices : null;
            $planCheck = app(\App\Services\OpenPlan3DPlanValidator::class)->validate($plan, $existingPlan);
            if (! $planCheck['valid']) {
                return response()->json(['ok' => false, 'message' => $planCheck['message']], 422);
            }
            $finish = [
                'wall_height_ft' => $data['wall_height_ft'] ?? $room_area_room->wall_height_ft,
                'ceiling_height_ft' => $data['ceiling_height_ft'] ?? $room_area_room->ceiling_height_ft,
                'flooring_waste_percent' => $data['flooring_waste_percent'] ?? $room_area_room->flooring_waste_percent,
                'flooring_direction' => $data['flooring_direction'] ?? $room_area_room->flooring_direction,
                'flooring_box_sqft' => $data['flooring_box_sqft'] ?? $room_area_room->flooring_box_sqft,
            ];
            $metrics = $this->openPlanLayoutMetricsOr422($bridge, $plan, null, $finish);
            if ($metrics instanceof JsonResponse) {
                return $metrics;
            }
            $existingArea = (float) $room_area_room->area_sqft;
            $incomingArea = (float) $metrics['area_sqft'];
            $confirmedZero = $request->boolean('confirm_zero_area');
            if (! $this->openPlanHasRoomOrWall($plan, $metrics)) {
                return response()->json(['ok' => false, 'message' => 'The drawing must contain at least one closed room or wall.'], 422);
            }
            if ($existingArea > 0 && $incomingArea <= 0 && ! $confirmedZero) {
                return response()->json([
                    'ok' => false,
                    'code' => 'zero_area_overwrite',
                    'message' => 'Refusing to overwrite a room with area '.$existingArea.' sq ft using a 0-area plan. Confirm to proceed.',
                ], 422);
            }

            $legacyBackup = $room_area_room->legacy_canvas_data;
            if ($legacyBackup === null && ! $bridge->isPlan($room_area_room->vertices)) {
                $legacyBackup = $room_area_room->vertices;
            }

            $room_area_room->update([
                'name' => $data['name'],
                'vertices' => $plan,
                'legacy_canvas_data' => $legacyBackup,
                'canvas_width' => $data['canvas_width'] ?? null,
                'canvas_height' => $data['canvas_height'] ?? null,
                'scale_pixels_per_foot' => isset($data['scale_pixels_per_foot'])
                    ? round((float) $data['scale_pixels_per_foot'], 4)
                    : $room_area_room->scale_pixels_per_foot,
                'area_sqft' => $metrics['area_sqft'],
                'wall_height_ft' => $metrics['wall_height_ft'],
                'ceiling_height_ft' => $metrics['ceiling_height_ft'],
                'flooring_waste_percent' => $metrics['flooring_waste_percent'],
                'flooring_direction' => $metrics['flooring_direction'],
                'flooring_box_sqft' => $metrics['flooring_box_sqft'],
            ]);

            return response()->json([
                'ok' => true,
                'room' => $this->roomPayload($room_area_room->fresh(), $canvasData),
                'project' => $this->projectPayload($room_area_room->project->fresh(['clientProject', 'rooms.photos', 'rooms.assemblies'])),
            ]);
        }

        $scale = (float) ($data['scale_pixels_per_foot'] ?? $room_area_room->scale_pixels_per_foot ?? $room_area_room->project->scale_pixels_per_foot ?? 0);
        $rawCanvas = $data['canvas'] ?? $data['vertices'] ?? null;
        $normalized = $canvasData->normalize($rawCanvas);
        $validation = $canvasData->validate($normalized, $scale);

        if (! $validation['valid']) {
            return response()->json(['ok' => false, 'message' => $validation['message']], 422);
        }

        $existingCanvas = is_array($room_area_room->vertices) ? $room_area_room->vertices : null;
        $incomingScale = isset($data['scale_pixels_per_foot']) ? (float) $data['scale_pixels_per_foot'] : (
            $room_area_room->scale_pixels_per_foot !== null ? (float) $room_area_room->scale_pixels_per_foot : null
        );
        $existingScale = $room_area_room->scale_pixels_per_foot !== null
            ? (float) $room_area_room->scale_pixels_per_foot
            : null;
        $normalized = $canvasData->preserveEditorLock($normalized, $existingCanvas, $rawCanvas);
        $normalized = $canvasData->preserveLockedBackground($normalized, $existingCanvas, $existingScale, $incomingScale);
        $lockMessage = $canvasData->lockedGeometryChangeMessage(
            $existingCanvas,
            $normalized,
            $existingScale,
            $incomingScale
        );
        if ($lockMessage !== null) {
            return response()->json(['ok' => false, 'message' => $lockMessage], 422);
        }
        $normalized = $canvasData->restoreStoredGeometryIfWithinTolerance(
            $normalized,
            $existingCanvas,
            $existingScale,
            $incomingScale
        );

        $room_area_room->update([
            'name' => $data['name'],
            'vertices' => $normalized,
            'canvas_width' => $data['canvas_width'] ?? null,
            'canvas_height' => $data['canvas_height'] ?? null,
            'scale_pixels_per_foot' => isset($data['scale_pixels_per_foot'])
                ? round((float) $data['scale_pixels_per_foot'], 4)
                : $room_area_room->scale_pixels_per_foot,
            'area_sqft' => $validation['area_sqft'],
            'wall_height_ft' => array_key_exists('wall_height_ft', $data)
                ? $canvasData->normalizeWallHeight($data['wall_height_ft'])
                : $room_area_room->wall_height_ft,
        ] + $this->finishAttributes($data, $canvasData, $room_area_room));

        $this->syncProjectCanvasDimensions($room_area_room->project, $data);
        $this->syncProjectWallThickness($room_area_room->project, $normalized);

        return response()->json([
            'ok' => true,
            'room' => $this->roomPayload($room_area_room->fresh(), $canvasData),
            'project' => $this->projectPayload($room_area_room->project->fresh(['clientProject', 'rooms.photos', 'rooms.assemblies'])),
        ]);
    }

    public function destroyRoom(RoomAreaRoom $room_area_room)
    {
        if (app(RoomAreaCanvasData::class)->isLayersLocked($room_area_room->vertices)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unlock layers to delete this room.',
            ], 422);
        }

        $this->deleteRoomBackgroundFile($room_area_room);
        $this->deleteRoomPdfFile($room_area_room);
        $this->deleteRoomPhotos($room_area_room);
        $room_area_room->delete();

        return response()->json(['ok' => true]);
    }

    public function storePhotos(Request $request, RoomAreaRoom $room_area_room, RoomAreaPhotoStorage $photos)
    {
        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:'.RoomAreaPhotoStorage::MAX_UPLOAD_KB],
            'caption' => ['nullable', 'string', 'max:160'],
        ]);

        $files = $data['photos'];
        $caption = isset($data['caption']) ? trim((string) $data['caption']) : '';
        $caption = $caption === '' ? null : $caption;
        if (count($files) !== 1) {
            $caption = null;
        }
        $sort = (int) $room_area_room->photos()->max('sort_order');
        $created = [];

        foreach ($files as $file) {
            $stored = $photos->store($file, (int) $room_area_room->id);
            $sort++;
            $photo = $room_area_room->photos()->create([
                'path' => $stored['path'],
                'caption' => $caption,
                'original_filename' => $file->getClientOriginalName(),
                'mime' => $stored['mime'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'sort_order' => $sort,
            ]);
            $created[] = $this->photoPayload($photo);
        }

        return response()->json([
            'ok' => true,
            'photos' => $created,
            'photo_count' => $room_area_room->photos()->count(),
        ]);
    }

    public function showPhoto(RoomAreaRoom $room_area_room, RoomAreaPhoto $photo): Response
    {
        $this->assertPhotoBelongsToRoom($room_area_room, $photo);
        if (! $photo->path || ! Storage::disk('local')->exists($photo->path)) {
            abort(404);
        }

        return Storage::disk('local')->response($photo->path);
    }

    public function updatePhoto(Request $request, RoomAreaRoom $room_area_room, RoomAreaPhoto $photo)
    {
        $this->assertPhotoBelongsToRoom($room_area_room, $photo);
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:160'],
        ]);
        $caption = isset($data['caption']) ? trim((string) $data['caption']) : '';
        $photo->update([
            'caption' => $caption === '' ? null : $caption,
        ]);

        return response()->json([
            'ok' => true,
            'photo' => $this->photoPayload($photo->fresh()),
        ]);
    }

    public function destroyPhoto(RoomAreaRoom $room_area_room, RoomAreaPhoto $photo, RoomAreaPhotoStorage $photos)
    {
        $this->assertPhotoBelongsToRoom($room_area_room, $photo);
        $photos->delete($photo->path);
        $photo->delete();

        return response()->json([
            'ok' => true,
            'photo_count' => $room_area_room->photos()->count(),
        ]);
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
            'ceiling_height_ft' => ['nullable', 'numeric', 'min:1', 'max:20'],
            'flooring_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flooring_direction' => ['nullable', 'integer', 'in:0,90'],
            'flooring_box_sqft' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);

        $diagram = '';
        if (is_string($data['diagram'] ?? null) && str_starts_with($data['diagram'], 'data:image/png;base64,')) {
            $diagram = $data['diagram'];
        }

        $room = null;
        if (! empty($data['room_id'])) {
            $room = RoomAreaRoom::query()->with(['project.clientProject', 'photos'])->find($data['room_id']);
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

        $photoUris = [];
        if ($room) {
            $storage = app(RoomAreaPhotoStorage::class);
            foreach ($room->photos as $photo) {
                $uri = $storage->pdfDataUri((string) $photo->path);
                if ($uri === null) {
                    continue;
                }
                $photoUris[] = [
                    'src' => $uri,
                    'caption' => $photo->caption,
                ];
            }
        }

        $finish = [
            'ceiling_height_ft' => isset($data['ceiling_height_ft']) ? (float) $data['ceiling_height_ft'] : null,
            'flooring_waste_percent' => isset($data['flooring_waste_percent']) ? (float) $data['flooring_waste_percent'] : null,
            'flooring_direction' => isset($data['flooring_direction']) ? (int) $data['flooring_direction'] : null,
            'flooring_box_sqft' => isset($data['flooring_box_sqft']) ? (float) $data['flooring_box_sqft'] : null,
        ];

        $pdf = Pdf::loadView('admin.utilities.room_area_pdf', [
            'title' => $title,
            'summary' => $data['summary'] ?? '',
            'rows' => $data['rows'],
            'objects' => $objects,
            'diagram' => $diagram,
            'photos' => $photoUris,
            'generatedAt' => now()->format('M j, Y g:i A'),
            'clientProjectName' => $room?->project?->clientProject?->name,
            'finish' => $finish,
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
            'canvas.objects.*.casing_sides' => ['nullable', 'integer', 'min:0', 'max:4'],
            'canvas.objects.*.door_face' => ['nullable', 'string', 'in:interior,exterior'],
            'canvas.objects.*.x' => ['nullable', 'numeric'],
            'canvas.objects.*.y' => ['nullable', 'numeric'],
            'canvas.objects.*.depth' => ['nullable', 'numeric'],
            'canvas.objects.*.rotation' => ['nullable', 'numeric'],
            'canvas.wall_thickness_in' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'canvas.background' => ['nullable', 'array'],
            'canvas.background.x' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.y' => ['required_with:canvas.background', 'numeric'],
            'canvas.background.width' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.background.height' => ['required_with:canvas.background', 'numeric', 'gt:0'],
            'canvas.editor' => ['nullable', 'array'],
            'canvas.editor.layers_locked' => ['nullable', 'boolean'],
            'canvas.editor.dimensions_mode' => ['nullable', 'string', 'in:all,main,area,off'],
            'canvas.editor.unit_system' => ['nullable', 'string', 'in:imperial,metric'],
            'canvas.editor.measure_unit' => ['nullable', 'string', 'in:meter,inch,feet_decimal,feet_inch_frac'],
            'canvas.editor.angle_snap' => ['nullable', 'boolean'],
            'canvas.editor.template' => ['nullable', 'string', 'in:rect,l,u,t,alcove'],
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
        if (app(RoomAreaCanvasData::class)->isLayersLocked($room_area_room->vertices)
            && $room_area_room->background_path) {
            return response()->json([
                'ok' => false,
                'message' => 'Unlock layers to change the floor plan.',
            ], 422);
        }

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
        if (app(RoomAreaCanvasData::class)->isLayersLocked($room_area_room->vertices)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unlock layers to change the floor plan.',
            ], 422);
        }

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
            'wall_thickness_in' => (float) ($project->wall_thickness_in ?? 0),
            'canvas_width' => $project->canvas_width,
            'canvas_height' => $project->canvas_height,
            'rooms' => $project->rooms->map(fn (RoomAreaRoom $room) => $this->roomPayload($room))->values(),
        ];
    }

    private function roomPayload(RoomAreaRoom $room, ?RoomAreaCanvasData $canvasData = null): array
    {
        $canvasData ??= app(RoomAreaCanvasData::class);
        $bridge = app(\App\Services\OpenPlan3DMeasurementBridge::class);
        if (! $room->relationLoaded('photos')) {
            $room->load('photos');
        }

        $isOpenPlan3D = $bridge->isPlan($room->vertices);
        $scale = (float) ($room->scale_pixels_per_foot ?? $room->project?->scale_pixels_per_foot ?? 0);

        if ($isOpenPlan3D) {
            $plan = $room->vertices;
            $canvas = is_array($room->legacy_canvas_data) ? $canvasData->normalize($room->legacy_canvas_data) : null;
            $groupSummaries = [];
            $metrics = $room->layoutMetrics(tolerant: true);
        } else {
            $canvas = $canvasData->normalize($room->vertices);
            $groupSummaries = $scale > 0
                ? $canvasData->groupSummaries($canvas, $scale)
                : [];
            $metrics = $scale > 0
                ? $room->layoutMetrics(tolerant: true)
                : $canvasData->emptyLayoutMetrics($room->resolvedWallHeightFt());
            try {
                $plan = $scale > 0
                    ? $bridge->fromCanvasData($canvas, $scale, $room->resolvedWallHeightFt(), $room->finishSettings())
                    : null;
            } catch (\InvalidArgumentException $e) {
                $plan = null;
                if ($scale > 0 && empty($metrics['geometry_unmeasurable'])) {
                    $metrics['geometry_unmeasurable'] = true;
                    $metrics['geometry_message'] = trim($e->getMessage()) ?: 'Room geometry could not be measured.';
                }
            }
        }

        return [
            'id' => $room->id,
            'name' => $room->name,
            'vertices' => $room->vertices,
            'canvas' => $canvas,
            'plan' => $plan,
            'is_openplan3d' => $isOpenPlan3D,
            'legacy_canvas_data' => $room->legacy_canvas_data,
            'group_summaries' => $groupSummaries,
            'metrics' => $metrics,
            'canvas_width' => $room->canvas_width,
            'canvas_height' => $room->canvas_height,
            'scale_pixels_per_foot' => $room->scale_pixels_per_foot,
            'wall_height_ft' => $room->resolvedWallHeightFt(),
            'ceiling_height_ft' => $room->resolvedCeilingHeightFt(),
            'flooring_waste_percent' => $metrics['flooring_waste_percent'],
            'flooring_direction' => $metrics['flooring_direction'],
            'flooring_box_sqft' => $metrics['flooring_box_sqft'],
            'background_url' => $room->background_path
                ? route('admin.utilities.room-area.rooms.background', $room)
                : null,
            'pdf_url' => $room->pdf_path
                ? route('admin.utilities.room-area.rooms.pdf', $room)
                : null,
            'area_sqft' => (float) $room->area_sqft,
            'photo_count' => $room->relationLoaded('photos')
                ? $room->photos->count()
                : (int) ($room->photos_count ?? $room->photos()->count()),
            'photos' => $room->relationLoaded('photos')
                ? $room->photos->map(fn (RoomAreaPhoto $photo) => $this->photoPayload($photo))->values()->all()
                : [],
            'assembly_ids' => $room->relationLoaded('assemblies')
                ? $room->assemblies->pluck('id')->values()->all()
                : $room->assemblies()->pluck('room_area_assemblies.id')->all(),
        ];
    }

    /**
     * @return array{assembliesJson: list<array<string, mixed>>, catalogPickerOptions: list<array<string, mixed>>}
     */
    private function assemblyPageData(): array
    {
        $kitSeedError = null;
        try {
            app(RoomAreaAssemblyDefaults::class)->ensure();
        } catch (\App\Services\RoomAreaKitSeedException $e) {
            $kitSeedError = $e->getMessage();
        }

        return [
            'assembliesJson' => \App\Models\RoomAreaAssembly::query()
                ->with('lines.material')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (\App\Models\RoomAreaAssembly $assembly) => $assembly->toEditorArray())
                ->values()
                ->all(),
            'catalogPickerOptions' => MaterialCatalogPickerOptions::activeCatalog(),
            'kitSeedError' => $kitSeedError,
        ];
    }

    /**
     * @return array{id: int, url: string, caption: ?string, width: ?int, height: ?int}
     */
    private function photoPayload(RoomAreaPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'url' => route('admin.utilities.room-area.rooms.photos.show', [
                'room_area_room' => $photo->room_area_room_id,
                'photo' => $photo->id,
            ]),
            'caption' => $photo->caption,
            'width' => $photo->width,
            'height' => $photo->height,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ceiling_height_ft: float, flooring_waste_percent: float, flooring_direction: int, flooring_box_sqft: float}
     */
    private function finishAttributes(array $data, RoomAreaCanvasData $canvasData, ?RoomAreaRoom $existing = null): array
    {
        $math = app(RoomAreaFinishMath::class);
        $wall = array_key_exists('wall_height_ft', $data)
            ? $canvasData->normalizeWallHeight($data['wall_height_ft'])
            : ($existing?->resolvedWallHeightFt() ?? $canvasData->normalizeWallHeight(null));

        return [
            'ceiling_height_ft' => $math->ceilingHeightFt(
                array_key_exists('ceiling_height_ft', $data) ? $data['ceiling_height_ft'] : $existing?->ceiling_height_ft,
                $wall
            ),
            'flooring_waste_percent' => $math->normalizeWastePercent(
                array_key_exists('flooring_waste_percent', $data)
                    ? $data['flooring_waste_percent']
                    : ($existing?->flooring_waste_percent ?? RoomAreaFinishMath::DEFAULT_WASTE_PERCENT)
            ),
            'flooring_direction' => $math->normalizeDirection(
                array_key_exists('flooring_direction', $data)
                    ? $data['flooring_direction']
                    : ($existing?->flooring_direction ?? RoomAreaFinishMath::DIRECTION_LENGTH)
            ),
            'flooring_box_sqft' => $math->normalizeBoxSqFt(
                array_key_exists('flooring_box_sqft', $data)
                    ? $data['flooring_box_sqft']
                    : ($existing?->flooring_box_sqft ?? RoomAreaFinishMath::DEFAULT_BOX_SQFT)
            ),
        ];
    }

    private function assertPhotoBelongsToRoom(RoomAreaRoom $room, RoomAreaPhoto $photo): void
    {
        if ((int) $photo->room_area_room_id !== (int) $room->id) {
            abort(404);
        }
    }

    /**
     * Restore the standalone editor to the same layout (and room, if any)
     * the user had open when they followed Guide.
     *
     * @return array{ra_project?: int, ra_room?: int}
     */
    private function standaloneEditorReturnParams(Request $request): array
    {
        $projectId = $request->integer('ra_project');
        $roomId = $request->integer('ra_room');

        $project = $projectId > 0
            ? RoomAreaProject::query()->find($projectId)
            : null;
        $room = $roomId > 0
            ? RoomAreaRoom::query()->find($roomId)
            : null;

        $params = [];
        if ($room) {
            if (! $project || (int) $room->room_area_project_id === (int) $project->id) {
                $params['ra_project'] = (int) $room->room_area_project_id;
                $params['ra_room'] = (int) $room->id;

                return $params;
            }
        }
        if ($project) {
            $params['ra_project'] = (int) $project->id;
        }

        return $params;
    }

    private function deleteRoomPhotos(RoomAreaRoom $room): void
    {
        app(RoomAreaPhotoStorage::class)->deleteDirectory((int) $room->id);
        $room->photos()->delete();
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

    /**
     * @param  array<string, mixed>  $canvas
     */
    private function syncProjectWallThickness(RoomAreaProject $project, array $canvas): void
    {
        $thickness = app(RoomAreaCanvasData::class)->normalizeWallThickness($canvas['wall_thickness_in'] ?? 0);
        if ((float) ($project->wall_thickness_in ?? 0) === $thickness) {
            return;
        }
        $project->update(['wall_thickness_in' => $thickness]);
    }

    /**
     * True when the openPlan3D payload contains at least one wall or a room
     * polygon with 3+ points. Empty room objects (rooms: [{}]) do not count.
     * confirm_zero_area cannot bypass this — an empty drawing is never saved.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $metrics
     */
    private function openPlanHasRoomOrWall(array $plan, array $metrics): bool
    {
        $floors = [];
        if (isset($plan['floor']) && is_array($plan['floor'])) {
            $floors[] = $plan['floor'];
        }
        if (isset($plan['floors']) && is_array($plan['floors'])) {
            foreach ($plan['floors'] as $floor) {
                if (is_array($floor)) {
                    $floors[] = $floor;
                }
            }
        }
        if (isset($plan['project']['floors']) && is_array($plan['project']['floors'])) {
            foreach ($plan['project']['floors'] as $floor) {
                if (is_array($floor)) {
                    $floors[] = $floor;
                }
            }
        }
        if ($floors === [] && (isset($plan['walls']) || isset($plan['rooms']))) {
            $floors[] = $plan;
        }

        foreach ($floors as $floor) {
            foreach ($floor['walls'] ?? [] as $wall) {
                if (is_array($wall) && isset($wall['start'], $wall['end'])) {
                    return true;
                }
            }
            foreach ($floor['rooms'] ?? [] as $room) {
                if (! is_array($room)) {
                    continue;
                }
                $poly = $room['polygon'] ?? $room['points'] ?? [];
                if (is_array($poly) && count($poly) >= 3) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>|null  $finish
     * @return array<string, mixed>|JsonResponse
     */
    private function openPlanLayoutMetricsOr422(\App\Services\OpenPlan3DMeasurementBridge $bridge, array $plan, ?string $selectedRoomId, ?array $finish)
    {
        try {
            return $bridge->layoutMetrics($plan, $selectedRoomId, $finish ?? []);
        } catch (InvalidArgumentException $e) {
            $message = trim($e->getMessage());
            if ($message === '') {
                $message = 'Room geometry could not be measured — adjust corners and try again.';
            }

            return response()->json(['ok' => false, 'message' => $message], 422);
        }
    }
}
