<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\PaintingPaintLine;
use App\Models\PaintingQuotation;
use App\Models\PaintingSettings;
use Illuminate\Support\Collection;

class PaintingQuotationCalculator
{
    public const DEFAULT_COATS = 2;

    public const EXCLUDED_MODE_DIMENSIONS = 'dimensions';

    public const EXCLUDED_MODE_SQF = 'sqf';

    public static function defaultSurfacePrepDescription(): string
    {
        return 'Light sanding, minor filling of nail holes, small imperfections, scrapes, knicks, screw pops, spot priming, and cleaning prior to painting.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        $defaultPaintLineId = PaintingPaintLine::query()->activeOrdered()->value('id');

        return [
            'total_property_sqf' => 0,
            'excluded_areas' => [],
            'paint_line_id' => $defaultPaintLineId ? (int) $defaultPaintLineId : null,
            'coats' => self::DEFAULT_COATS,
            'surface_prep_description' => self::defaultSurfacePrepDescription(),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft = false): array
    {
        if ($draft) {
            return [];
        }

        $hints = [];
        $total = max(0, (float) ($inputs['total_property_sqf'] ?? 0));
        if ($total <= 0) {
            $hints[] = 'Enter the total property size in square feet.';
        }

        $net = $this->netPaintingArea($inputs);
        if ($net <= 0) {
            $hints[] = 'Net painting area must be greater than zero after exclusions.';
        }

        $paintLineId = (int) ($inputs['paint_line_id'] ?? 0);
        if ($paintLineId <= 0 || ! PaintingPaintLine::query()->whereKey($paintLineId)->exists()) {
            $hints[] = 'Select a paint brand/line.';
        }

        return $hints;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function excludedAreaTotal(array $inputs): float
    {
        $sum = 0.0;
        foreach ($this->normalizedExcludedAreas($inputs) as $row) {
            $sum += $row['sqf'];
        }

        return round(max(0, $sum), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function netPaintingArea(array $inputs): float
    {
        $total = max(0, (float) ($inputs['total_property_sqf'] ?? 0));
        $excluded = $this->excludedAreaTotal($inputs);

        return round(max(0, $total - $excluded), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array{name: string, mode: string, length_ft: ?float, width_ft: ?float, sqf: float, detail: string}>
     */
    public function normalizedExcludedAreas(array $inputs): array
    {
        $rows = $inputs['excluded_areas'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $mode = (string) ($row['mode'] ?? self::EXCLUDED_MODE_DIMENSIONS);
            if ($mode === self::EXCLUDED_MODE_SQF) {
                $sqf = round(max(0, (float) ($row['sqf'] ?? 0)), 2);
                if ($sqf <= 0) {
                    continue;
                }
                $out[] = [
                    'name' => $name,
                    'mode' => self::EXCLUDED_MODE_SQF,
                    'length_ft' => null,
                    'width_ft' => null,
                    'sqf' => $sqf,
                    'detail' => number_format($sqf, 0).' sq ft',
                ];
            } else {
                $length = round(max(0, (float) ($row['length_ft'] ?? 0)), 2);
                $width = round(max(0, (float) ($row['width_ft'] ?? 0)), 2);
                $sqf = round($length * $width, 2);
                if ($sqf <= 0) {
                    continue;
                }
                $out[] = [
                    'name' => $name,
                    'mode' => self::EXCLUDED_MODE_DIMENSIONS,
                    'length_ft' => $length,
                    'width_ft' => $width,
                    'sqf' => $sqf,
                    'detail' => rtrim(rtrim(number_format($length, 1, '.', ''), '0'), '.')
                        .' × '
                        .rtrim(rtrim(number_format($width, 1, '.', ''), '0'), '.')
                        .' ft',
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(
        array $inputs,
        ?PaintingSettings $settings = null,
        ?Collection $paintLines = null,
    ): array {
        $settings = $settings ?? PaintingSettings::current();
        $paintLines = $paintLines ?? PaintingPaintLine::query()->activeOrdered()->get();

        $totalProperty = max(0, round((float) ($inputs['total_property_sqf'] ?? 0), 2));
        $excludedRows = $this->normalizedExcludedAreas($inputs);
        $excludedTotal = round(array_sum(array_column($excludedRows, 'sqf')), 2);
        $netArea = round(max(0, $totalProperty - $excludedTotal), 2);

        $laborRate = max(0, \App\Services\SharedWorkCatalog::flatRate('paint_walls', (float) $settings->labor_per_sqf));
        $paintPerGallon = max(0, (float) $settings->paint_per_gallon);
        $addonFee = max(0, (float) $settings->material_addon_fee);
        $sqfPerGallon = max(0.01, (float) $settings->sqf_per_gallon);
        $coats = max(1, (int) ($inputs['coats'] ?? self::DEFAULT_COATS));

        $paintLineId = (int) ($inputs['paint_line_id'] ?? 0);
        /** @var PaintingPaintLine|null $paintLine */
        $paintLine = $paintLineId > 0 ? $paintLines->firstWhere('id', $paintLineId) : null;
        $paintLineName = $paintLine?->name ?? 'Paint';

        $gallonsNeeded = $netArea > 0 ? (int) ceil($netArea / $sqfPerGallon) : 0;
        $laborAmount = round($netArea * $laborRate, 2);
        $paintMaterialAmount = round($gallonsNeeded * $paintPerGallon, 2);
        $materialsAmount = round($paintMaterialAmount + $addonFee, 2);
        $subtotalPreHst = round($laborAmount + $materialsAmount, 2);
        $hst = round($subtotalPreHst * PaintingQuotation::HST_RATE, 2);
        $grandTotal = round($subtotalPreHst + $hst, 2);

        $breakdown = [];
        if ($netArea > 0) {
            $breakdown[] = [
                'key' => 'labor',
                'label' => 'Painting service (labor)',
                'detail' => number_format($netArea, 0).' sq ft × '.ClientProject::formatMoney($laborRate).'/sq ft',
                'amount' => $laborAmount,
            ];
            $breakdown[] = [
                'key' => 'materials',
                'label' => 'Materials',
                'detail' => $gallonsNeeded.' gal × '.ClientProject::formatMoney($paintPerGallon)
                    .' + '.ClientProject::formatMoney($addonFee).' expendables',
                'amount' => $materialsAmount,
            ];
        }

        return [
            'scope' => [
                'total_property_sqf' => $totalProperty,
                'excluded_total_sqf' => $excludedTotal,
                'excluded_areas' => $excludedRows,
                'net_painting_area_sqf' => $netArea,
                'paint_line_id' => $paintLineId ?: null,
                'paint_line_name' => $paintLineName,
                'coats' => $coats,
                'gallons_needed' => $gallonsNeeded,
                'sqf_per_gallon' => $sqfPerGallon,
            ],
            'breakdown' => $breakdown,
            'summary' => [
                'labor_pre_hst' => $laborAmount,
                'materials_pre_hst' => $materialsAmount,
                'subtotal_pre_hst' => $subtotalPreHst,
                'hst' => $hst,
                'grand_total' => $grandTotal,
                'final_quote_total' => $grandTotal,
            ],
        ];
    }
}
