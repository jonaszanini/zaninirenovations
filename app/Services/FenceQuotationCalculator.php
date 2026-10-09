<?php

namespace App\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Support\CatalogMaterialDelivery;
use App\Support\FenceGateSpec;
use App\Support\FenceSideFlags;

/**
 * FenceQuotationCalculator — the structural math engine behind the
 * Fence quotation module.
 *
 * Inputs (from the UI):
 *   - sides          : array of runs with length, existing posts, gate flags,
 *                      optional connected_to_previous and gate_preset
 *   - fence_height_ft: 6 or 8
 *   - board_style    : 'standard' | 'overlapping'
 *   - gate_count     : int (>= 0)
 *   - extra_gate_preset: size/type for gates not placed on a run
 *
 * Output (`calculate()`):
 *   {
 *     inputs:      echo of the normalised inputs,
 *     geometry:    { total_linear_ft, sections_total, sides_breakdown },
 *     materials:   [ { key, name, long_name, model, store_sku, quantity, unit, unit_price, subtotal, notes, url } ],
     *     totals:      { materials_subtotal, hst, labour_subtotal, total, labour_hst, grand_total, existing_post_credit }
 *   }
 *
 * The output is JSON-serialisable so it can be stored on
 * `fence_quotations.calculation` and reused on edit without a PDF of its own.
 */
class FenceQuotationCalculator
{

    /** Standard 4×4 post face width (inches). */
    private const POST_WIDTH_IN_4X4 = 3.5;

    /** Standard 6×6 post face width (inches). */
    private const POST_WIDTH_IN_6X6 = 5.5;

    private FenceFormulaSetting $settings;

    /** @var \Illuminate\Support\Collection<string, FenceMaterial> */
    private $catalogue;

    public function __construct(?FenceFormulaSetting $settings = null, $catalogue = null)
    {
        $this->settings = $settings ?: FenceFormulaSetting::current();
        $this->catalogue = $catalogue ?: FenceMaterial::catalogueByKey();
    }

    /**
     * Unit prices from a saved calculation snapshot, keyed by material key.
     *
     * @param  array<string, mixed>|null  $calculation
     * @return array<string, float>
     */
    public static function frozenUnitPricesFromCalculation(?array $calculation): array
    {
        $out = [];
        foreach ($calculation['materials'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[$key] = round((float) ($row['unit_price'] ?? 0), 2);
        }

        return $out;
    }

    /**
     * Compare live catalogue unit prices to a saved snapshot.
     * The dollar delta is unit-price change × saved quantity only — quantity
     * or formula changes are not part of the banner amount.
     *
     * @param  array<string, mixed>  $live
     * @param  array<string, mixed>|null  $saved
     * @return array{changed: bool, delta: float, saved_materials: float, live_materials: float}
     */
    public static function cataloguePriceDelta(array $live, ?array $saved): array
    {
        $liveMat = (float) ($live['totals']['materials_subtotal'] ?? 0);
        $savedMat = (float) ($saved['totals']['materials_subtotal'] ?? 0);

        $liveByKey = [];
        foreach ($live['materials'] ?? [] as $row) {
            if (is_array($row) && ($row['key'] ?? '') !== '') {
                $liveByKey[(string) $row['key']] = $row;
            }
        }

        $changed = false;
        $delta = 0.0;
        foreach ($saved['materials'] ?? [] as $row) {
            if (! is_array($row) || ($row['key'] ?? '') === '') {
                continue;
            }
            $key = (string) $row['key'];
            if (! array_key_exists($key, $liveByKey)) {
                continue;
            }
            $savedPrice = round((float) ($row['unit_price'] ?? 0), 2);
            $livePrice = round((float) ($liveByKey[$key]['unit_price'] ?? 0), 2);
            if (abs($livePrice - $savedPrice) <= 0.009) {
                continue;
            }
            $changed = true;
            $savedQty = (float) ($row['quantity'] ?? 0);
            $delta += ($livePrice - $savedPrice) * $savedQty;
        }

        return [
            'changed' => $changed,
            'delta' => round($delta, 2),
            'saved_materials' => $savedMat,
            'live_materials' => $liveMat,
        ];
    }

    /** Banner suffix, e.g. " (unit prices -CA$5.00)". Empty when the delta is a wash. */
    public static function formatCatalogueDeltaLabel(float $delta): string
    {
        if (abs($delta) < 0.01) {
            return '';
        }

        $sign = $delta < 0 ? '-' : '+';

        return ' (unit prices '.$sign.\App\Models\ClientProject::formatMoney(abs($delta)).')';
    }

    public function calculate(array $inputs, ?array $frozenUnitPrices = null): array
    {
        $normalised = $this->normaliseInputs($inputs);
        $geometry = $this->computeGeometry($normalised);
        $materials = $this->computeMaterials($normalised, $geometry);
        if ($frozenUnitPrices) {
            $materials = $this->applyFrozenUnitPrices($materials, $frozenUnitPrices);
        }
        $materials = $this->withCatalogDeliveries($materials, $frozenUnitPrices);
        $totals = $this->computeTotals($materials, $geometry, $normalised);

        return [
            'inputs' => $normalised,
            'geometry' => $geometry,
            'materials' => $materials,
            'totals' => $totals,
            'price_source' => $frozenUnitPrices ? 'frozen' : 'catalogue',
        ];
    }

    /** ================================================================
     *   INPUT NORMALISATION
     *  ================================================================ */

    private function normaliseInputs(array $inputs): array
    {
        $rawSides = $inputs['sides'] ?? [];
        $sides = [];
        $index = 0;
        foreach ($rawSides as $side) {
            if (! is_array($side)) {
                continue;
            }
            $len = (float) ($side['length_ft'] ?? 0);
            if ($len <= 0) {
                continue;
            }
            $gateSpec = FenceGateSpec::fromSide($side);
            $connected = FenceSideFlags::connectedToPreviousFromSide($side, $index);
            $sides[] = [
                'length_ft' => round($len, 2),
                'already_have_post_left' => FenceSideFlags::isOn($side['already_have_post_left'] ?? false),
                'already_have_post_right' => FenceSideFlags::isOn($side['already_have_post_right'] ?? false),
                'gate_goes_here' => FenceSideFlags::isOn($side['gate_goes_here'] ?? false),
                'connected_to_previous' => $connected,
                'gate_preset' => $gateSpec->key,
                'gate_width_ft' => $gateSpec->widthFt,
                'gate_type' => $gateSpec->type,
            ];
            $index++;
        }

        $height = (float) ($inputs['fence_height_ft'] ?? 6);
        if (!in_array((int) round($height), [6, 8], true)) {
            $height = 6.0;
        }

        $style = ($inputs['board_style'] ?? FenceQuotation::BOARD_STYLE_STANDARD) === FenceQuotation::BOARD_STYLE_OVERLAPPING
            ? FenceQuotation::BOARD_STYLE_OVERLAPPING
            : FenceQuotation::BOARD_STYLE_STANDARD;

        $gateCount = max(0, (int) ($inputs['gate_count'] ?? 0));
        $gatesMarkedOnSides = 0;
        foreach ($sides as $side) {
            if ($side['gate_goes_here']) {
                $gatesMarkedOnSides++;
            }
        }

        $defaultLabour6 = (float) $this->settings->labour_rate_per_ft;
        $defaultLabour8 = (float) ($this->settings->labour_rate_8ft_per_ft ?? $defaultLabour6);

        return [
            'sides' => $sides,
            'fence_height_ft' => round($height, 2),
            'board_style' => $style,
            'gate_count' => max($gateCount, $gatesMarkedOnSides),
            'labour_rate_per_ft' => round(max(0, (float) ($inputs['labour_rate_per_ft'] ?? $defaultLabour6)), 2),
            'labour_rate_8ft_per_ft' => round(max(0, (float) ($inputs['labour_rate_8ft_per_ft'] ?? $defaultLabour8)), 2),
            'post_size' => $this->normalisePostSize($inputs['post_size'] ?? null),
            'extra_gate_preset' => FenceGateSpec::fromKey($inputs['extra_gate_preset'] ?? null)->key,
            'include_labour_hst' => array_key_exists('include_labour_hst', $inputs)
                ? filter_var($inputs['include_labour_hst'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'existing_post_labour_credit' => $this->normaliseExistingPostLabourCredit($inputs),
        ];
    }

    private function normaliseExistingPostLabourCredit(array $inputs): float
    {
        $default = $this->settings->existingPostLabourCredit();
        if (! array_key_exists('existing_post_labour_credit', $inputs) || $inputs['existing_post_labour_credit'] === null || $inputs['existing_post_labour_credit'] === '') {
            return round(max(0, $default), 2);
        }

        return round(max(0, (float) $inputs['existing_post_labour_credit']), 2);
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
        $postWidthIn = $this->postWidthInFor($in);

        $gateRows = [];

        foreach ($in['sides'] as $idx => $side) {
            $sideLengthFt = (float) $side['length_ft'];
            $gateHere = (bool) ($side['gate_goes_here'] ?? false) && $gatesRemaining > 0;
            $gateSpec = $gateHere ? FenceGateSpec::fromSide($side) : null;
            $openingFt = $gateSpec?->widthFt ?? 0.0;
            if ($gateHere && $openingFt > $sideLengthFt) {
                $openingFt = $sideLengthFt;
            }
            $fenceLengthFt = $gateHere
                ? max(0.0, $sideLengthFt - $openingFt)
                : $sideLengthFt;

            $existingLeft = (bool) ($side['already_have_post_left'] ?? false);
            $existingRight = (bool) ($side['already_have_post_right'] ?? false);
            $connectedToPrevious = FenceSideFlags::connectedToPreviousFromSide($side, $idx);

            // Gate-side layout: gate consumes its opening at the end of the run.
            //   [existing left?] — fence run (length − opening) — hinge — opening — far post
            // "Existing post on right" is the far/latch post after the gate, not the hinge.
            $layoutExistingRight = $gateHere ? false : $existingRight;

            $layout = $this->computeSideLayout(
                $fenceLengthFt,
                $existingLeft,
                $layoutExistingRight,
                $postWidthIn,
            );

            $sections = $layout['sections'];
            $posts = $layout['posts'];
            if ($gateHere && $gateSpec) {
                if ($fenceLengthFt <= 0 && ! $existingLeft) {
                    // Gate-only run: layout(0) returns 0 posts. The hinge is missing
                    // unless the left end already has a post.
                    $posts += 1;
                }
                $farPosts = $gateSpec->isDouble() ? max(0, $gateSpec->hingePostCount() - 1) : 1;
                if (! $existingRight) {
                    $posts += $farPosts;
                }
                $gatesRemaining--;
                $gatesAssigned++;
                $gateRows[] = [
                    'spec' => $gateSpec,
                    'width_ft' => $openingFt,
                ];
            }

            $picketLinearFt += max(0.0, $sideLengthFt - ($gateHere ? $openingFt : 0.0));

            $sidesBreakdown[] = [
                'index' => $idx + 1,
                'length_ft' => $sideLengthFt,
                'fence_length_ft' => round($fenceLengthFt, 2),
                'sections' => $sections,
                'posts' => $posts,
                'section_length_ft' => $layout['section_length_ft'],
                'section_lengths_ft' => $layout['section_lengths_ft'],
                'max_section_length_ft' => $layout['max_section_length_ft'],
                'already_have_post_left' => $existingLeft,
                'already_have_post_right' => $existingRight,
                'connected_to_previous' => $connectedToPrevious,
                'gate_goes_here' => $gateHere,
                'gate_opening_ft' => $gateHere ? $openingFt : null,
                'gate_preset' => $gateHere ? $gateSpec?->key : null,
                'gate_type' => $gateHere ? $gateSpec?->type : null,
            ];

            $totalLinearFt += $sideLengthFt;
            $sectionsTotal += $sections;
            $postsPerSide[] = $posts;
        }

        $structuralPosts = array_sum($postsPerSide);
        for ($i = 1; $i < $sideCount; $i++) {
            $connected = FenceSideFlags::connectedToPreviousFromSide($in['sides'][$i], $i);
            if (! $connected) {
                continue;
            }
            $prevRightExisting = (bool) ($in['sides'][$i - 1]['already_have_post_right'] ?? false);
            $currLeftExisting = (bool) ($in['sides'][$i]['already_have_post_left'] ?? false);
            // Connected runs always share the corner post. Deduct unless
            // both sides already excluded that same existing corner.
            if (! ($prevRightExisting && $currLeftExisting)) {
                $structuralPosts -= 1;
            }
        }
        $structuralPosts = max(0, $structuralPosts);

        $extraGateCount = max(0, (int) $in['gate_count'] - $gatesAssigned);
        $extraSpec = FenceGateSpec::fromKey($in['extra_gate_preset'] ?? FenceGateSpec::DEFAULT_KEY);
        $gatePosts = 0;
        for ($i = 0; $i < $extraGateCount; $i++) {
            $gatePosts += $extraSpec->standalonePostCount();
            $gateRows[] = [
                'spec' => $extraSpec,
                'width_ft' => $extraSpec->widthFt,
            ];
        }
        $postsBeforeWaste = $structuralPosts + $gatePosts;

        return [
            'total_linear_ft' => round($totalLinearFt, 2),
            'picket_linear_ft' => round($picketLinearFt, 2),
            'run_count' => $sideCount,
            'sections_total' => $sectionsTotal,
            'panels_total' => $sectionsTotal,
            'structural_posts' => $structuralPosts,
            'gate_posts' => $gatePosts,
            'gates_on_sides' => $gatesAssigned,
            'posts_before_waste' => $postsBeforeWaste,
            'sides_breakdown' => $sidesBreakdown,
            'gates' => array_map(static function (array $row): array {
                /** @var FenceGateSpec $spec */
                $spec = $row['spec'];

                return [
                    'preset' => $spec->key,
                    'width_ft' => (float) $row['width_ft'],
                    'type' => $spec->type,
                    'label' => $spec->label,
                    'hardware_sets' => $spec->hardwareSets(),
                ];
            }, $gateRows),
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
    public function computeSideLayout(
        float $lengthFt,
        bool $existingPostLeft,
        bool $existingPostRight,
        ?float $postWidthIn = null,
    ): array {
        $postWidthIn = $postWidthIn ?? self::POST_WIDTH_IN_4X4;

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
            $postInches = $posts * $postWidthIn;
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
     * Split the inner span budget equally across all sections (each ≤ max width).
     *
     * @return list<float> section lengths in inches
     */
    private function distributeSectionLengthsIn(float $budgetIn, int $sections, float $maxSectionIn): array
    {
        if ($sections <= 0) {
            return [];
        }

        $equalLength = $budgetIn / $sections;
        $lengths = array_fill(0, $sections, round($equalLength, 4));

        // Absorb rounding drift on the final section so the total matches the budget.
        $drift = round($budgetIn - array_sum($lengths), 4);
        if ($drift !== 0.0) {
            $lengths[$sections - 1] = round($lengths[$sections - 1] + $drift, 4);
        }

        return $lengths;
    }

    private function normalisePostSize(mixed $value): string
    {
        $size = is_string($value) ? trim($value) : '';

        return in_array($size, [FenceQuotation::POST_SIZE_4X4, FenceQuotation::POST_SIZE_6X6], true)
            ? $size
            : FenceQuotation::POST_SIZE_4X4;
    }

    private function postWidthInFor(array $in): float
    {
        return ($in['post_size'] ?? FenceQuotation::POST_SIZE_4X4) === FenceQuotation::POST_SIZE_6X6
            ? self::POST_WIDTH_IN_6X6
            : self::POST_WIDTH_IN_4X4;
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

        $postSize = (string) ($in['post_size'] ?? FenceQuotation::POST_SIZE_4X4);
        $wastePosts = $settings->wasteExtraPosts();
        $wastePickets = $settings->wastePicketsPerSection();
        $wasteRails = $settings->wasteExtraRails();
        $wasteBrackets = $settings->wasteExtraBrackets();
        $wasteCaps = $settings->wasteExtraCaps();
        $concreteWasteDivisor = $settings->wasteConcretePostsPerExtraBag();

        // -------- POSTS --------------------------------------------------
        $postsTotal = $postsBeforeWaste + ($postsBeforeWaste > 0 ? $wastePosts : 0);
        $postKey = FenceMaterial::postKeyFor($height, $postSize);

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

        $gateBoardTotal = 0;
        $gateBoardNotes = [];
        foreach ($geom['gates'] ?? [] as $gate) {
            $widthFt = (float) ($gate['width_ft'] ?? 0);
            $boards = ($effectiveWidth > 0 && $widthFt > 0)
                ? (int) ceil(($widthFt * 12.0) / $effectiveWidth)
                : 0;
            $gateBoardTotal += $boards;
            if ($boards > 0) {
                $gateBoardNotes[] = $boards.' for '.($gate['label'] ?? 'gate');
            }
        }

        // Waste: +N boards per panel (span), plus explicit gate-leaf boards.
        $picketsTotal = $picketsBeforeWaste + ($sectionsTotal * $wastePickets) + $gateBoardTotal;
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
        $gateLeafCount = 0;
        foreach ($geom['gates'] ?? [] as $gate) {
            $gateLeafCount += (($gate['type'] ?? FenceGateSpec::TYPE_SINGLE) === FenceGateSpec::TYPE_DOUBLE) ? 2 : 1;
        }
        if ($gateLeafCount === 0) {
            $gateLeafCount = (int) $in['gate_count'];
        }
        $gateRails = $gateLeafCount * (int) $settings->gate_rails;
        $railsBeforeWaste = $structuralRails + $gateRails;
        $railsTotal = $railsBeforeWaste + ($railsBeforeWaste > 0 ? $wasteRails : 0);

        // -------- CONCRETE ----------------------------------------------
        $bagsPerPost = $settings->concretePerPostForHeight($height, $postSize);
        $concreteStructural = (float) $postsBeforeWaste * $bagsPerPost;
        $concreteWaste = $postsBeforeWaste > 0
            ? (int) ceil($postsBeforeWaste / $concreteWasteDivisor)
            : 0;
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
        $bracketsTotal = $bracketsBeforeWaste + ($bracketsBeforeWaste > 0 ? $wasteBrackets : 0);

        // -------- POST CAPS ---------------------------------------------
        $postCapsTotal = $postsBeforeWaste + ($postsBeforeWaste > 0 ? $wasteCaps : 0);
        $postCapKey = FenceMaterial::postCapKeyFor($postSize);

        // -------- GATE HARDWARE ------------------------------------------
        $gateHardware = 0;
        foreach ($geom['gates'] ?? [] as $gate) {
            $gateHardware += (int) ($gate['hardware_sets'] ?? 1);
        }
        if ($gateHardware === 0) {
            $gateHardware = (int) $in['gate_count'];
        }

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
            $this->picketRowNotes($picketsBeforeWaste, $sectionsTotal, $wastePickets, $gateBoardTotal, $gateBoardNotes)
        );

        // Top boards — only add rows for sizes actually used.
        if ($top6 > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_BOARD_6,
                $top6,
                'ea',
                "Top board (clear span ≤ 6')",
                // If picket key was also 6', merge into that row instead
                // for a cleaner quote. We handle the merge after the loop.
            );
        }
        if ($top8 > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_BOARD_8,
                $top8,
                'ea',
                "Top board (clear span > 6')"
            );
        }

        // Rails
        $rows[] = $this->row(
            FenceMaterial::KEY_RAIL,
            $railsTotal,
            'ea',
            "Fence: {$structuralRails} ({$railsPerSection}/panel), gates: {$gateRails}, +{$wasteRails} waste"
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
            "2 × {$structuralRails} fence rails + {$wasteBrackets} waste"
        );

        // Post caps
        $rows[] = $this->row(
            $postCapKey,
            $postCapsTotal,
            'ea',
            "1 per post + {$wasteCaps} waste"
        );

        // Gate hardware
        if ($gateHardware > 0) {
            $rows[] = $this->row(
                FenceMaterial::KEY_GATE_HARDWARE,
                $gateHardware,
                'set',
                $gateHardware === 1 ? '1 set per gate leaf' : $gateHardware.' sets (1 per gate leaf)'
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

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, float>|null  $frozenUnitPrices
     * @return list<array<string, mixed>>
     */
    private function withCatalogDeliveries(array $rows, ?array $frozenUnitPrices = null): array
    {
        return CatalogMaterialDelivery::appendOncePerVendor($rows, function (array $row) use ($frozenUnitPrices): array {
            $key = (string) ($row['key'] ?? '');
            $frozenKey = $key !== '' ? $key.'_delivery' : '';
            if ($frozenUnitPrices && $frozenKey !== '' && array_key_exists($frozenKey, $frozenUnitPrices)) {
                $delivery = (float) $frozenUnitPrices[$frozenKey];
            } else {
                /** @var FenceMaterial|null $mat */
                $mat = $this->catalogue->get($key);
                $delivery = $mat ? $mat->effectiveDeliveryCad() : 0.0;
            }

            /** @var FenceMaterial|null $mat */
            $mat = $this->catalogue->get($key);
            $vendorKey = $this->deliveryVendorKey($mat);
            $vendorName = $this->deliveryVendorName($mat);

            return [$delivery, $vendorName, $vendorKey];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, float>  $frozenUnitPrices
     * @return list<array<string, mixed>>
     */
    private function applyFrozenUnitPrices(array $rows, array $frozenUnitPrices): array
    {
        foreach ($rows as &$row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || ! array_key_exists($key, $frozenUnitPrices)) {
                continue;
            }
            $unit = round((float) $frozenUnitPrices[$key], 2);
            $qty = (float) ($row['quantity'] ?? 0);
            $row['unit_price'] = $unit;
            $row['subtotal'] = round($qty * $unit, 2);
            $row['price_frozen'] = true;
        }
        unset($row);

        return $rows;
    }

    private function deliveryVendorKey(?FenceMaterial $mat): string
    {
        $catalog = $mat?->material;
        if ($catalog === null) {
            return 'unlinked';
        }
        $vendorId = (int) ($catalog->vendor_id ?? 0);
        if ($vendorId > 0) {
            return 'vendor:'.$vendorId;
        }
        $store = $catalog->storeLinkVendor();
        if ($store) {
            return 'store:'.$store;
        }

        return 'catalog:'.$catalog->id;
    }

    private function deliveryVendorName(?FenceMaterial $mat): string
    {
        $catalog = $mat?->material;
        if ($catalog === null) {
            return 'Materials';
        }
        $name = trim((string) ($catalog->vendorName() ?? ''));
        if ($name !== '') {
            return $name;
        }
        $store = $catalog->storeLinkVendor();
        if ($store === 'home_depot') {
            return 'Home Depot';
        }
        if ($store === 'amazon') {
            return 'Amazon';
        }

        return $catalog->displayName();
    }

    /**
     * @param  list<string>  $gateBoardNotes
     */
    private function picketRowNotes(
        int $picketsBeforeWaste,
        int $sectionsTotal,
        int $wastePickets,
        int $gateBoardTotal,
        array $gateBoardNotes,
    ): string {
        $note = "Pickets: {$picketsBeforeWaste}, +{$wastePickets} per panel ({$sectionsTotal}) waste";
        if ($gateBoardTotal > 0) {
            $note .= '; gate boards: '.$gateBoardTotal;
            if ($gateBoardNotes !== []) {
                $note .= ' ('.implode(', ', $gateBoardNotes).')';
            }
        }

        return $note;
    }

    private function postRowNotes(array $geom, FenceFormulaSetting $settings, int $postsBeforeWaste): string
    {
        $waste = $settings->wasteExtraPosts();
        $note = "Structural: {$postsBeforeWaste}, +{$waste} waste. Clear span ≤ {$settings->max_post_spacing_ft}' (posts sit outside this span).";
        $gatesOnSides = (int) ($geom['gates_on_sides'] ?? 0);
        if ($gatesOnSides > 0) {
            $note .= ' Gate on run'
                .($gatesOnSides === 1 ? '' : 's')
                .": {$gatesOnSides} (hinge and far posts included in structural count).";
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
        $grossLabour = $linearLabour + $additionalGateLabour;
        $creditPerPost = (float) ($in['existing_post_labour_credit'] ?? $this->settings->existingPostLabourCredit());
        $creditCount = $this->countPostsRemovedByExistingTicks($in, $geom);
        $rawCredit = round($creditCount * $creditPerPost, 2);
        $creditAmount = round(min($rawCredit, max(0, $grossLabour)), 2);
        $labour = round(max(0, $grossLabour - $creditAmount), 2);
        $includeLabourHst = (bool) ($in['include_labour_hst'] ?? true);
        $labourHst = $includeLabourHst ? round($labour * FenceQuotation::HST_RATE, 2) : 0.0;
        $total = round($materialsSubtotal + $hst + $labour, 2);

        return [
            'materials_subtotal' => $materialsSubtotal,
            'hst' => $hst,
            'labour_subtotal' => $labour,
            'total' => $total,
            'labour_hst' => $labourHst,
            'include_labour_hst' => $includeLabourHst,
            'grand_total' => round($total + $labourHst, 2),
            'hst_rate' => FenceQuotation::HST_RATE,
            'labour_rate_per_ft' => $labourRatePerFt,
            'labour_rate_6ft_per_ft' => round($rate6, 2),
            'labour_rate_8ft_per_ft' => round($rate8, 2),
            'labour_per_additional_gate' => round($labourPerAdditionalGate, 2),
            'additional_gates_count' => $additionalGates,
            'labour_before_credit' => round($grossLabour, 2),
            'existing_post_credit' => [
                'count' => $creditCount,
                'amount' => $creditAmount,
                'rate' => round($creditPerPost, 2),
            ],
        ];
    }

    /**
     * How many posts the left/right existing-post ticks actually removed.
     * Shared connected-run corners credit once — only when a tick removes a post.
     */
    private function countPostsRemovedByExistingTicks(array $in, array $geom): int
    {
        $hasTick = false;
        foreach ($in['sides'] ?? [] as $side) {
            if (! empty($side['already_have_post_left']) || ! empty($side['already_have_post_right'])) {
                $hasTick = true;
                break;
            }
        }
        if (! $hasTick) {
            return 0;
        }

        $cleared = $in;
        $cleared['sides'] = array_map(static function (array $side): array {
            $side['already_have_post_left'] = false;
            $side['already_have_post_right'] = false;

            return $side;
        }, $in['sides']);

        $without = $this->computeGeometry($cleared);

        return max(0, (int) $without['structural_posts'] - (int) $geom['structural_posts']);
    }
}
