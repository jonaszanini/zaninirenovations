<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\RoomAreaSignature;
use Barryvdh\DomPDF\Facade\Pdf;

class RoomAreaPlanDocument
{
    private RoomAreaPhaseTotals $totals;

    private RoomAreaPlanSvg $svg;

    private RoomAreaSignatureStorage $signatures;

    public function __construct(
        ?RoomAreaPhaseTotals $totals = null,
        ?RoomAreaPlanSvg $svg = null,
        ?RoomAreaSignatureStorage $signatures = null
    ) {
        $this->totals = $totals ?? new RoomAreaPhaseTotals;
        $this->svg = $svg ?? new RoomAreaPlanSvg;
        $this->signatures = $signatures ?? new RoomAreaSignatureStorage;
    }

    public const PAPER = 'letter';

    public const REMOTE_ENABLED = false;

    /**
     * @param  array{name?: string, date?: string|null, image?: string|null, signed?: bool}|null  $signatureOverride
     * @return array<string, mixed>
     */
    public function build(RoomAreaProject $project, ?array $signatureOverride = null): array
    {
        $project->loadMissing(['phases', 'rooms', 'clientProject', 'signatures']);
        $grouped = $this->totals->grouped($project);
        $sections = [];
        foreach ($grouped['phases'] as $phase) {
            $sections[] = $this->section($phase['name'], $phase['totals'], $phase['rooms'], $phase['room_metrics'] ?? []);
        }
        if ($grouped['unassigned']['rooms']->isNotEmpty() || $sections === []) {
            $sections[] = $this->section(
                (string) $grouped['unassigned']['name'],
                $grouped['unassigned']['totals'],
                $grouped['unassigned']['rooms'],
                $grouped['unassigned']['room_metrics'] ?? []
            );
        }

        return [
            'brand' => 'Zanini Renovations',
            'logo_data_uri' => $this->logoDataUri(),
            'project_name' => (string) $project->name,
            'client_name' => (string) ($project->clientProject?->client_name ?: ''),
            'client_project_name' => (string) ($project->clientProject?->name ?: ''),
            'generated_at' => now()->format('M j, Y g:i A'),
            'totals' => $grouped['project'],
            'sections' => $sections,
            'signature' => $signatureOverride ?? $this->signatureFromActive($project),
        ];
    }

    /**
     * @param  array{name?: string, date?: string|null, image?: string|null, signed?: bool}|null  $signatureOverride
     */
    public function pdfBinary(RoomAreaProject $project, ?array $signatureOverride = null): string
    {
        $pdf = Pdf::loadView('admin.utilities.room_area_plan_pdf', $this->build($project, $signatureOverride))
            ->setPaper(self::PAPER, 'portrait')
            ->setOption('isRemoteEnabled', self::REMOTE_ENABLED)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->output();
    }

    /**
     * @param  array<string, float|int>  $totals
     * @param  iterable<int, RoomAreaRoom>  $rooms
     * @param  list<array<string, float|int>>  $allocated
     * @return array{name: string, totals: array<string, float|int>, rooms: list<array<string, mixed>>}
     */
    private function section(string $name, array $totals, iterable $rooms, array $allocated = []): array
    {
        $rows = [];
        $index = 0;
        foreach ($rooms as $room) {
            $metrics = $allocated[$index] ?? $room->layoutMetrics();
            $index++;
            $rows[] = [
                'name' => (string) $room->name,
                'svg' => $this->svg->forRoom($room),
                'dimensions' => $this->svg->edgeLabels($room),
                'metrics' => [
                    'area_sqft' => (float) ($metrics['area_sqft'] ?? 0),
                    'perimeter_lf' => (float) ($metrics['perimeter_lf'] ?? 0),
                    'net_wall_area_sqft' => (float) ($metrics['net_wall_area_sqft'] ?? 0),
                    'flooring_with_waste_sqft' => (float) ($metrics['flooring_with_waste_sqft'] ?? 0),
                    'casing_lf' => (float) ($metrics['casing_lf'] ?? 0),
                ],
            ];
        }

        return [
            'name' => $name,
            'totals' => $totals,
            'rooms' => $rows,
        ];
    }

    /**
     * @return array{name: string, date: string|null, image: string|null, signed: bool}
     */
    private function signatureFromActive(RoomAreaProject $project): array
    {
        $active = $project->activeSignature();
        if (! $active instanceof RoomAreaSignature) {
            return [
                'name' => '',
                'date' => null,
                'image' => null,
                'signed' => false,
            ];
        }

        return [
            'name' => (string) $active->signer_name,
            'date' => $active->signed_at?->format('F j, Y'),
            'image' => $this->signatures->dataUriForPath($active->signature_path),
            'signed' => true,
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }
        $binary = file_get_contents($path);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
