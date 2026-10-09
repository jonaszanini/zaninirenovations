<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FenceQuotation — a saved fence calculator result.
 *
 * Stores both the raw inputs (so the quote can be re-opened and edited)
 * and a snapshot of the calculated breakdown (so older quotes keep the
 * same totals even after material prices change).
 *
 * The module deliberately has NO PDF of its own — the client-facing
 * document is always the project's main Quotation PDF. Linking a
 * fence quote to a project appends a single line item (description +
 * pre-tax amount) to that project's ClientProjectQuotation, which
 * then renders/sends through the existing flow.
 */
class FenceQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
    use \App\Models\Concerns\HasPdfFoldVersion;
    use \App\Models\Concerns\HasReplyToAcceptChoice;

    public const HST_RATE = 0.13;
    public const BOARD_STYLE_STANDARD = 'standard';
    public const BOARD_STYLE_OVERLAPPING = 'overlapping';

    public const POST_SIZE_4X4 = '4x4';

    public const POST_SIZE_6X6 = '6x6';

    public const MAX_RUNS = 8;

    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'fence_height_ft',
        'post_size',
        'board_style',
        'gate_count',
        'labour_rate_per_ft',
        'labour_rate_8ft_per_ft',
        'existing_post_labour_credit',
        'sides',
        'calculation',
        'materials_subtotal',
        'hst',
        'labour_total',
        'labour_hst',
        'include_labour_hst',
        'grand_total',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
        'include_debris_removal',
        'debris_removal_amount',
        'additional_costs',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'sides' => 'array',
        'calculation' => 'array',
        'fence_height_ft' => 'decimal:2',
        'gate_count' => 'integer',
        'labour_rate_per_ft' => 'decimal:2',
        'labour_rate_8ft_per_ft' => 'decimal:2',
        'existing_post_labour_credit' => 'decimal:2',
        'materials_subtotal' => 'decimal:2',
        'hst' => 'decimal:2',
        'labour_total' => 'decimal:2',
        'labour_hst' => 'decimal:2',
        'include_labour_hst' => 'boolean',
        'grand_total' => 'decimal:2',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'include_commute' => 'boolean',
        'commute_trip_percent' => 'decimal:2',
        'commute_estimated_trips' => 'integer',
        'commute_round_trip_cost' => 'decimal:2',
        'commute_amount' => 'decimal:2',
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
        'additional_costs' => 'array',
        'version' => 'integer',
        'linked_at' => 'datetime',
    ];

    /** Optional: the project this quote was linked into (if any). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    /**
     * Short contractor-facing line for the project Quotation PDF.
     * Example: "6 ft standard board fence, 4×4 posts in concrete, 58 linear ft (3 runs), one 4 ft single gate"
     */
    public function summaryDescription(): string
    {
        $sides = collect($this->sides ?? [])->values();
        $totalFt = $sides->sum(fn ($s) => (float) ($s['length_ft'] ?? 0));
        $runCount = $sides->filter(fn ($s) => (float) ($s['length_ft'] ?? 0) > 0)->count();
        $height = (int) round((float) $this->fence_height_ft);
        $style = $this->board_style === self::BOARD_STYLE_OVERLAPPING
            ? 'overlapping board'
            : 'standard board';
        $lf = rtrim(rtrim(number_format($totalFt, 2), '0'), '.');
        $runsPart = $runCount.' '.($runCount === 1 ? 'run' : 'runs');

        $gateLabels = [];
        foreach ($sides as $side) {
            if (! is_array($side) || empty($side['gate_goes_here'])) {
                continue;
            }
            $gateLabels[] = \App\Support\FenceGateSpec::fromSide($side)->shortLabel();
        }
        $placed = count($gateLabels);
        $stated = max(0, (int) $this->gate_count);
        $extra = max(0, $stated - $placed);
        $extraPreset = is_array($this->calculation)
            ? ($this->calculation['inputs']['extra_gate_preset'] ?? \App\Support\FenceGateSpec::DEFAULT_KEY)
            : \App\Support\FenceGateSpec::DEFAULT_KEY;
        for ($i = 0; $i < $extra; $i++) {
            $gateLabels[] = \App\Support\FenceGateSpec::fromKey($extraPreset)->shortLabel();
        }

        $gatesPart = '';
        if ($gateLabels !== []) {
            if (count($gateLabels) === 1) {
                $gatesPart = ', one '.$gateLabels[0].' gate';
            } else {
                $gatesPart = ', '.count($gateLabels).' gates ('.implode(', ', $gateLabels).')';
            }
        }

        return sprintf(
            '%d ft %s fence, %s posts in concrete, %s linear ft (%s)%s',
            $height,
            $style,
            $this->postSizeLabel(),
            $lf,
            $runsPart,
            $gatesPart
        );
    }

    /**
     * Line items for the project Quotation PDF. When labour HST is excluded,
     * materials and labour are split so only materials remain taxable.
     *
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    public function projectQuotationLineItems(): array
    {
        $summary = $this->summaryDescription();
        $materials = round((float) $this->materials_subtotal, 2);
        $labour = round((float) $this->labour_total, 2);

        if ($this->include_labour_hst !== false) {
            return [[
                'description' => $summary,
                'amount' => round($materials + $labour, 2),
                'apply_hst' => true,
            ]];
        }

        $items = [];
        if ($materials > 0.00001) {
            $items[] = [
                'description' => $summary.' — materials',
                'amount' => $materials,
                'apply_hst' => true,
            ];
        }
        if ($labour > 0.00001) {
            $items[] = [
                'description' => $summary.' — labour',
                'amount' => $labour,
                'apply_hst' => false,
            ];
        }

        return $items !== [] ? $items : [[
            'description' => $summary,
            'amount' => 0.0,
            'apply_hst' => true,
        ]];
    }

    /** Pre-HST labour only (excludes materials, commute, and debris). */
    public function labourPreTaxAmountForProjectQuotation(): float
    {
        return max(0, round((float) $this->labour_total, 2));
    }

    /** Client-facing label for the post cross-section (4×4 or 6×6). */
    public function postSizeLabel(): string
    {
        $size = $this->resolvedPostSize();

        return $size === self::POST_SIZE_6X6 ? '6×6' : '4×4';
    }

    public function resolvedPostSize(): string
    {
        $stored = is_string($this->post_size ?? null) ? trim($this->post_size) : '';
        if (in_array($stored, [self::POST_SIZE_4X4, self::POST_SIZE_6X6], true)) {
            return $stored;
        }

        return self::postSizeFromCalculation(is_array($this->calculation) ? $this->calculation : null);
    }

    /**
     * @param  array<string, mixed>|null  $calculation
     */
    public static function postSizeFromCalculation(?array $calculation): string
    {
        foreach ($calculation['materials'] ?? [] as $material) {
            if (! is_array($material)) {
                continue;
            }
            if (! in_array($material['key'] ?? '', FenceMaterial::POST_KEYS, true)) {
                continue;
            }

            $text = strtolower(trim(
                ($material['name'] ?? '').' '.($material['long_name'] ?? '').' '.($material['model'] ?? '')
            ));
            if (str_contains($text, '6x6') || str_contains($text, '6 x 6')) {
                return self::POST_SIZE_6X6;
            }

            return self::POST_SIZE_4X4;
        }

        return self::POST_SIZE_4X4;
    }

    /**
     * Generate a unique fence quote number like "FQ-2026-0001". Sequential
     * per year, independent of other document types, so the client-facing
     * identifier is short and obviously distinct from INV/QUO/CON.
     */
    public static function generateNumber(): string
    {
        $year = (int) date('Y');

        $latest = static::query()
            ->where('quote_number', 'like', "FQ-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('FQ-%d-%04d', $year, $next);
    }
}
