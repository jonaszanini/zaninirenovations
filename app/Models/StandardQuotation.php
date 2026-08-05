<?php

namespace App\Models;

use App\Services\StandardQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StandardQuotation — standalone materials + labour estimate.
 *
 * Linking appends one line to the project's ClientProjectQuotation with
 * amount = grand_total and apply_hst = false (total already includes
 * material HST; labour is untaxed).
 */
class StandardQuotation extends Model
{
    public const HST_RATE = 0.13;

    public const PDF_DETAIL_SUMMARY = 'summary';

    public const PDF_DETAIL_DETAILED = 'detailed';

    protected $fillable = [
        'client_project_id',
        'document_type',
        'quote_number',
        'client_name',
        'display_title',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'includes_material',
        'includes_labour',
        'pdf_detail_mode',
        'materials',
        'labour_mode',
        'labour_quantity',
        'labour_hours',
        'labour_hourly_rate',
        'labour_use_fixed',
        'labour_fixed_amount',
        'labour_fixed_hst_included',
        'materials_subtotal_before_hst',
        'materials_hst',
        'materials_with_hst',
        'labour_total',
        'labour_hst',
        'grand_total',
        'contingency_enabled',
        'contingency_percent',
        'contingency_amount',
        'contingency_hst',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'includes_material' => 'boolean',
        'includes_labour' => 'boolean',
        'materials' => 'array',
        'labour_quantity' => 'decimal:2',
        'labour_hours' => 'decimal:2',
        'labour_hourly_rate' => 'decimal:2',
        'labour_use_fixed' => 'boolean',
        'labour_fixed_amount' => 'decimal:2',
        'labour_fixed_hst_included' => 'boolean',
        'materials_subtotal_before_hst' => 'decimal:2',
        'materials_hst' => 'decimal:2',
        'materials_with_hst' => 'decimal:2',
        'labour_total' => 'decimal:2',
        'labour_hst' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'contingency_enabled' => 'boolean',
        'contingency_percent' => 'decimal:2',
        'contingency_amount' => 'decimal:2',
        'contingency_hst' => 'decimal:2',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'include_commute' => 'boolean',
        'commute_trip_percent' => 'decimal:2',
        'commute_estimated_trips' => 'integer',
        'commute_round_trip_cost' => 'decimal:2',
        'commute_amount' => 'decimal:2',
        'version' => 'integer',
        'linked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');

        $latest = static::query()
            ->where('quote_number', 'like', "SQ-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('SQ-%d-%04d', $year, $next);
    }

    public function usesDetailedPdf(): bool
    {
        return ($this->pdf_detail_mode ?? self::PDF_DETAIL_SUMMARY) === self::PDF_DETAIL_DETAILED;
    }

    /**
     * Short human-readable line used as the placeholder text when linking
     * this quotation to a project (see _link_to_project_card.blade.php).
     */
    public function summaryDescription(): string
    {
        $title = trim((string) ($this->display_title ?? ''));

        return $title !== '' ? $title : 'Standard quotation';
    }

    /**
     * Summary-mode PDF line label from Includes (PDF wording) checkboxes.
     */
    public function pdfSummaryLineDescription(): string
    {
        $material = (bool) $this->includes_material;
        $labour = (bool) $this->includes_labour;

        if ($material && $labour) {
            return 'Labour and material';
        }
        if ($labour) {
            return 'Labour';
        }
        if ($material) {
            return 'Material';
        }

        return 'Labour and material';
    }

    /**
     * @return array<string, mixed>
     */
    public function computedTotals(): array
    {
        return (new StandardQuotationCalculator)->compute(
            is_array($this->materials) ? $this->materials : [],
            (float) $this->labour_hourly_rate,
            (string) ($this->labour_mode ?? 'hours'),
            (float) $this->labour_quantity,
            (bool) $this->contingency_enabled,
            (float) ($this->contingency_percent ?? 0),
            (bool) $this->labour_use_fixed,
            (float) ($this->labour_fixed_amount ?? 0),
            (bool) ($this->labour_fixed_hst_included ?? false),
        );
    }

    /**
     * Itemized rows for the client PDF (materials, contingency, labour).
     *
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    public function detailedPdfLineItems(): array
    {
        $computed = $this->computedTotals();
        $lines = [];

        foreach ($computed['materials'] as $material) {
            $parts = array_values(array_filter([
                trim((string) ($material['short_name'] ?? '')),
                trim((string) ($material['description'] ?? '')),
            ]));
            $label = $parts !== [] ? implode(' — ', $parts) : 'Material';
            $qty = (float) ($material['quantity'] ?? 0);
            $unit = (float) ($material['unit_price'] ?? 0);
            if ($qty > 0 && $unit >= 0) {
                $label .= sprintf(
                    ' (%s × $%s)',
                    self::formatPdfQty($qty),
                    number_format($unit, 2, '.', ',')
                );
            }
            $lines[] = [
                'description' => $label,
                'amount' => round((float) $material['line_subtotal'], 2),
                'apply_hst' => (bool) $material['apply_hst'],
            ];
        }

        if ($computed['contingency_enabled'] && (float) $computed['contingency_amount'] > 0) {
            $pct = self::formatPdfQty((float) $computed['contingency_percent']);
            if ((float) $computed['contingency_amount_taxable'] > 0) {
                $lines[] = [
                    'description' => 'Contingency allowance ('.$pct.'%)',
                    'amount' => round((float) $computed['contingency_amount_taxable'], 2),
                    'apply_hst' => true,
                ];
            }
            if ((float) $computed['contingency_amount_exempt'] > 0) {
                $lines[] = [
                    'description' => 'Contingency allowance ('.$pct.'%) — HST exempt',
                    'amount' => round((float) $computed['contingency_amount_exempt'], 2),
                    'apply_hst' => false,
                ];
            }
        }

        if ((float) $computed['labour_total'] > 0) {
            $lines[] = [
                'description' => self::labourPdfDescription($this, $computed),
                'amount' => round((float) $computed['labour_total'], 2),
                'apply_hst' => true,
            ];
        }

        if ($this->include_commute && (float) $this->commute_amount > 0) {
            $commuteLine = \App\Services\CommuteExpenseService::projectLineItem(
                (float) $this->commute_amount,
                (int) ($this->commute_estimated_trips ?? 1)
            );
            if ($commuteLine !== null) {
                $lines[] = $commuteLine;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    protected static function labourPdfDescription(self $sq, array $computed): string
    {
        if ($sq->labour_use_fixed) {
            return 'Labour (flat fee)';
        }

        $mode = ($sq->labour_mode ?? 'hours') === 'days' ? 'days' : 'hours';
        $qtyLabel = self::formatPdfQty((float) $sq->labour_quantity).' '.$mode;
        $rate = number_format((float) $sq->labour_hourly_rate, 2, '.', ',');

        return 'Labour ('.$qtyLabel.' @ $'.$rate.'/hr)';
    }

    protected static function formatPdfQty(float $qty): string
    {
        $formatted = number_format($qty, 2, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
