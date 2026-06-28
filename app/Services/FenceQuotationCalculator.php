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
     *   - sides          : array<['length_ft' => float, 'already_have_post_left' => bool, 'already_have_post_right' => bool, 'gate_goes_here' => bool]>
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
     *     totals:      { materials_subtotal, hst, labour_subtotal, total, labour_hst, grand_total }
 *   }
 *
 * The output is deliberately JSON-serialisable so it can be stored on
 * `fence_quotations.calculation` verbatim and used by the PDF template
 * without further processing.
 */
class FenceQuotationCalculator
{
    /** Typical clear opening width credited off picket runs when a gate is on a side. */
    private const GATE_OPENING_FT = 4.0;

    /** Standard 4×4 post face width (inches). */
    private const POST_WIDTH_IN = 3.5;

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
                $sides[] = [
                    'length_ft' => round($len, 2),
                    'already_have_post_left' => filter_var($side['already_have_post_left'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'already_have_post_right' => filter_var($side['already_have_post_right'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'gate_goes_here' => filter_var($side['gate_goes_here'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        $height = (float) ($inputs['fence_height_ft'] ?? 6);
        if (!in_array((int) round($height), [6, 8], true)) {
            $height = 6.0;
        }

        $style = ($inputs['board_style'] ?? FenceQuotation::BOARD_STYLE_STANDARD) === FenceQuotation::BOARD_STYLE_OVERLAPPING
            ? FenceQuotation::BOARD_STYLE_OVERLAPPING
            : FenceQuotation::BOARD_STYLE_STANDARD;

        return [
            'sides' => $sides,
            'fence_height_ft' => round($height, 2),
            'board_style' => $style,
            'gate_count' => max(0, (int) ($inputs['gate_count'] ?? 0)),
        ];
    }

    /** ================================================================
     *   GEOMETRY — how many sections, posts, pickets, etc.
     *  ================================================================ */

    private function computeGeometry(array $in): array
    {
        $sideCount = count($in['sides']);
        $totalLinearFt = 0.0;
        $picketLinearFt = 0.0;
        $sectionsTotal = 0;
        $sidesBreakdown = [];
        $postsPerSide = [];
        $gatesRemaining = max(0, (int) $in['gate_count']);
        $gatesAssigned = 0;

        foreach ($in['sides'] as $idx => $side) {
            $layout = $this->computeSideLayout(
                (float) $side['length_ft'],
                (bool) ($side['already_have_post_left'] ?? false),
                (bool) ($side['already_have_post_right'] ?? false),
            );

            $sections = $layout['sections'];
            $posts = $layout['posts'];
            $gateHere = (bool) ($side['gate_goes_here'] ?? false) && $gatesRemaining > 0;
            if ($gateHere) {
                $posts = max(0, $posts - 1);
                $sections = max(0, $sections - 1);
                $gatesRemaining--;
                $gatesAssigned++;
            }

            $sideLengthFt = (float) $side['length_ft'];
            $picketLinearFt += max(0.0, $sideLengthFt - ($gateHere ? self::GATE_OPENING_FT : 0.0));

            $sidesBreakdown[] = [
                'index' => $idx + 1,
                'length_ft' => $sideLengthFt,
                'sections' => $sections,
                'posts' => $posts,
                'section_length_ft' => $layout['section_length_ft'],
                'section_lengths_ft' => $layout['section_lengths_ft'],
                'max_section_length_ft' => $layout['max_section_length_ft'],
                'already_have_post_left' => (bool) ($side['already_have_post_left'] ?? false),
                'already_have_post_right' => (bool) ($side['already_have_post_right'] ?? false),
                'gate_goes_here' => $gateHere,
            ];

            $totalLinearFt += $sideLengthFt;
            $sectionsTotal += $sections;
            $postsPerSide[] = $posts;
        }

        $structuralPosts = array_sum($postsPerSide);
        for ($i = 1; $i < $sideCount; $i++) {
            $prevRightExisting = (bool) ($in['sides'][$i - 1]['already_have_post_right'] ?? false);
            $currLeftExisting = (bool) ($in['sides'][$i]['already_have_post_left'] ?? false);
            if (! $prevRightExisting && ! $currLeftExisting) {
                $structuralPosts -= 1;
            }
        }
        $structuralPosts = max(0, $structuralPosts);

        $gatePosts = (int) $in['gate_count'];
        $postsBeforeWaste = $structuralPosts + $gatePosts;

        return [
            'total_linear_ft' => round($totalLinearFt, 2),
            'picket_linear_ft' => round($picketLinearFt, 2),
            'sections_total' => $sectionsTotal,
            'structural_posts' => $structuralPosts,
            'gate_posts' => $gatePosts,
            'gates_on_sides' => $gatesAssigned,
            'posts_before_waste' => $postsBeforeWaste,
            'sides_breakdown' => $sidesBreakdown,
        ];
    }

    /**
     * Inch-precise section/post layout for one fence side.
     *
     * Inner spans may not exceed max_post_spacing (default 8 ft / 96 in).
     * Total side length = sum(inner sections) + (included posts × 3.5 in).
     *
     * @return array{
     *   sections: int,
     *   posts: int,
     *   section_lengths_ft: list<float>,
     *   section_length_ft: float,
     *   max_section_length_ft: float,
     * }
     */
    public function computeSideLayout(float $lengthFt, bool $existingPostLeft, bool $existingPostRight): array
    {
        if ($lengthFt <= 0) {
            return [
                'sections' => 0,
                'posts' => 0,
                'section_lengths_ft' => [],
                'section_length_ft' => 0.0,
                'max_section_length_ft' => 0.0,
            ];
        }

        $totalIn = round($lengthFt * 12, 4);
        $maxSectionIn = round((float) $this->settings->max_post_spacing_ft * 12, 4);

        for ($sections = 1; $sections <= 500; $sections++) {
            $posts = $sections + 1
                - ($existingPostLeft ? 1 : 0)
                - ($existingPostRight ? 1 : 0);
            $posts = max(0, $posts);
            $postInches = $posts * self::POST_WIDTH_IN;
            $sectionBudget = $totalIn - $postInches;

            if ($sectionBudget <= 0) {
                continue;
            }

            if ($sectionBudget <= ($sections * $maxSectionIn) + 1e-6) {
                $sectionLengthsIn = $this->distributeSectionLengthsIn($sectionBudget, $sections, $maxSectionIn);
                $sectionLengthsFt = array_map(
                    static fn (float $inches): float => round($inches / 12, 3),
                    $sectionLengthsIn
                );

                return [
                    'sections' => $sections,
                    'posts' => $posts,
                    'section_lengths_ft' => $sectionLengthsFt,
                    'section_length_ft' => $sections > 0
                        ? round(array_sum($sectionLengthsFt) / $sections, 3)
                        : 0.0,
                    'max_section_length_ft' => $sectionLengthsFt !== []
                        ? max($sectionLengthsFt)
                        : 0.0,
                ];
            }
        }

        return [
            'sections' => 1,
            'posts' => max(0, 2 - ($existingPostLeft ? 1 : 0) - ($existingPostRight ? 1 : 0)),
            'section_lengths_ft' => [round($lengthFt, 3)],
            'section_length_ft' => round($lengthFt, 3),
            'max_section_length_ft' => round($lengthFt, 3),
        ];
    }

    /**
     * Fill full max-width spans first; remainder becomes the final partial section.
     *
     * @return list<float> section lengths in inches
     */
    private function distributeSectionLengthsIn(float $budgetIn, int $sections, float $maxSectionIn): array
    {
        $lengths = [];
        $remaining = $budgetIn;

        for ($i = 0; $i < $sections; $i++) {
            if ($i === $sections - 1) {
                $lengths[] = round($remaining, 4);

                continue;
            }

            $sectionsAfter = $sections - $i - 1;
            $minReserve = $sectionsAfter * 0.01;
            $assign = min($maxSectionIn, max(0.01, $remaining - $minReserve));
            $lengths[] = round($assign, 4);
            $remaining -= $assign;
        }

        return $lengths;
    }

    /** ================================================================
     *   MATERIALS — one row per catalogue entry.
     *  ================================================================ */

    private function computeMaterials(array $in, array $geom): array
    {
        $height = (int) round((float) $in['fence_height_ft']);
        $settings = $this->settings;
        $totalFt = (float) ($geom['picket_linear_ft'] ?? $geom['total_linear_ft']);
        $sectionsTotal = (int) $geom['sections_total'];
        $postsBeforeWaste = (int) $geom['posts_before_waste'];

        // -------- POSTS --------------------------------------------------
        // Waste margin: +1 post per project.
        $postsTotal = $postsBeforeWaste + ($postsBeforeWaste > 0 ? 1 : 0);
        $postKey = $height === 8 ? FenceMaterial::KEY_POST_12 : FenceMaterial::KEY_POST_10;

        // -------- PICKETS (fence boards along the run) -------------------
        // Effective per-board width depends on style:
        //   standard:    board_width    (0 gap)
        //   overlapping: board_width − overlap (each next board hides part
        //                                        of the previous one)
        $boardWidth = (float) $settings->board_width_in;
        $overlap = (float) $settings->overlapping_in;

        if ($in['board_style'] === FenceQuotation::BOARD_STYLE_OVERLAPPING) {
            $effectiveWidth = max($boardWidth - $overlap, 0.01);
        } else {
            $effectiveWidth = $boardWidth;
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
            $sectionLengths = $side['section_lengths_ft'] ?? [];
            if ($sectionLengths === []) {
                $secLen = (float) ($side['section_length_ft'] ?? 0);
                $secs = (int) ($side['sections'] ?? 0);
                if ($secLen <= 6.0 + 1e-6) {
                    $top6 += $secs;
                } else {
                    $top8 += $secs;
                }

                continue;
            }

            foreach ($sectionLengths as $secLen) {
                if ((float) $secLen <= 6.0 + 1e-6) {
                    $top6++;
                } else {
                    $top8++;
                }
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
        $nailsBoxSize = (int) ($nailsMat->effectiveQuantityPerBox() ?? 0);
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
            $this->postRowNotes($geom, $settings, $postsBeforeWaste)
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
            ? $mat->effectivePriceCad()
            : round($unitPrice, 2);

        $subtotal = round($quantity * $displayUnitPrice, 2);

        return [
            'key' => $key,
            'name' => $mat ? $mat->effectiveName() : $key,
            'long_name' => $mat?->effectiveLongName(),
            'model' => $mat?->effectiveModel(),
            'store_sku' => $mat?->effectiveStoreSku(),
            'url' => $mat?->effectiveUrl(),
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

    private function postRowNotes(array $geom, FenceFormulaSetting $settings, int $postsBeforeWaste): string
    {
        $note = "Structural: {$postsBeforeWaste}, +1 waste. Max {$settings->max_post_spacing_ft}' spacing.";
        $gatesOnSides = (int) ($geom['gates_on_sides'] ?? 0);
        if ($gatesOnSides > 0) {
            $note .= ' Gate on side'
                .($gatesOnSides === 1 ? '' : 's')
                .": {$gatesOnSides} (1 fence post per gate credited to gate jambs).";
        }

        return $note;
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

        $linearLabour = (float) $geom['total_linear_ft'] * $labourRatePerFt;
        $additionalGateLabour = $additionalGates * $labourPerAdditionalGate;
        $labour = round($linearLabour + $additionalGateLabour, 2);
        $labourHst = round($labour * FenceQuotation::HST_RATE, 2);
        $total = round($materialsSubtotal + $hst + $labour, 2);

        return [
            'materials_subtotal' => $materialsSubtotal,
            'hst' => $hst,
            'labour_subtotal' => $labour,
            'total' => $total,
            'labour_hst' => $labourHst,
            'grand_total' => round($total + $labourHst, 2),
            'hst_rate' => FenceQuotation::HST_RATE,
            'labour_rate_per_ft' => $labourRatePerFt,
            'labour_rate_6ft_per_ft' => round($rate6, 2),
            'labour_rate_8ft_per_ft' => round($rate8, 2),
            'labour_per_additional_gate' => round($labourPerAdditionalGate, 2),
            'additional_gates_count' => $additionalGates,
        ];
    }
}
