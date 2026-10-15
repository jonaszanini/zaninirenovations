<?php

namespace App\Services;

use App\Models\RoomAreaRoom;
use Illuminate\Support\Facades\DB;

class RoomAreaDrawingImporter
{
    public function __construct(
        private OpenPlan3DMeasurementBridge $bridge,
        private RoomAreaCanvasData $canvasData,
    ) {}

    /**
     * Convert a single RoomAreaRoom to openPlan3D format.
     *
     * @return array{
     *   ok: bool,
     *   status: 'converted'|'skipped'|'failed',
     *   message: string,
     *   room_id: int,
     *   room_name: string,
     *   old_area: ?float,
     *   new_area: ?float,
     *   plan: ?array<string, mixed>
     * }
     */
    public function convert(RoomAreaRoom $room, bool $force = false): array
    {
        $roomId = (int) $room->id;
        $roomName = (string) $room->name;
        $rawVertices = $room->vertices;

        // If already in openPlan3D format and not forcing re-import
        if (! $force && $this->bridge->isPlan($rawVertices)) {
            return [
                'ok' => true,
                'status' => 'skipped',
                'message' => 'Room drawing is already in openPlan3D format.',
                'room_id' => $roomId,
                'room_name' => $roomName,
                'old_area' => (float) $room->area_sqft,
                'new_area' => (float) $room->area_sqft,
                'plan' => $rawVertices,
            ];
        }

        // If forcing re-import on an openPlan3D room, use legacy_canvas_data if present
        $sourceDrawing = ($force && $this->bridge->isPlan($rawVertices) && is_array($room->legacy_canvas_data))
            ? $room->legacy_canvas_data
            : $rawVertices;

        if (empty($sourceDrawing) || ! is_array($sourceDrawing)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Drawing data is empty or invalid array.',
                'room_id' => $roomId,
                'room_name' => $roomName,
                'old_area' => (float) $room->area_sqft,
                'new_area' => null,
                'plan' => null,
            ];
        }

        $scale = $room->resolvedScalePixelsPerFoot();
        if ($scale <= 0) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => "Room has no valid scale (resolved scale is {$scale} px/ft).",
                'room_id' => $roomId,
                'room_name' => $roomName,
                'old_area' => (float) $room->area_sqft,
                'new_area' => null,
                'plan' => null,
            ];
        }

        $normalized = $this->canvasData->normalize($sourceDrawing);
        $validation = $this->canvasData->validate($normalized, $scale);
        if (! $validation['valid']) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Canvas geometry validation failed: '.($validation['message'] ?? 'Invalid geometry'),
                'room_id' => $roomId,
                'room_name' => $roomName,
                'old_area' => (float) $room->area_sqft,
                'new_area' => null,
                'plan' => null,
            ];
        }

        $wallHeight = $room->resolvedWallHeightFt();
        $finish = $room->finishSettings();
        $plan = $this->bridge->fromCanvasData($normalized, $scale, $wallHeight, $finish);

        if (! $this->bridge->isPlan($plan)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Generated openPlan3D data is not recognized as a valid plan structure.',
                'room_id' => $roomId,
                'room_name' => $roomName,
                'old_area' => (float) $room->area_sqft,
                'new_area' => null,
                'plan' => null,
            ];
        }

        $metrics = $this->bridge->layoutMetrics($plan, null, $finish);
        $newArea = (float) $metrics['area_sqft'];

        return [
            'ok' => true,
            'status' => 'converted',
            'message' => 'Successfully converted canvas drawing to openPlan3D.',
            'room_id' => $roomId,
            'room_name' => $roomName,
            'old_area' => (float) $room->area_sqft,
            'new_area' => $newArea,
            'plan' => $plan,
        ];
    }

    /**
     * Batch import all rooms in the database.
     *
     * @return array{
     *   dry_run: bool,
     *   force: bool,
     *   total: int,
     *   converted: int,
     *   skipped: int,
     *   failed: int,
     *   results: list<array<string, mixed>>,
     *   failures: list<array{room_id: int, room_name: string, message: string}>
     * }
     */
    public function run(bool $dryRun = true, bool $force = false): array
    {
        $rooms = RoomAreaRoom::query()->with('project')->orderBy('id')->get();
        $total = $rooms->count();
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];
        $failures = [];

        foreach ($rooms as $room) {
            $result = $this->convert($room, $force);
            $results[] = $result;

            if ($result['status'] === 'converted') {
                $converted++;
                if (! $dryRun && $result['plan'] !== null) {
                    DB::transaction(function () use ($room, $result) {
                        $legacyBackup = $room->legacy_canvas_data ?? (
                            $this->bridge->isPlan($room->vertices) ? null : $room->vertices
                        );

                        $room->update([
                            'legacy_canvas_data' => $legacyBackup,
                            'vertices' => $result['plan'],
                            'area_sqft' => $result['new_area'],
                            'openplan3d_import_meta' => [
                                'imported_at' => now()->toIso8601String(),
                                'original_area_sqft' => $result['old_area'],
                                'converted_area_sqft' => $result['new_area'],
                                'scale_pixels_per_foot' => $room->resolvedScalePixelsPerFoot(),
                                'version' => 1,
                            ],
                        ]);
                    });
                }
            } elseif ($result['status'] === 'skipped') {
                $skipped++;
            } else {
                $failed++;
                $failures[] = [
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'dry_run' => $dryRun,
            'force' => $force,
            'total' => $total,
            'converted' => $converted,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
            'failures' => $failures,
        ];
    }
}
