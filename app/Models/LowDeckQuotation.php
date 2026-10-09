<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LowDeckQuotation — a saved low floating deck calculator result.
 *
 * Stores both the raw inputs (so the quote can be re-opened and edited)
 * and a snapshot of the calculated breakdown (so older quotes keep the
 * same totals even after material prices change).
 *
 * Like the other calculator modules, this has NO PDF of its own — the
 * client-facing document is always the project's main Quotation PDF.
 * Linking a deck quote to a project appends a single line item
 * (description + pre-tax amount) to that project's ClientProjectQuotation.
 */
class LowDeckQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
    use \App\Models\Concerns\HasPdfFoldVersion;
    use \App\Models\Concerns\HasReplyToAcceptChoice;

    public const HST_RATE = 0.13;

    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'width_ft',
        'length_ft',
        'joist_spacing_in',
        'labour_rate_per_sqft',
        'waste_dump_fee_cad',
        'include_diagrams_on_pdf',
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
        'width_ft' => 'decimal:2',
        'length_ft' => 'decimal:2',
        'joist_spacing_in' => 'integer',
        'labour_rate_per_sqft' => 'decimal:2',
        'waste_dump_fee_cad' => 'decimal:2',
        'include_diagrams_on_pdf' => 'boolean',
        'calculation' => 'array',
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

    public function areaSqFt(): float
    {
        return round((float) $this->width_ft * (float) $this->length_ft, 2);
    }

    /**
     * Short human-friendly description used when linking a deck quote into
     * a project's main Quotation as a line item. Example:
     *   "Low floating deck, 12' x 16' (192 sq ft), 16 in. joist spacing"
     */
    public function summaryDescription(): string
    {
        $width = $this->trimFt((float) $this->width_ft);
        $length = $this->trimFt((float) $this->length_ft);
        $area = $this->trimFt($this->areaSqFt());

        return sprintf(
            "Low floating deck, %s' x %s' (%s sq ft), %s joist spacing",
            $width,
            $length,
            $area,
            $this->formatJoistSpacingLabel()
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

    private function formatJoistSpacingLabel(): string
    {
        $exact = data_get($this->calculation, 'geometry.joists.field_spacing_in');
        if (is_numeric($exact) && (float) $exact > 0) {
            $value = (float) $exact;
            if (abs($value - round($value)) < 0.01) {
                return (int) round($value).' in.';
            }

            return rtrim(rtrim(number_format($value, 2), '0'), '.').' in.';
        }

        return (int) $this->joist_spacing_in.' in.';
    }

    private function trimFt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Generate a unique deck quote number like "LDQ-2026-0001". Sequential
     * per year, independent of other document types.
     */
    public static function generateNumber(): string
    {
        $year = (int) date('Y');

        $latest = static::query()
            ->where('quote_number', 'like', "LDQ-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('LDQ-%d-%04d', $year, $next);
    }
}
