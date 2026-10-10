<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\User;
use App\Services\RoomAreaMeasurementExport;
use App\Services\RoomAreaPlanDocument;
use App\Services\RoomAreaSignableQuote;
use App\Services\RoomAreaSignatureRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoomAreaPlanController extends Controller
{
    public function planPdf(
        RoomAreaProject $room_area_project,
        RoomAreaPlanDocument $document,
        RoomAreaSignatureRecorder $signatures
    ): Response {
        $active = $signatures->active($room_area_project);
        if ($active) {
            $frozen = $signatures->frozenPdf($active);
            if (! is_string($frozen) || $frozen === '') {
                return response(RoomAreaSignatureRecorder::MISSING_FROZEN_PDF_MESSAGE, 409, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }

            return response($frozen, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->filename($room_area_project->name, 'plan', 'pdf').'"',
            ]);
        }

        $pdf = $document->pdfBinary($room_area_project);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($room_area_project->name, 'plan', 'pdf').'"',
        ]);
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
            'phase_id' => ['nullable', 'integer'],
        ]);
        $phaseId = $data['phase_id'] ?? null;
        if ($phaseId) {
            $phase = RoomAreaPhase::query()->find($phaseId);
            if (! $phase || (int) $phase->room_area_project_id !== (int) $room_area_room->room_area_project_id) {
                abort(404);
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
        RoomAreaSignableQuote $quotes,
        RoomAreaSignatureRecorder $recorder
    ) {
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'standard_quotation_id' => ['required', 'integer'],
            'quote_version' => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ]);

        $quote = $quotes->findForProject($room_area_project, (int) $data['standard_quotation_id']);
        $admin = $request->user();
        if (! $admin instanceof User) {
            abort(403);
        }

        try {
            $signature = $recorder->record(
                $request,
                $room_area_project,
                $admin,
                $quote,
                trim((string) $data['signer_name']),
                (string) $data['signature'],
                (int) $data['quote_version']
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode() ?: 422);
        }

        return response()->json([
            'ok' => true,
            'signature_id' => $signature->id,
            'project' => $this->projectPayload($room_area_project->fresh()),
        ]);
    }

    public function voidSignature(
        Request $request,
        RoomAreaProject $room_area_project,
        RoomAreaSignatureRecorder $recorder
    ) {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $active = $recorder->active($room_area_project);
        if (! $active) {
            abort(404);
        }
        $admin = $request->user();
        if (! $admin instanceof User) {
            abort(403);
        }

        try {
            $recorder->void($active, $admin, (string) $data['reason']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode() ?: 422);
        }

        return response()->json([
            'ok' => true,
            'project' => $this->projectPayload($room_area_project->fresh()),
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
