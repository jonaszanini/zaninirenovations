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
 *   - joist_spacing_in    : 12 | 16
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

        $spacing = (int) ($inputs['joist_spacing_in'] ?? $this->settings->default_joist_spacing_in);
        if (! in_array($spacing, [12, 16], true)) {
            $spacing = 16;
        }

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
            'joist_spacing_in' => $spacing,
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

        $spacingFt = (float) $in['joist_spacing_in'] / 12.0;
        $blockSpacingFt = max(0.5, (float) $this->settings->block_spacing_ft);

        // --- Block grid (max spacing both directions) -------------------
        $gridLinesRun = $this->gridLinesAlong($runFt, $blockSpacingFt);
        $gridLinesSpan = $this->gridLinesAlong($spanFt, $blockSpacingFt);
        $blockPositionsX = $this->evenPositions($runFt, $gridLinesRun);
        $blockPositionsY = $this->evenPositions($spanFt, $gridLinesSpan);
        $blockCount = $gridLinesRun * $gridLinesSpan;
        $cornerCount = $blockCount >= 4 ? 4 : $blockCount;

        // --- Joists (run in span direction, distributed over run) -------
        $joistPositionsX = $this->joistPositions($runFt, $spacingFt);
        $totalJoistLines = count($joistPositionsX);
        $fieldJoists = max(0, $totalJoistLines - 2); // exclude the 2 end rims

        // --- Beams (run in run direction, at each span grid line) -------
        $beamLines = $gridLinesSpan;
        $interiorBeams = max(0, $beamLines - 2);

        // --- Deck boards (run in run direction, stacked over span) ------
        $boardWidth = (float) $this->settings->board_width_in;
        $boardGap = (float) $this->settings->board_gap_in;
        $boardPitch = max(0.01, $boardWidth + $boardGap);
        $boardRows = $spanFt > 0 ? (int) ceil(($spanFt * 12.0) / $boardPitch) : 0;

        // --- Frame intersections ----------------------------------------
        $crossJoints = $fieldJoists * $interiorBeams;
        $tJoints = $fieldJoists * 2 + 4 + $interiorBeams * 2;

        // --- Joist tape (tops of all decking-bearing members) -----------
        $overlapFt = (float) $this->settings->joist_tape_overlap_in / 12.0;
        $rollLengthFt = max(1.0, (float) $this->settings->joist_tape_roll_length_ft);
        $tapedMembers = $fieldJoists + 2 /* end rims */ + 2 /* headers */ + $interiorBeams;
        $tapeBaseFt = $fieldJoists * $spanFt
            + 2 * $spanFt
            + 2 * $runFt
            + $interiorBeams * $runFt;
        $totalTapeFt = round($tapeBaseFt + $tapedMembers * $overlapFt, 2);
        $tapeRolls = $totalTapeFt > 0 ? (int) ceil($totalTapeFt / $rollLengthFt) : 0;

        return [
            'width_ft' => round($width, 2),
            'length_ft' => round($length, 2),
            'area_sq_ft' => $area,
            'span_ft' => round($spanFt, 2),
            'run_ft' => round($runFt, 2),
            'joist_spacing_in' => (int) $in['joist_spacing_in'],
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
                'total_lines' => $totalJoistLines,
                'length_ft' => round($spanFt, 2),
                'positions_x_ft' => $joistPositionsX,
            ],
            'beams' => [
                'lines_total' => $beamLines,
                'interior_count' => $interiorBeams,
                'length_ft' => round($runFt, 2),
                'positions_y_ft' => $blockPositionsY,
            ],
            'boards' => [
                'rows' => $boardRows,
                'run_ft' => round($runFt, 2),
                'board_width_in' => round($boardWidth, 3),
                'board_gap_in' => round($boardGap, 3),
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

    /** Number of grid lines so spacing never exceeds the max (min 2). */
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

    /** Joist line positions at spacing O.C. plus an end joist at the far edge. */
    private function joistPositions(float $runFt, float $spacingFt): array
    {
        if ($runFt <= 0 || $spacingFt <= 0) {
            return [];
        }

        $positions = [];
        $pos = 0.0;
        while ($pos < $runFt - 1e-6) {
            $positions[] = round($pos, 3);
            $pos += $spacingFt;
        }
        $positions[] = round($runFt, 3); // closing end joist

        return $positions;
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
            [$spanFt, $spanFt],                 // 2 end rim joists
            [$runFt, $runFt],                   // 2 perimeter headers
            array_fill(0, (int) $geom['beams']['interior_count'], $runFt)
        );
        $joistPieces = $this->splitToStock($joistRuns, $this->maxStockLengthFt($joistStocks));
        $joists = LowDeckLumberPacker::pack($joistPieces, $joistStocks, $kerf);

        // Deck boards: one row per board pitch, each row runs the long side.
        $boardPieces = $this->splitToStock(
            array_fill(0, (int) $geom['boards']['rows'], $runFt),
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
            '%d rows × %s ft (5.5 in boards, %s in gap)',
            (int) $geom['boards']['rows'],
            rtrim(rtrim(number_format((float) $geom['boards']['run_ft'], 2), '0'), '.'),
            rtrim(rtrim(number_format((float) $geom['boards']['board_gap_in'], 3), '0'), '.')
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
        $boardLengthIn = (int) $geom['boards']['rows'] * (float) $geom['boards']['run_ft'] * 12.0;
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
