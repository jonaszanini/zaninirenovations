<?php

namespace App\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;

/**
 * FenceQuotationCalculator — the structural math engine behind the
 * Fence quotation module.
 *
 * Inputs (from the UI):
 *   - sides          : array<['length_ft' => float]>   (1..4 sides)
 *   - fence_height_ft: 6 or 8
 *   - board_style    : 'standard' | 'overlapping'
 *   - overlap_in     : float (only used when board_style = overlapping)
 *   - gate_count     : int (>= 0)
 *
 * Output (`calculate()`):
 *   {
 *     inputs:      echo of the normalised inputs,
 *     geometry:    { total_linear_ft, sections_total, sides_breakdown },
 *     materials:   [ { key, name, long_name, model, store_sku, quantity, unit, unit_price, subtotal, notes, url } ],
 *     totals:      { materials_subtotal, hst, labour_subtotal, grand_total }
 *   }
 *
 * The output is deliberately JSON-serialisable so it can be stored on
 * `fence_quotations.calculation` verbatim and used by the PDF template
 * without further processing.
 */
class FenceQuotationCalculator
{
    private FenceFormulaSetting $settings;

    /** @var \Illuminate\Support\Collection<string, FenceMaterial> */
    private $catalogue;

    public function __construct(?FenceFormulaSetting $settings = null, $catalogue = null)
    {
        $this->settings = $settings ?: FenceFormulaSetting::current();
        $this->catalogue = $catalogue ?: FenceMaterial::catalogueByKey();
    }

    /**
     * Run the full calculation against the given inputs and return the
     * shape documented in the class docblock.
     */
    public function calculate(array $inputs): array
    {
        $normalised = $this->normaliseInputs($inputs);
        $geometry = $this->computeGeometry($normalised);
        $materials = $this->computeMaterials($normalised, $geometry);
        $totals = $this->computeTotals($materials, $geometry, $normalised);

        return [
            'inputs' => $normalised,
            'geometry' => $geometry,
            'materials' => $materials,
            'totals' => $totals,
        ];
    }

    /** ================================================================
     *   INPUT NORMALISATION
     *  ================================================================ */

    private function normaliseInputs(array $inputs): array
    {
        $rawSides = $inputs['sides'] ?? [];
        $sides = [];
        foreach ($rawSides as $side) {
            $len = (float) ($side['length_ft'] ?? 0);
            if ($len > 0) {
                $sides[] = ['length_ft' => round($len, 2)];
            }
        }

        $height = (float) ($inputs['fence_height_ft'] ?? 6);
        if (!in_array((int) round($height), [6, 8], true)) {
            $height = 6.0;
        }

        $style = ($inputs['board_style'] ?? FenceQuotation::BOARD_STYLE_STANDARD) === FenceQuotation::BOARD_STYLE_OVERLAPPING
            ? FenceQuotation::BOARD_STYLE_OVERLAPPING
            : FenceQuotation::BOARD_STYLE_STANDARD;

        $overlap = (float) ($inputs['overlap_in'] ?? 0);
        if ($style !== FenceQuotation::BOARD_STYLE_OVERLAPPING) {
            $overlap = 0.0;
        }

        return [
            'sides' => $sides,
            'fence_height_ft' => round($height, 2),
            'board_style' => $style,
            'overlap_in' => round(max($overlap, 0), 3),
            'gate_count' => max(0, (int) ($inputs['gate_count'] ?? 0)),
        ];
    }

    /** ================================================================
     *   GEOMETRY — how many sections, posts, pickets, etc.
     *  ================================================================ */

    private function computeGeometry(array $in): array
    {
        $maxSpacing = (float) $this->settings->max_post_spacing_ft;

        $sideCount = count($in['sides']);
        $totalLinearFt = 0.0;
        $sectionsTotal = 0;
        $sidesBreakdown = [];

        // Sections per side + per-section length (evenly distributed across
        // the side). Max spacing is the structural ceiling, so we bump the
        // section count by 1 each time the length crosses a multiple.
        foreach ($in['sides'] as $idx => $side) {
            $L = (float) $side['length_ft'];
            $sections = $L > 0 ? (int) ceil($L / $maxSpacing) : 0;
            if ($sections < 1 && $L > 0) {
                $sections = 1;
            }
            $sectionLength = $sections > 0 ? round($L / $sections, 3) : 0.0;

            $sidesBreakdown[] = [
                'index' => $idx + 1,
                'length_ft' => $L,
                'sections' => $sections,
                'section_length_ft' => $sectionLength,
            ];

            $totalLinearFt += $L;
            $sectionsTotal += $sections;
        }

        /*
         * Posts — open chain:
         *   posts_per_side = sections + 1
         *   total_posts    = Σ(sections+1) − (sideCount − 1)   // shared corners
         *                  = sectionsTotal + sideCount − sideCount + 1
         *                  = sectionsTotal + 1                  (when sideCount ≥ 1)
         *
         * Gates add 1 post each (the gate frame sits between two of the
         * fence's own posts; the extra post is the second gate jamb).
         */
        $structuralPosts = $sideCount > 0 ? ($sectionsTotal + 1) : 0;
        $gatePosts = (int) $in['gate_count'];
        $postsBeforeWaste = $structuralPosts + $gatePosts;

        return [
            'total_linear_ft' => round($totalLinearFt, 2),
            'sections_total' => $sectionsTotal,
            'structural_posts' => $structuralPosts,
            'gate_posts' => $gatePosts,
            'posts_before_waste' => $postsBeforeWaste,
            'sides_breakdown' => $sidesBreakdown,
        ];
    }

    /** ================================================================
     *   MATERIALS — one row per catalogue entry.
     *  ================================================================ */

    private function computeMaterials(array $in, array $geom): array
    {
        $height = (int) round((float) $in['fence_height_ft']);
        $settings = $this->settings;
        $totalFt = (float) $geom['total_linear_ft'];
        $sectionsTotal = (int) $geom['sections_total'];
        $postsBeforeWaste = (int) $geom['posts_before_waste'];

        // -------- POSTS --------------------------------------------------
        // Waste margin: +1 post per project.
        $postsTotal = $postsBeforeWaste + ($postsBeforeWaste > 0 ? 1 : 0);
        $postKey = $height === 8 ? FenceMaterial::KEY_POST_12 : FenceMaterial::KEY_POST_10;

        // -------- PICKETS (fence boards along the run) -------------------
        // Effective per-board width depends on style:
        //   standard:    board_width + gap    (board covers width + gap)
        //   overlapping: board_width − overlap (each next board hides part
        //                                        of the previous one)
        $boardWidth = (float) $settings->board_width_in;
        $gap = (float) $settings->standard_gap_in;
        $overlap = (float) $in['overlap_in'];

        if ($in['board_style'] === FenceQuotation::BOARD_STYLE_OVERLAPPING) {
            $effectiveWidth = max($boardWidth - $overlap, 0.01);
        } else {
            $effectiveWidth = $boardWidth + $gap;
        }

        $picketsBeforeWaste = $effectiveWidth > 0 && $totalFt > 0
            ? (int) ceil(($totalFt * 12.0) / $effectiveWidth)
            : 0;

        // Waste: +1 board per section.
        $picketsTotal = $picketsBeforeWaste + $sectionsTotal;
        $picketKey = $height === 8 ? FenceMaterial::KEY_BOARD_8 : FenceMaterial::KEY_BOARD_6;

        // -------- TOP BOARDS (one per section, length depends on span) --
        // 6' board if the span is ≤ 6 ft, otherwise 8' board. Gate spans
        // don't get a top board (gates have their own frame).
        $top6 = 0;
        $top8 = 0;
        foreach ($geom['sides_breakdown'] as $side) {
            $secLen = (float) $side['section_length_ft'];
            $secs = (int) $side['sections'];
            if ($secLen <= 6.0 + 1e-6) {
                $top6 += $secs;
            } else {
                $top8 += $secs;
            }
        }

        // -------- RAILS --------------------------------------------------
        $railsPerSection = $settings->railsPerSectionForHeight($height);
        $structuralRails = $sectionsTotal * $railsPerSection;
        $gateRails = (int) $in['gate_count'] * (int) $settings->gate_rails;
        $railsBeforeWaste = $structuralRails + $gateRails;
        // Waste: +3 rails per project.
        $railsTotal = $railsBeforeWaste + ($railsBeforeWaste > 0 ? 3 : 0);

        // -------- CONCRETE ----------------------------------------------
        $bagsPerPost = $settings->concretePerPostForHeight($height);
        $concreteStructural = (float) $postsBeforeWaste * $bagsPerPost;
        // Waste: +1 bag per 3 posts (rounded up, err on over-ordering).
        $concreteWaste = $postsBeforeWaste > 0 ? (int) ceil($postsBeforeWaste / 3) : 0;
        $concreteTotal = (int) ceil($concreteStructural) + $concreteWaste;

        // -------- NAILS --------------------------------------------------
        // Nails are sold by the box but the user stores a per-box price
        // and a quantity-per-box; the calculator converts to boxes for
        // ordering and pricing.
        $nailsPerPicket = (int) $settings->nails_per_picket;
        $nailsNeeded = $picketsTotal * $nailsPerPicket;
        $nailsMat = $this->catalogue->get(FenceMaterial::KEY_NAILS);
        $nailsBoxSize = (int) ($nailsMat->quantity_per_box ?? 0);
        $boxesTotal = ($nailsBoxSize > 0 && $nailsNeeded > 0)
            ? (int) ceil($nailsNeeded / $nailsBoxSize)
            : 0;

        // -------- BRACKETS ----------------------------------------------
        // 2 per fence rail (each end). Gate-frame rails use their own
        // hardware so they don't count here.
        $bracketsBeforeWaste = $structuralRails * 2;
        // Waste: +6 per project.
        $bracketsTotal = $bracketsBeforeWaste + ($bracketsBeforeWaste > 0 ? 6 : 0);

        // -------- POST CAPS ---------------------------------------------
        // 1 per post (incl. gate posts), +1 waste per project.
        $postCapsTotal = $postsBeforeWaste + ($postsBeforeWaste > 0 ? 1 : 0);

        // -------- GATE HARDWARE ------------------------------------------
        $gateHardware = (int) $in['gate_count'];

        // -------- ASSEMBLE ROWS -----------------------------------------
        // Posts
        $rows = [];
        $rows[] = $this->row(
            $postKey,
            $postsTotal,
            'ea',
            "Structural: {$postsBeforeWaste}, +1 waste. Max {$settings->max_post_spacing_ft}' spacing."
        );

        // Fence Boards (pickets = run along the fence)
        $rows[] = $this->row(
            $picketKey,
            $picketsTotal,
            'ea',
            "Pickets: {$picketsBeforeWaste}, +1 per section ({$sectionsTotal}) waste"
        );

        // Top boards — only add rows for sizes actually used.
        if ($top6 > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_BOARD_6,
                $top6,
                'ea',
                "Top board (sections ≤ 6')",
                // If picket key was also 6', merge into that row instead
                // for a cleaner quote. We handle the merge after the loop.
            );
        }
        if ($top8 > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_BOARD_8,
                $top8,
                'ea',
                "Top board (sections > 6')"
            );
        }

        // Rails
        $rows[] = $this->row(
            FenceMaterial::KEY_RAIL,
            $railsTotal,
            'ea',
            "Fence: {$structuralRails} ({$railsPerSection}/section), gates: {$gateRails}, +3 waste"
        );

        // Concrete
        $rows[] = $this->row(
            FenceMaterial::KEY_CONCRETE,
            $concreteTotal,
            'bag',
            sprintf(
                '%s bags/post × %d posts, +%d waste',
                rtrim(rtrim(number_format($bagsPerPost, 3), '0'), '.'),
                $postsBeforeWaste,
                $concreteWaste
            )
        );

        // Nails (stored as boxes; we surface both values for transparency)
        if ($nailsMat) {
            $rows[] = array_merge(
                $this->row(
                    FenceMaterial::KEY_NAILS,
                    $boxesTotal,
                    'box',
                    sprintf(
                        '%d nails needed (%d per picket × %d pickets) / %s per box',
                        $nailsNeeded,
                        $nailsPerPicket,
                        $picketsTotal,
                        $nailsBoxSize > 0 ? (string) $nailsBoxSize : '—'
                    )
                ),
                [
                    'quantity_of_individual_units' => $nailsNeeded,
                    'quantity_per_box' => $nailsBoxSize,
                ]
            );
        }

        // Brackets
        $rows[] = $this->row(
            FenceMaterial::KEY_BRACKET,
            $bracketsTotal,
            'ea',
            "2 × {$structuralRails} fence rails + 6 waste"
        );

        // Post caps
        $rows[] = $this->row(
            FenceMaterial::KEY_POST_CAP,
            $postCapsTotal,
            'ea',
            "1 per post + 1 waste"
        );

        // Gate hardware
        if ($gateHardware > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_GATE_HARDWARE,
                $gateHardware,
                'set',
                "1 set per gate"
            );
        }

        // Merge same-key rows so the final quote doesn't list 6' boards
        // twice (e.g. when 6' board is both the picket AND the top board).
        return $this->mergeSameKeyRows($rows);
    }

    /**
     * Build a single material row. Pulls price + display name from the
     * catalogue so the output is self-contained (no model needed at
     * render time).
     */
    private function row(string $key, int|float $quantity, string $unit, string $notes): array
    {
        /** @var FenceMaterial|null $mat */
        $mat = $this->catalogue->get($key);

        $quantity = is_int($quantity) ? $quantity : round((float) $quantity, 2);
        $unitPrice = $mat ? $mat->unitPrice() : 0.0;

        // When a material is sold by the box, the unit price on the row
        // represents "price per box" — we stored unitPrice() as per-nail
        // internally, so flip back for display. Nails are the only box
        // case today.
        $displayUnitPrice = ($mat && $mat->soldByBox())
            ? round((float) $mat->price_cad, 2)
            : round($unitPrice, 2);

        $subtotal = round($quantity * $displayUnitPrice, 2);

        return [
            'key' => $key,
            'name' => $mat ? $mat->name : $key,
            'long_name' => $mat?->long_name,
            'model' => $mat?->model,
            'store_sku' => $mat?->store_sku,
            'url' => $mat ? $mat->url : null,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $displayUnitPrice,
            'subtotal' => $subtotal,
            'notes' => $notes,
        ];
    }

    /**
     * Collapse duplicate material rows (same `key`) by summing quantity
     * and subtotal. Preserves the first row's notes but appends the
     * second's so the output explains both contributions.
     */
    private function mergeSameKeyRows(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = $row['key'];
            if (!isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }
            $merged[$key]['quantity'] += $row['quantity'];
            $merged[$key]['subtotal'] = round(
                (float) $merged[$key]['unit_price'] * (float) $merged[$key]['quantity'],
                2
            );
            $merged[$key]['notes'] = trim(($merged[$key]['notes'] ?? '').' + '.$row['notes'], ' +');
        }

        return array_values($merged);
    }

    /** ================================================================
     *   TOTALS — materials subtotal, HST, labour, grand total.
     *  ================================================================ */

    private function computeTotals(array $materials, array $geom, array $in): array
    {
        $materialsSubtotal = round(array_sum(array_column($materials, 'subtotal')), 2);
        $hst = round($materialsSubtotal * FenceQuotation::HST_RATE, 2);

        $heightFt = (float) ($in['fence_height_ft'] ?? 6);
        $rate6 = (float) ($in['labour_rate_per_ft'] ?? $this->settings->labour_rate_per_ft);
        $rate8 = (float) ($in['labour_rate_8ft_per_ft'] ?? $this->settings->labour_rate_8ft_per_ft ?? $rate6);
        $labourRatePerFt = ((int) round($heightFt)) === 8 ? $rate8 : $rate6;
        $labourPerAdditionalGate = (float) ($this->settings->labour_per_additional_gate ?? 0);
        $additionalGates = max(0, ((int) ($in['gate_count'] ?? 0)) - 1);

        // Labour is priced per linear foot on the total run. No HST on
        // labour per the project spec. Rate depends on 6' vs 8' fence.
        $linearLabour = (float) $geom['total_linear_ft'] * $labourRatePerFt;
        $additionalGateLabour = $additionalGates * $labourPerAdditionalGate;
        $labour = round($linearLabour + $additionalGateLabour, 2);

        return [
            'materials_subtotal' => $materialsSubtotal,
            'hst' => $hst,
            'labour_subtotal' => $labour,
            'grand_total' => round($materialsSubtotal + $hst + $labour, 2),
            'hst_rate' => FenceQuotation::HST_RATE,
            'labour_rate_per_ft' => $labourRatePerFt,
            'labour_rate_6ft_per_ft' => round($rate6, 2),
            'labour_rate_8ft_per_ft' => round($rate8, 2),
            'labour_per_additional_gate' => round($labourPerAdditionalGate, 2),
            'additional_gates_count' => $additionalGates,
        ];
    }
}
