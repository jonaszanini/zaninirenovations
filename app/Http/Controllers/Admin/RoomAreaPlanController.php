<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaMeasurementExport;
use App\Services\RoomAreaPlanDocument;
use App\Services\RoomAreaSignatureStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoomAreaPlanController extends Controller
{
    public function planPdf(RoomAreaProject $room_area_project, RoomAreaPlanDocument $document): Response
    {
        $data = $document->build($room_area_project);
        $pdf = Pdf::loadView('admin.utilities.room_area_plan_pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream($this->filename($room_area_project->name, 'plan', 'pdf'));
    }

    public function exportCsv(RoomAreaProject $room_area_project, RoomAreaMeasurementExport $export): Response
    {
        $csv = $export->csv($room_area_project);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($room_area_project->name, 'measurements', 'csv').'"',
        ]);
    }

    public function exportXlsx(RoomAreaProject $room_area_project, RoomAreaMeasurementExport $export): Response
    {
        $xlsx = $export->xlsx($room_area_project);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($room_area_project->name, 'measurements', 'xlsx').'"',
        ]);
    }

    public function storePhase(Request $request, RoomAreaProject $room_area_project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);
        $next = ((int) $room_area_project->phases()->max('sort_order')) + 1;
        $phase = $room_area_project->phases()->create([
            'name' => $data['name'],
            'sort_order' => $next,
        ]);

        return response()->json([
            'ok' => true,
            'phase' => $this->phasePayload($phase),
            'project' => $this->projectPayload($room_area_project),
        ], 201);
    }

    public function updatePhase(Request $request, RoomAreaPhase $room_area_phase)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);
        $room_area_phase->update(['name' => $data['name']]);

        return response()->json([
            'ok' => true,
            'phase' => $this->phasePayload($room_area_phase),
            'project' => $this->projectPayload($room_area_phase->project),
        ]);
    }

    public function destroyPhase(RoomAreaPhase $room_area_phase)
    {
        $project = $room_area_phase->project;
        $room_area_phase->delete();

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($project),
        ]);
    }

    public function assignRoomPhase(Request $request, RoomAreaRoom $room_area_room)
    {
        $data = $request->validate([
            'phase_id' => ['nullable', 'integer', 'exists:room_area_phases,id'],
        ]);
        $phaseId = $data['phase_id'] ?? null;
        if ($phaseId) {
            $phase = RoomAreaPhase::query()->findOrFail($phaseId);
            if ((int) $phase->room_area_project_id !== (int) $room_area_room->room_area_project_id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'That phase belongs to a different project.',
                ], 422);
            }
        }
        $room_area_room->phase_id = $phaseId;
        $room_area_room->save();

        return response()->json([
            'ok' => true,
            'room' => [
                'id' => $room_area_room->id,
                'phase_id' => $room_area_room->phase_id,
            ],
            'project' => $this->projectPayload($room_area_room->project),
        ]);
    }

    public function saveSignature(
        Request $request,
        RoomAreaProject $room_area_project,
        RoomAreaSignatureStorage $storage
    ) {
        $data = $request->validate([
            'signer_name' => ['nullable', 'string', 'max:120'],
            'signed_at' => ['nullable', 'date'],
            'signature' => ['nullable', 'string'],
        ]);

        $path = $room_area_project->signature_path;
        if (! empty($data['signature'])) {
            $binary = $storage->decodePngDataUrl((string) $data['signature']);
            if ($binary === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Use a PNG signature image.',
                ], 422);
            }
            $path = $storage->storePng($room_area_project, $binary);
        }

        $room_area_project->fill([
            'signer_name' => $data['signer_name'] ?? $room_area_project->signer_name,
            'signed_at' => $data['signed_at'] ?? $room_area_project->signed_at,
            'signature_path' => $path,
        ]);
        $room_area_project->save();

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($room_area_project),
        ]);
    }

    public function clearSignature(RoomAreaProject $room_area_project, RoomAreaSignatureStorage $storage)
    {
        $storage->delete($room_area_project);
        $room_area_project->fill([
            'signer_name' => null,
            'signed_at' => null,
            'signature_path' => null,
        ]);
        $room_area_project->save();

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($room_area_project),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(RoomAreaProject $project): array
    {
        return app(RoomAreaController::class)->planProjectPayload($project);
    }

    /**
     * @return array{id: int, name: string, sort_order: int}
     */
    private function phasePayload(RoomAreaPhase $phase): array
    {
        return [
            'id' => (int) $phase->id,
            'name' => (string) $phase->name,
            'sort_order' => (int) $phase->sort_order,
        ];
    }

    private function filename(string $title, string $suffix, string $ext): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'room-area';
        }

        return $slug.'-'.$suffix.'.'.$ext;
    }
}
