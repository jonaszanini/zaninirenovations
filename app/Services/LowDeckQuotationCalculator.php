<?php

namespace App\Services;

use App\Exceptions\LowDeckAreaExceededException;
use App\Models\LowDeckFormulaSetting;
use App\Models\LowDeckMaterial;
use App\Models\LowDeckQuotation;
use App\Support\LowDeckLumberPacker;

/**
 * LowDeckQuotationCalculator — the structural math engine behind the Low
 * Deck floating quotation module.
 *
 * Inputs (from the UI):
 *   - width_ft            : float (> 0)
 *   - length_ft           : float (> 0)
 *   - labour_rate_per_sqft: float (optional, falls back to settings)
 *   - waste_dump_fee_cad  : float (optional, falls back to settings)
 *   - material_waste_percent: float (optional, default 10 — admin-only ordering buffer)
 *
 * Output (`calculate()`):
 *   {
 *     inputs:    echo of the normalised inputs,
 *     geometry:  { area, block grid, joists, beams, boards, intersections, joist tape },
 *     packing:   { posts, joists, boards } bin-packing snapshots,
 *     materials: [ { key, name, quantity, unit, unit_price, subtotal, notes } ],
 *     totals:    { materials_subtotal, waste_dump_fee, hst, labour_subtotal, labour_hst, grand_total }
 *   }
 *
 * The output is JSON-serialisable so it can be stored verbatim on
 * `low_deck_quotations.calculation` and replayed without recalculation.
 */
class LowDeckQuotationCalculator
{
    /** Nominal 2×6 thickness in feet (plan view). */
    private const RIM_JOIST_FT = 1.5 / 12.0;

    /** Clear gap between perimeter rim and inner edge joist (inches). */
    private const EDGE_INSET_GAP_IN = 2.25;

    /** Maximum uniform center spacing for field joists (inches). */
    private const MAX_FIELD_JOIST_SPACING_IN = 15;

    /** Joist hangers per field joist at the centre blocking beam. */
    private const HANGERS_PER_FIELD_JOIST = 2;

    private LowDeckFormulaSetting $settings;

    /** @var \Illuminate\Support\Collection<string, LowDeckMaterial> */
    private $catalogue;

    public function __construct(?LowDeckFormulaSetting $settings = null, $catalogue = null)
    {
        $this->settings = $settings ?: LowDeckFormulaSetting::current();
        $this->catalogue = $catalogue ?: LowDeckMaterial::catalogueByKey();
    }

    public function calculate(array $inputs): array
    {
        $normalised = $this->normaliseInputs($inputs);

        $area = round($normalised['width_ft'] * $normalised['length_ft'], 2);
        $maxArea = (float) $this->settings->max_area_sq_ft;
        if ($area > $maxArea + 1e-6) {
            throw new LowDeckAreaExceededException($area, $maxArea);
        }

        $geometry = $this->computeGeometry($normalised);
        $packing = $this->computePacking($geometry);
        $materials = $this->computeMaterials($normalised, $geometry, $packing);
        $totals = $this->computeTotals($materials, $geometry, $normalised);

        return [
            'inputs' => $normalised,
            'geometry' => $geometry,
            'packing' => $packing,
            'materials' => $materials,
            'totals' => $totals,
        ];
    }

    /** ================================================================
     *   INPUT NORMALISATION
     *  ================================================================ */

    private function normaliseInputs(array $inputs): array
    {
        $width = max(0.0, round((float) ($inputs['width_ft'] ?? 0), 2));
        $length = max(0.0, round((float) ($inputs['length_ft'] ?? 0), 2));

        $labourRate = array_key_exists('labour_rate_per_sqft', $inputs) && $inputs['labour_rate_per_sqft'] !== null && $inputs['labour_rate_per_sqft'] !== ''
            ? round(max(0.0, (float) $inputs['labour_rate_per_sqft']), 2)
            : round((float) $this->settings->labour_rate_per_sqft, 2);

        $wasteFee = array_key_exists('waste_dump_fee_cad', $inputs) && $inputs['waste_dump_fee_cad'] !== null && $inputs['waste_dump_fee_cad'] !== ''
            ? round(max(0.0, (float) $inputs['waste_dump_fee_cad']), 2)
            : round((float) $this->settings->waste_dump_fee_cad, 2);

        $materialWastePercent = array_key_exists('material_waste_percent', $inputs) && $inputs['material_waste_percent'] !== null && $inputs['material_waste_percent'] !== ''
            ? round(max(0.0, (float) $inputs['material_waste_percent']), 2)
            : 10.0;
        if ($materialWastePercent > 0 && $materialWastePercent <= 1) {
            $materialWastePercent *= 100;
        }

        return [
            'width_ft' => $width,
            'length_ft' => $length,
            'labour_rate_per_sqft' => $labourRate,
            'waste_dump_fee_cad' => $wasteFee,
            'material_waste_percent' => $materialWastePercent,
        ];
    }

    /** ================================================================
     *   GEOMETRY
     *  ================================================================ */

    private function computeGeometry(array $in): array
    {
        $width = (float) $in['width_ft'];
        $length = (float) $in['length_ft'];
        $area = round($width * $length, 2);

        // Joists span the shorter dimension; they are distributed over the
        // longer dimension. Beams and deck boards run along the longer side.
        $spanFt = min($width, $length);
        $runFt = max($width, $length);

        $blockSpacingFt = max(0.5, (float) $this->settings->block_spacing_ft);
        $gridLinesRun = $this->gridLinesAlong($runFt, $blockSpacingFt);
        $gridLinesSpan = $this->gridLinesAlong($spanFt, $blockSpacingFt);
        $blockPositionsX = $this->evenPositions($runFt, $gridLinesRun);
        $blockPositionsY = $this->evenPositions($spanFt, $gridLinesSpan);
        $blockCount = $gridLinesRun * $gridLinesSpan;
        $cornerCount = $blockCount >= 4 ? 4 : $blockCount;

        // --- Joists (run in span direction, distributed over run) -------
        $edgeInsetFt = $this->edgeInsetCenterFt();
        $framing = $this->computeJoistFraming($runFt, $edgeInsetFt);
        $fieldJoists = $framing['field_count'];
        $joistPositionsX = $framing['positions_x_ft'];
        $totalJoistLines = count($joistPositionsX);
        $joistSpacingIn = $framing['field_spacing_in'];

        // --- Beams (run in run direction) --------------------------------
        $edgeInsetY = $edgeInsetFt;
        $outerHeaderYs = [0.0, $spanFt];
        $edgeHeaderYs = [$edgeInsetY, round($spanFt - $edgeInsetY, 3)];
        $beamPositionsY = $this->uniqueSorted(array_merge($outerHeaderYs, $edgeHeaderYs, $blockPositionsY));
        $beamLines = count($beamPositionsY);
        $interiorBeams = max(0, $beamLines - 4); // exclude 2 outer + 2 inner edge headers

        // --- Deck boards (picture-frame border + infill field) ----------
        $boardLayout = $this->computeDeckBoardLayout($runFt, $spanFt);

        // --- Joist hangers (field joists at centre interior beam) -------
        $centerBeamY = $this->centerInteriorBeamY($beamPositionsY, $spanFt, $edgeHeaderYs);
        $fieldJoistPositions = $framing['field_positions_x_ft'];
        $hangerCount = $fieldJoists > 0 && $centerBeamY !== null
            ? $fieldJoists * self::HANGERS_PER_FIELD_JOIST
            : 0;
        $hangerPositions = [];
        if ($hangerCount > 0 && $centerBeamY !== null) {
            foreach ($fieldJoistPositions as $x) {
                $hangerPositions[] = [
                    'x_ft' => (float) $x,
                    'y_ft' => $centerBeamY,
                ];
            }
        }

        // --- Frame intersections ----------------------------------------
        $verticalFraming = $fieldJoists + 2; // field + inner edge joists (left/right)
        $crossJoints = $verticalFraming * $interiorBeams;
        $tJoints = $verticalFraming * 2 + 4 + $interiorBeams * 2 + 4;

        // --- Joist tape (tops of all decking-bearing members) -----------
        $overlapFt = (float) $this->settings->joist_tape_overlap_in / 12.0;
        $rollLengthFt = max(1.0, (float) $this->settings->joist_tape_roll_length_ft);
        $tapedMembers = $fieldJoists + 2 /* inner edge vertical */
            + 2 /* outer rim joists */
            + 2 /* outer headers */
            + 2 /* inner edge headers */
            + $interiorBeams;
        $tapeBaseFt = $fieldJoists * $spanFt
            + 2 * $spanFt /* inner edge vertical */
            + 2 * $spanFt /* outer rims */
            + 2 * $runFt /* outer headers */
            + 2 * $runFt /* inner edge headers */
            + $interiorBeams * $runFt;
        $totalTapeFt = round($tapeBaseFt + $tapedMembers * $overlapFt, 2);
        $tapeRolls = $totalTapeFt > 0 ? (int) ceil($totalTapeFt / $rollLengthFt) : 0;

        return [
            'width_ft' => round($width, 2),
            'length_ft' => round($length, 2),
            'area_sq_ft' => $area,
            'span_ft' => round($spanFt, 2),
            'run_ft' => round($runFt, 2),
            'joist_spacing_in' => $joistSpacingIn,
            'max_joist_spacing_in' => self::MAX_FIELD_JOIST_SPACING_IN,
            'edge_inset_in' => self::EDGE_INSET_GAP_IN,
            'blocks' => [
                'count' => $blockCount,
                'corner_count' => $cornerCount,
                'grid_lines_run' => $gridLinesRun,
                'grid_lines_span' => $gridLinesSpan,
                'positions_x_ft' => $blockPositionsX,
                'positions_y_ft' => $blockPositionsY,
            ],
            'joists' => [
                'field_count' => $fieldJoists,
                'edge_count' => 2,
                'rim_count' => 2,
                'total_lines' => $totalJoistLines,
                'length_ft' => round($spanFt, 2),
                'field_spacing_in' => $joistSpacingIn,
                'edge_inset_ft' => round($edgeInsetFt, 3),
                'rim_positions_x_ft' => [0.0, round($runFt, 3)],
                'edge_inset_positions_x_ft' => [
                    round($edgeInsetFt, 3),
                    round($runFt - $edgeInsetFt, 3),
                ],
                'field_positions_x_ft' => $framing['field_positions_x_ft'],
                'positions_x_ft' => $joistPositionsX,
            ],
            'beams' => [
                'lines_total' => $beamLines,
                'interior_count' => $interiorBeams,
                'edge_count' => 2,
                'length_ft' => round($runFt, 2),
                'edge_inset_ft' => round($edgeInsetY, 3),
                'rim_positions_y_ft' => $outerHeaderYs,
                'edge_inset_positions_y_ft' => $edgeHeaderYs,
                'positions_y_ft' => $beamPositionsY,
            ],
            'boards' => $boardLayout,
            'joist_hangers' => [
                'count' => $hangerCount,
                'per_field_joist' => self::HANGERS_PER_FIELD_JOIST,
                'field_joist_count' => $fieldJoists,
                'beam_y_ft' => $centerBeamY,
                'positions_ft' => $hangerPositions,
            ],
            'intersections' => [
                't_count' => $tJoints,
                'cross_count' => $crossJoints,
            ],
            'joist_tape' => [
                'total_tape_ft' => $totalTapeFt,
                'rolls_required' => $tapeRolls,
                'members_taped' => $tapedMembers,
                'roll_length_ft' => round($rollLengthFt, 2),
            ],
        ];
    }

    /**
     * Interior blocking beam closest to deck mid-span (excludes rim + edge headers).
     *
     * @param  list<float>  $beamYs
     * @param  list<float>  $edgeHeaderYs
     */
    private function centerInteriorBeamY(array $beamYs, float $spanFt, array $edgeHeaderYs): ?float
    {
        $eps = 0.001;
        $center = $spanFt / 2.0;
        $exclude = array_merge([0.0, round($spanFt, 3)], array_map(
            static fn ($y) => round((float) $y, 3),
            $edgeHeaderYs
        ));

        $interior = [];
        foreach ($beamYs as $y) {
            $y = round((float) $y, 3);
            foreach ($exclude as $excluded) {
                if (abs($y - $excluded) < $eps) {
                    continue 2;
                }
            }
            $interior[] = $y;
        }

        if ($interior === []) {
            return null;
        }

        usort($interior, static fn (float $a, float $b): int => abs($a - $center) <=> abs($b - $center));

        return $interior[0];
    }

    /**
     * Picture-frame decking: mitred border boards plus infill rows at 1/8 in. gaps.
     *
     * @return array<string, mixed>
     */
    private function computeDeckBoardLayout(float $runFt, float $spanFt): array
    {
        $boardWidthIn = (float) $this->settings->board_width_in;
        $boardGapIn = (float) $this->settings->board_gap_in;
        $boardWidthFt = $boardWidthIn / 12.0;

        if ($runFt <= 0 || $spanFt <= 0) {
            return [
                'layout' => 'picture_frame',
                'border_count' => 0,
                'infill_rows' => 0,
                'total_boards' => 0,
                'rows' => 0,
                'run_ft' => 0.0,
                'board_width_in' => round($boardWidthIn, 3),
                'board_gap_in' => round($boardGapIn, 3),
                'board_width_ft' => 0.0,
                'inner_run_ft' => 0.0,
                'inner_span_ft' => 0.0,
                'border_parallel_ft' => 0.0,
                'border_perpendicular_ft' => 0.0,
                'infill_length_ft' => 0.0,
                'total_length_ft' => 0.0,
                'runs_ft' => [],
            ];
        }

        $innerRunFt = max(0.0, $runFt - 2 * $boardWidthFt);
        $innerSpanFt = max(0.0, $spanFt - 2 * $boardWidthFt);
        $sideBorderFt = round($innerSpanFt, 3);
        $parallelBorderFt = round($runFt, 3);
        $infillLengthFt = round($innerRunFt, 3);
        $infill = $this->computeInfillRowWidths($innerSpanFt, $boardWidthIn, $boardGapIn);
        $infillRowWidthsIn = $infill['row_widths_in'];
        $infillRows = $infill['total_rows'];
        $infillFullRows = $infill['full_rows'];
        $infillRipRows = $infill['rip_rows'];

        $runsFt = array_merge(
            [$parallelBorderFt, $parallelBorderFt],
            [$sideBorderFt, $sideBorderFt],
            array_fill(0, $infillRows, $infillLengthFt)
        );

        $totalBoards = count($runsFt);
        $totalLengthFt = round(array_sum($runsFt), 2);

        return [
            'layout' => 'picture_frame',
            'border_count' => 4,
            'infill_rows' => $infillRows,
            'infill_full_rows' => $infillFullRows,
            'infill_rip_rows' => $infillRipRows,
            'infill_row_widths_in' => $infillRowWidthsIn,
            'total_boards' => $totalBoards,
            'rows' => $infillRows,
            'run_ft' => round($runFt, 2),
            'board_width_in' => round($boardWidthIn, 3),
            'board_gap_in' => round($boardGapIn, 3),
            'board_width_ft' => round($boardWidthFt, 4),
            'inner_run_ft' => $infillLengthFt,
            'inner_span_ft' => round($innerSpanFt, 3),
            'border_parallel_ft' => $parallelBorderFt,
            'border_perpendicular_ft' => $sideBorderFt,
            'infill_length_ft' => $infillLengthFt,
            'total_length_ft' => $totalLengthFt,
            'runs_ft' => $runsFt,
        ];
    }

    /**
     * Full-width infill rows plus one ripped row when a remainder is left in the field.
     *
     * @return array{full_rows: int, rip_rows: int, total_rows: int, row_widths_in: list<float>}
     */
    private function computeInfillRowWidths(float $innerSpanFt, float $boardWidthIn, float $boardGapIn): array
    {
        $innerSpanIn = max(0.0, $innerSpanFt * 12.0);
        $pitchIn = max(0.01, $boardWidthIn + $boardGapIn);

        if ($innerSpanIn <= 0) {
            return [
                'full_rows' => 0,
                'rip_rows' => 0,
                'total_rows' => 0,
                'row_widths_in' => [],
            ];
        }

        $fullRows = 0;
        for ($n = 1; ; $n++) {
            $usedIn = ($n - 1) * $pitchIn + $boardWidthIn;
            if ($usedIn > $innerSpanIn + 1e-6) {
                break;
            }
            $fullRows = $n;
        }

        $widthsIn = array_fill(0, $fullRows, round($boardWidthIn, 3));
        $usedIn = $fullRows > 0 ? ($fullRows - 1) * $pitchIn + $boardWidthIn : 0.0;
        $remainderIn = max(0.0, $innerSpanIn - $usedIn);
        $ripRows = 0;

        if ($remainderIn > 0.01) {
            $gapBeforeRip = $fullRows > 0 ? $boardGapIn : 0.0;
            $ripWidthIn = max(0.0, $remainderIn - $gapBeforeRip);
            if ($ripWidthIn > 0.01) {
                $widthsIn[] = round($ripWidthIn, 3);
                $ripRows = 1;
            }
        }

        return [
            'full_rows' => $fullRows,
            'rip_rows' => $ripRows,
            'total_rows' => count($widthsIn),
            'row_widths_in' => $widthsIn,
        ];
    }

    private function gridLinesAlong(float $lengthFt, float $maxSpacingFt): int
    {
        if ($lengthFt <= 0) {
            return 0;
        }

        return max(2, (int) ceil($lengthFt / $maxSpacingFt) + 1);
    }

    /** @return list<float> evenly spaced positions from 0 to length. */
    private function evenPositions(float $lengthFt, int $lines): array
    {
        if ($lines <= 0) {
            return [];
        }
        if ($lines === 1) {
            return [0.0];
        }

        $positions = [];
        for ($i = 0; $i < $lines; $i++) {
            $positions[] = round($lengthFt * $i / ($lines - 1), 3);
        }

        return $positions;
    }

    /** Joist line positions: rims, edge inset joists, and evenly spaced field joists. */
    private function computeJoistFraming(float $runFt, float $edgeInsetFt): array
    {
        if ($runFt <= 0) {
            return [
                'field_count' => 0,
                'field_spacing_in' => 0.0,
                'field_positions_x_ft' => [],
                'positions_x_ft' => [],
            ];
        }

        $innerLeft = $edgeInsetFt + self::RIM_JOIST_FT / 2.0;
        $innerRight = $runFt - $edgeInsetFt - self::RIM_JOIST_FT / 2.0;
        $clearSpanFt = max(0.0, $innerRight - $innerLeft);
        $clearSpanIn = $clearSpanFt * 12.0;

        $fieldCount = 0;
        $spacingIn = $clearSpanIn;
        while ($spacingIn > self::MAX_FIELD_JOIST_SPACING_IN + 1e-6) {
            $fieldCount++;
            $spacingIn = $clearSpanIn / ($fieldCount + 1);
        }
        $spacingIn = round($spacingIn, 2);
        $spacingFt = $spacingIn / 12.0;

        $fieldPositions = [];
        for ($i = 1; $i <= $fieldCount; $i++) {
            $fieldPositions[] = round($innerLeft + $spacingFt * $i, 3);
        }

        $positions = $this->uniqueSorted(array_merge(
            [0.0, round($edgeInsetFt, 3)],
            $fieldPositions,
            [round($runFt - $edgeInsetFt, 3), round($runFt, 3)]
        ));

        return [
            'field_count' => $fieldCount,
            'field_spacing_in' => $spacingIn,
            'field_positions_x_ft' => $fieldPositions,
            'positions_x_ft' => $positions,
        ];
    }

    private function edgeInsetCenterFt(): float
    {
        return round(
            self::RIM_JOIST_FT + self::EDGE_INSET_GAP_IN / 12.0 + self::RIM_JOIST_FT / 2.0,
            4
        );
    }

    /**
     * @param  list<float|int>  $values
     * @return list<float>
     */
    private function uniqueSorted(array $values): array
    {
        $unique = array_values(array_unique(array_map(
            static fn ($v) => round((float) $v, 3),
            $values
        )));
        sort($unique, SORT_NUMERIC);

        return $unique;
    }

    /** ================================================================
     *   BIN PACKING (posts, joists/beams, deck boards)
     *  ================================================================ */

    private function computePacking(array $geom): array
    {
        $kerf = (float) $this->settings->kerf_in / 12.0;

        $postStocks = $this->stockOptions(LowDeckMaterial::POST_STOCK_KEYS);
        $joistStocks = $this->stockOptions(LowDeckMaterial::JOIST_STOCK_KEYS);
        $boardStocks = $this->stockOptions(LowDeckMaterial::BOARD_STOCK_KEYS);

        // Posts: one short post per block.
        $postLen = (float) $this->settings->post_length_per_block_ft;
        $postPieces = $this->splitToStock(
            array_fill(0, (int) $geom['blocks']['count'], $postLen),
            $this->maxStockLengthFt($postStocks)
        );
        $posts = LowDeckLumberPacker::pack($postPieces, $postStocks, $kerf);

        // Joists + rims + interior beams all come from 2x6 stock. Members
        // longer than stock are spliced over the beam grid (even segments).
        $spanFt = (float) $geom['span_ft'];
        $runFt = (float) $geom['run_ft'];
        $joistRuns = array_merge(
            array_fill(0, (int) $geom['joists']['field_count'], $spanFt),
            array_fill(0, (int) ($geom['joists']['edge_count'] ?? 2), $spanFt),
            array_fill(0, (int) ($geom['joists']['rim_count'] ?? 2), $spanFt),
            array_fill(0, 2, $runFt),                   // outer perimeter headers
            array_fill(0, (int) ($geom['beams']['edge_count'] ?? 2), $runFt),
            array_fill(0, (int) $geom['beams']['interior_count'], $runFt)
        );
        $joistPieces = $this->splitToStock($joistRuns, $this->maxStockLengthFt($joistStocks));
        $joists = LowDeckLumberPacker::pack($joistPieces, $joistStocks, $kerf);

        // Deck boards: picture-frame border pieces + infill rows.
        $boardRuns = $geom['boards']['runs_ft'] ?? [];
        $boardPieces = $this->splitToStock(
            array_map('floatval', $boardRuns),
            $this->maxStockLengthFt($boardStocks)
        );
        $boards = LowDeckLumberPacker::pack($boardPieces, $boardStocks, $kerf);

        return [
            'posts' => $posts,
            'joists' => $joists,
            'boards' => $boards,
        ];
    }

    /**
     * @param  list<array{key: string, length_ft: float, price: float}>  $stocks
     */
    private function maxStockLengthFt(array $stocks): float
    {
        $max = 0.0;
        foreach ($stocks as $stock) {
            $max = max($max, (float) $stock['length_ft']);
        }

        return $max > 0 ? $max : 12.0;
    }

    /**
     * Split each run that is longer than the longest commercial stock into
     * equal segments that each fit (a splice over the support grid). Runs
     * within stock length pass through unchanged.
     *
     * @param  list<float>  $runs
     * @return list<float>
     */
    private function splitToStock(array $runs, float $maxStockFt): array
    {
        $pieces = [];
        foreach ($runs as $run) {
            $run = (float) $run;
            if ($run <= 0) {
                continue;
            }
            if ($run <= $maxStockFt + 1e-6) {
                $pieces[] = round($run, 3);

                continue;
            }
            $segments = (int) ceil($run / $maxStockFt);
            $segLen = round($run / $segments, 3);
            for ($i = 0; $i < $segments; $i++) {
                $pieces[] = $segLen;
            }
        }

        return $pieces;
    }

    /**
     * @param  array<int, string>  $stockKeys  [length_ft => catalogue key]
     * @return list<array{key: string, length_ft: float, price: float}>
     */
    private function stockOptions(array $stockKeys): array
    {
        $options = [];
        foreach ($stockKeys as $lengthFt => $key) {
            /** @var LowDeckMaterial|null $mat */
            $mat = $this->catalogue->get($key);
            $options[] = [
                'key' => $key,
                'length_ft' => (float) $lengthFt,
                'price' => $mat ? $mat->effectivePriceCad() : 0.0,
            ];
        }

        return $options;
    }

    /** ================================================================
     *   MATERIALS — one row per catalogue entry / synthetic line.
     *  ================================================================ */

    private function computeMaterials(array $in, array $geom, array $packing): array
    {
        $settings = $this->settings;
        $wastePercent = (float) ($in['material_waste_percent'] ?? 0);
        $rows = [];

        // -------- BLOCKS -------------------------------------------------
        $blocks = (int) $geom['blocks']['count'];
        if ($blocks > 0) {
            $rows[] = $this->row(
                LowDeckMaterial::KEY_BLOCK_4X4,
                $this->wastefulQuantity($blocks, $wastePercent),
                'ea',
                sprintf(
                    '%d × %d grid (max %s ft on centre)',
                    (int) $geom['blocks']['grid_lines_run'],
                    (int) $geom['blocks']['grid_lines_span'],
                    rtrim(rtrim(number_format((float) $settings->block_spacing_ft, 2), '0'), '.')
                )
            );
        }

        // -------- POSTS (packed) ----------------------------------------
        foreach ($this->lumberRows($packing['posts'], 'Posts: '.number_format((float) $settings->post_length_per_block_ft, 2).' ft per block', $wastePercent) as $row) {
            $rows[] = $row;
        }

        // -------- GRAVEL -------------------------------------------------
        $footFt = (float) $settings->gravel_footprint_in / 12.0;
        $depthFt = (float) $settings->gravel_depth_in / 12.0;
        $cubicPerBlock = $footFt * $footFt * $depthFt;
        $perBag = max(0.001, (float) $settings->gravel_cubic_ft_per_bag);
        $gravelCubic = $cubicPerBlock * $blocks;
        $gravelBags = $gravelCubic > 0 ? (int) ceil($gravelCubic / $perBag) : 0;
        if ($gravelBags > 0) {
            $rows[] = $this->row(
                LowDeckMaterial::KEY_GRAVEL_BAG,
                $this->wastefulQuantity($gravelBags, $wastePercent),
                'bag',
                sprintf(
                    '%s cu ft total (%s cu ft / block × %d), %s cu ft / bag',
                    rtrim(rtrim(number_format($gravelCubic, 2), '0'), '.'),
                    rtrim(rtrim(number_format($cubicPerBlock, 3), '0'), '.'),
                    $blocks,
                    rtrim(rtrim(number_format($perBag, 3), '0'), '.')
                )
            );
        }

        // -------- JOISTS / RIMS / BEAMS (packed) ------------------------
        $joistNote = sprintf(
            'Field joists: %d, rims + headers: 4, interior beams: %d',
            (int) $geom['joists']['field_count'],
            (int) $geom['beams']['interior_count']
        );
        foreach ($this->lumberRows($packing['joists'], $joistNote, $wastePercent) as $row) {
            $rows[] = $row;
        }

        // -------- JOIST HANGERS (2x6) ------------------------------------
        $hangers = (int) ($geom['joist_hangers']['count'] ?? 0);
        if ($hangers > 0) {
            $fieldJoists = (int) ($geom['joist_hangers']['field_joist_count'] ?? 0);
            $perJoist = (int) ($geom['joist_hangers']['per_field_joist'] ?? self::HANGERS_PER_FIELD_JOIST);
            $rows[] = $this->row(
                LowDeckMaterial::KEY_JOIST_HANGER_2X6,
                $this->wastefulQuantity($hangers, $wastePercent),
                'ea',
                sprintf(
                    '%d hangers (%d per field joist × %d at centre beam)',
                    $hangers,
                    $perJoist,
                    $fieldJoists
                )
            );
        }

        // -------- JOIST TAPE --------------------------------------------
        $rolls = (int) $geom['joist_tape']['rolls_required'];
        if ($rolls > 0) {
            $rows[] = $this->row(
                LowDeckMaterial::KEY_JOIST_TAPE_50FT,
                $this->wastefulQuantity($rolls, $wastePercent),
                'roll',
                sprintf(
                    '%s linear ft of joist tops, %s ft per roll',
                    rtrim(rtrim(number_format((float) $geom['joist_tape']['total_tape_ft'], 2), '0'), '.'),
                    rtrim(rtrim(number_format((float) $geom['joist_tape']['roll_length_ft'], 2), '0'), '.')
                )
            );
        }

        // -------- DECK BOARDS (packed) ----------------------------------
        $boardNote = sprintf(
            '%d boards: 4 border (45° miters) + %d infill%s × %s ft (5.5 in boards, %s in gap)',
            (int) ($geom['boards']['total_boards'] ?? 0),
            (int) ($geom['boards']['infill_rows'] ?? 0),
            ((int) ($geom['boards']['infill_rip_rows'] ?? 0) > 0) ? ' incl. 1 ripped' : '',
            rtrim(rtrim(number_format((float) ($geom['boards']['infill_length_ft'] ?? 0), 2), '0'), '.'),
            rtrim(rtrim(number_format((float) ($geom['boards']['board_gap_in'] ?? 0), 3), '0'), '.')
        );
        foreach ($this->lumberRows($packing['boards'], $boardNote, $wastePercent) as $row) {
            $rows[] = $row;
        }

        // -------- STRUCTURAL SCREWS (6 in) ------------------------------
        $cornerCount = (int) $geom['blocks']['corner_count'];
        $standardPosts = max(0, $blocks - $cornerCount);
        $structuralScrews = $standardPosts * (int) $settings->structural_screws_per_post
            + $cornerCount * (int) $settings->structural_screws_per_corner;
        if ($structuralScrews > 0) {
            $rows[] = $this->boxRow(
                LowDeckMaterial::KEY_SCREW_STRUCTURAL_6IN,
                $structuralScrews,
                sprintf(
                    '%d screws (%d posts × %d, %d corners × %d)',
                    $structuralScrews,
                    $standardPosts,
                    (int) $settings->structural_screws_per_post,
                    $cornerCount,
                    (int) $settings->structural_screws_per_corner
                ),
                $wastePercent
            );
        }

        // -------- DECK BOARD SCREWS (2-1/2 in) --------------------------
        $boardLengthIn = (int) round(((float) ($geom['boards']['total_length_ft'] ?? 0)) * 12.0);
        $boardScrews = (int) ceil($boardLengthIn * (float) $settings->board_screws_per_inch);
        if ($boardScrews > 0) {
            $rows[] = $this->boxRow(
                LowDeckMaterial::KEY_SCREW_DECK_2_5IN,
                $boardScrews,
                sprintf(
                    '%d screws (2 per 10 in over %s in of boards)',
                    $boardScrews,
                    rtrim(rtrim(number_format($boardLengthIn, 1), '0'), '.')
                ),
                $wastePercent
            );
        }

        // -------- FRAME SCREWS (3 in) -----------------------------------
        $tJoints = (int) $geom['intersections']['t_count'];
        $crossJoints = (int) $geom['intersections']['cross_count'];
        $frameScrews = $tJoints * (int) $settings->frame_screws_per_t_joint
            + $crossJoints * (int) $settings->frame_screws_per_cross_joint;
        if ($frameScrews > 0) {
            $rows[] = $this->boxRow(
                LowDeckMaterial::KEY_SCREW_FRAME_3IN,
                $frameScrews,
                sprintf(
                    '%d screws (%d T-joints × %d, %d + crosses × %d)',
                    $frameScrews,
                    $tJoints,
                    (int) $settings->frame_screws_per_t_joint,
                    $crossJoints,
                    (int) $settings->frame_screws_per_cross_joint
                ),
                $wastePercent
            );
        }

        // -------- WASTE DUMP FEE (synthetic line) -----------------------
        $wasteFee = round((float) $in['waste_dump_fee_cad'], 2);
        if ($wasteFee > 0) {
            $rows[] = [
                'key' => LowDeckMaterial::KEY_WASTE_DUMP_FEE,
                'name' => 'Waste material dump fee',
                'long_name' => 'Waste material dump fee',
                'model' => null,
                'store_sku' => null,
                'url' => null,
                'quantity' => 1,
                'unit' => 'project',
                'unit_price' => $wasteFee,
                'subtotal' => $wasteFee,
                'notes' => 'Flat per-project disposal fee',
            ];
        }

        return $rows;
    }

    /**
     * Turn a packer result into one material row per stock length used.
     *
     * @return list<array<string, mixed>>
     */
    private function lumberRows(array $packing, string $note, float $wastePercent): array
    {
        $rows = [];
        $byKey = $packing['by_key'] ?? [];

        foreach ($byKey as $key => $count) {
            if ((int) $count <= 0) {
                continue;
            }
            $rows[] = $this->row($key, $this->wastefulQuantity((int) $count, $wastePercent), 'ea', $note);
        }

        return $rows;
    }

    private function wastefulQuantity(int|float $baseQty, float $wastePercent): int|float
    {
        if ($baseQty <= 0 || $wastePercent <= 0) {
            return $baseQty;
        }

        $adjusted = (float) $baseQty * (1 + ($wastePercent / 100));

        if (is_int($baseQty) || abs($baseQty - round($baseQty)) < 1e-6) {
            return (int) ceil($adjusted - 1e-9);
        }

        return round($adjusted, 2);
    }

    /**
     * Build a single material row from the catalogue (per-unit pricing).
     */
    private function row(string $key, int|float $quantity, string $unit, string $notes): array
    {
        /** @var LowDeckMaterial|null $mat */
        $mat = $this->catalogue->get($key);

        $quantity = is_int($quantity) ? $quantity : round((float) $quantity, 2);
        $unitPrice = $mat ? $mat->effectivePriceCad() : 0.0;
        $subtotal = round($quantity * $unitPrice, 2);

        return [
            'key' => $key,
            'name' => $mat ? $mat->effectiveName() : $key,
            'long_name' => $mat?->effectiveLongName(),
            'model' => $mat?->effectiveModel(),
            'store_sku' => $mat?->effectiveStoreSku(),
            'url' => $mat?->effectiveUrl(),
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => round($unitPrice, 2),
            'subtotal' => $subtotal,
            'notes' => $notes,
        ];
    }

    /**
     * Build a box-sold material row: rounds the loose unit count up to whole
     * retail boxes and prices per box.
     */
    private function boxRow(string $key, int $unitsNeeded, string $notes, float $wastePercent = 0.0): array
    {
        /** @var LowDeckMaterial|null $mat */
        $mat = $this->catalogue->get($key);

        $adjustedUnits = (int) $this->wastefulQuantity($unitsNeeded, $wastePercent);
        $boxSize = (int) ($mat?->effectiveQuantityPerBox() ?? 0);
        $boxes = ($boxSize > 0 && $adjustedUnits > 0) ? (int) ceil($adjustedUnits / $boxSize) : 0;
        $unitPrice = $mat ? $mat->effectivePriceCad() : 0.0;
        $subtotal = round($boxes * $unitPrice, 2);

        return [
            'key' => $key,
            'name' => $mat ? $mat->effectiveName() : $key,
            'long_name' => $mat?->effectiveLongName(),
            'model' => $mat?->effectiveModel(),
            'store_sku' => $mat?->effectiveStoreSku(),
            'url' => $mat?->effectiveUrl(),
            'quantity' => $boxes,
            'unit' => 'box',
            'unit_price' => round($unitPrice, 2),
            'subtotal' => $subtotal,
            'notes' => $notes,
            'quantity_of_individual_units' => $adjustedUnits,
            'quantity_per_box' => $boxSize,
        ];
    }

    /** ================================================================
     *   TOTALS
     *  ================================================================ */

    private function computeTotals(array $materials, array $geom, array $in): array
    {
        $materialsSubtotal = round(array_sum(array_column($materials, 'subtotal')), 2);
        $hst = round($materialsSubtotal * LowDeckQuotation::HST_RATE, 2);

        $area = (float) $geom['area_sq_ft'];
        $labourRate = (float) $in['labour_rate_per_sqft'];
        $labour = round($area * $labourRate, 2);
        $labourHst = round($labour * LowDeckQuotation::HST_RATE, 2);

        $total = round($materialsSubtotal + $hst + $labour, 2);
        $wasteFee = round((float) $in['waste_dump_fee_cad'], 2);

        return [
            'materials_subtotal' => $materialsSubtotal,
            'waste_dump_fee' => $wasteFee,
            'hst' => $hst,
            'labour_subtotal' => $labour,
            'total' => $total,
            'labour_hst' => $labourHst,
            'grand_total' => round($total + $labourHst, 2),
            'hst_rate' => LowDeckQuotation::HST_RATE,
            'labour_rate_per_sqft' => round($labourRate, 2),
            'area_sq_ft' => round($area, 2),
            'material_waste_percent' => round((float) ($in['material_waste_percent'] ?? 0), 2),
        ];
    }
}
