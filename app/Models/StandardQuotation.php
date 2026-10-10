<?php

namespace App\Models;

use App\Services\StandardQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StandardQuotation — standalone materials + labour estimate.
 *
 * Linking syncs pre-HST taxable/exempt lines (plus hidden commute/debris)
 * into the project's ClientProjectQuotation. The client PDF rebuilds from
 * this quote and folds commute/debris into Labour.
 */
class StandardQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
    use \App\Models\Concerns\HasPdfFoldVersion;
    use \App\Models\Concerns\HasReplyToAcceptChoice;

    public const HST_RATE = 0.13;

    public const PDF_DETAIL_COMBINED = 'combined';

    public const PDF_DETAIL_SUMMARY = 'summary';

    public const PDF_DETAIL_DETAILED = 'detailed';

    /** @return list<string> */
    public static function pdfDetailModes(): array
    {
        return [
            self::PDF_DETAIL_COMBINED,
            self::PDF_DETAIL_SUMMARY,
            self::PDF_DETAIL_DETAILED,
        ];
    }

    public const LINE_KIND_MATERIAL = 'material';

    public const LINE_KIND_ELECTRICAL = 'electrical';

    public const ELECTRICAL_LINE_DESCRIPTION = 'Electrical work (Including material)';

    protected $fillable = [
        'client_project_id',
        'document_type',
        'quote_number',
        'client_name',
        'is_draft',
        'display_title',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'includes_material',
        'includes_labour',
        'pdf_detail_mode',
        'materials',
        'works',
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
        'include_labour_hst',
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
        'include_debris_removal',
        'debris_removal_amount',
        'include_building',
        'building_labor_percent',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'includes_material' => 'boolean',
        'includes_labour' => 'boolean',
        'materials' => 'array',
        'works' => 'array',
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
        'include_labour_hst' => 'boolean',
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
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
        'include_building' => 'boolean',
        'building_labor_percent' => 'decimal:2',
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

    public function usesCombinedPdf(): bool
    {
        return ($this->pdf_detail_mode ?? self::PDF_DETAIL_SUMMARY) === self::PDF_DETAIL_COMBINED;
    }

    public function usesDetailedPdf(): bool
    {
        return ($this->pdf_detail_mode ?? self::PDF_DETAIL_SUMMARY) === self::PDF_DETAIL_DETAILED;
    }

    public function usesSummaryPdf(): bool
    {
        return ! $this->usesCombinedPdf() && ! $this->usesDetailedPdf();
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

        // Includes is PDF wording, but never contradict priced lines.
        if (! $labour && $this->chargedLabourPreTax() > 0) {
            $labour = true;
        }
        if (! $material && $this->chargedMaterialsPreTax() > 0) {
            $material = true;
        }

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

    public function chargedLabourPreTax(): float
    {
        $computed = $this->computedTotals();
        $fromCompute = max(0, round((float) ($computed['labour_total'] ?? 0), 2));
        if ($fromCompute > 0) {
            return $fromCompute;
        }

        return max(0, round((float) ($this->labour_total ?? 0), 2));
    }

    public function chargedMaterialsPreTax(): float
    {
        $computed = $this->computedTotals();
        $fromCompute = max(0, round((float) ($computed['materials_subtotal_before_hst'] ?? 0), 2));
        if ($fromCompute > 0) {
            return $fromCompute;
        }

        return max(0, round((float) ($this->materials_subtotal_before_hst ?? 0), 2));
    }

    /**
     * Jobs for editing/PDF. Legacy quotes (no `works` JSON) wrap the root
     * materials + labour columns as a single job.
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedWorks(): array
    {
        $works = $this->works;
        if (is_array($works) && $works !== []) {
            return array_values($works);
        }

        return [[
            'title' => '',
            'materials' => is_array($this->materials) ? $this->materials : [],
            'labour_mode' => $this->labour_mode ?? 'hours',
            'labour_quantity' => $this->labour_quantity,
            'labour_hourly_rate' => $this->labour_hourly_rate,
            'labour_use_fixed' => (bool) $this->labour_use_fixed,
            'labour_fixed_amount' => $this->labour_fixed_amount,
            'labour_fixed_hst_included' => (bool) $this->labour_fixed_hst_included,
        ]];
    }

    public function hasMultipleWorks(): bool
    {
        return count($this->resolvedWorks()) >= 2;
    }

    /**
     * Material rows from every job (legacy quotes: the root `materials` column).
     *
     * @return list<array<string, mixed>>
     */
    public function flattenedMaterials(): array
    {
        $out = [];
        foreach ($this->resolvedWorks() as $work) {
            $mats = is_array($work['materials'] ?? null) ? $work['materials'] : [];
            foreach ($mats as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function computedTotals(): array
    {
        return (new StandardQuotationCalculator)->computeWorks(
            $this->resolvedWorks(),
            (bool) $this->contingency_enabled,
            (float) ($this->contingency_percent ?? 0),
            $this->chargesLabourHst(),
            (bool) ($this->include_building ?? false),
            (float) ($this->building_labor_percent ?? \App\Support\QuotationBuildingLabor::DEFAULT_PERCENT),
            // v3-only: leftover surcharge on a job that has labour.
            $this->usesCurrentPdfFold(),
        );
    }

    /** Labour HST is on unless the quote explicitly turns it off (cash). */
    public function chargesLabourHst(): bool
    {
        return $this->include_labour_hst !== false;
    }

    /** Pre-HST labour only (excludes materials, commute, and debris). */
    public function labourPreTaxAmountForProjectQuotation(): float
    {
        return $this->chargedLabourPreTax();
    }

    public static function isElectricalMaterialRow(array $row): bool
    {
        $kind = strtolower(trim((string) ($row['line_kind'] ?? $row['entry_mode'] ?? '')));

        return $kind === self::LINE_KIND_ELECTRICAL;
    }

    public static function normalizeMaterialLineKind(array $row): string
    {
        return self::isElectricalMaterialRow($row)
            ? self::LINE_KIND_ELECTRICAL
            : self::LINE_KIND_MATERIAL;
    }

    /**
     * Client PDF rows: one Material subtotal (not each SKU; contingency is
     * folded in, not shown as its own line), electrical work as its own
     * line(s), then labour. Two or more jobs list each job with its own
     * material and labour amounts.
     *
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    public function detailedPdfLineItems(): array
    {
        return $this->buildStructuredPdfLineItems();
    }

    /**
     * Summary PDF: Material and Labour as two lines for the whole quotation
     * (jobs are aggregated — no per-job headings).
     *
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    public function summaryPdfLineItems(): array
    {
        return $this->aggregatedPdfLineItems();
    }

    /**
     * Same structure as detailed, but Material + Labour collapse into one
     * "Labour and material" (or Includes wording) line per job / quote.
     *
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    public function combinedPdfLineItems(): array
    {
        return $this->collapseMaterialAndLabourLines(
            $this->buildStructuredPdfLineItems()
        );
    }

    /**
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    protected function buildStructuredPdfLineItems(): array
    {
        if ($this->hasMultipleWorks()) {
            return $this->multiJobPdfLineItems();
        }

        return $this->aggregatedPdfLineItems();
    }

    /**
     * Whole-quote Material + Labour lines (all jobs rolled together).
     *
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    protected function aggregatedPdfLineItems(): array
    {
        $computed = $this->computedTotals();
        $lines = [];

        $taxable = 0.0;
        $taxableHst = 0.0;
        $exempt = 0.0;
        $electricalLines = [];

        foreach ($computed['materials'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $amount = round((float) ($row['line_subtotal'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            if (self::isElectricalMaterialRow($row)) {
                $desc = trim((string) ($row['description'] ?? ''));
                $electricalLines[] = $this->withPdfHstAmount([
                    'description' => $desc !== '' ? $desc : self::ELECTRICAL_LINE_DESCRIPTION,
                    'amount' => $amount,
                    'apply_hst' => (bool) ($row['apply_hst'] ?? true),
                ], $this->materialRowHst($row));
                continue;
            }

            if (filter_var($row['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $taxable += $amount;
                $taxableHst += $this->materialRowHst($row);
            } else {
                $exempt += $amount;
            }
        }

        if (! empty($computed['contingency_enabled']) && (float) ($computed['contingency_amount'] ?? 0) > 0) {
            $taxable += round((float) ($computed['contingency_amount_taxable'] ?? 0), 2);
            $taxableHst += $this->contingencyHstLikeEditor($computed);
            $exempt += round((float) ($computed['contingency_amount_exempt'] ?? 0), 2);
        }

        $taxable = round($taxable, 2);
        $exempt = round($exempt, 2);

        if ($taxable > 0) {
            $lines[] = $this->withPdfHstAmount([
                'description' => 'Material',
                'amount' => $taxable,
                'apply_hst' => true,
            ], round($taxableHst, 2));
        }
        if ($exempt > 0) {
            $lines[] = [
                'description' => $taxable > 0 ? 'Material — HST exempt' : 'Material',
                'amount' => $exempt,
                'apply_hst' => false,
            ];
        }

        foreach ($electricalLines as $electricalLine) {
            $lines[] = $electricalLine;
        }

        if ((float) $computed['labour_total'] > 0) {
            $labourLine = [
                'description' => 'Labour',
                'amount' => round((float) $computed['labour_total'], 2),
                'apply_hst' => $this->chargesLabourHst(),
            ];
            if ($this->chargesLabourHst()) {
                $labourLine = $this->withPdfHstAmount(
                    $labourLine,
                    round((float) ($computed['labour_hst'] ?? 0), 2)
                );
            }
            $lines[] = $labourLine;
        }

        $this->appendCommuteAndDebrisPdfLines($lines);

        return $lines;
    }

    /**
     * Merge taxable Material + Labour into one Includes-wording line.
     * Keeps HST-exempt material, electrical, commute, and debris separate.
     *
     * @param  list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>  $lines
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    protected function collapseMaterialAndLabourLines(array $lines): array
    {
        $label = $this->pdfSummaryLineDescription();
        $out = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $row = $lines[$i];
            if (! empty($row['is_heading'])) {
                $out[] = $row;
                $i++;
                continue;
            }

            $desc = (string) ($row['description'] ?? '');
            if ($this->isPdfCommuteOrDebrisLine($desc)) {
                $out[] = $row;
                $i++;
                continue;
            }

            $taxableCombined = 0.0;
            $taxableCombinedHst = 0.0;
            $exemptMaterial = 0.0;
            $mergedTaxableLabour = false;
            $passthrough = [];
            $j = $i;

            while ($j < $n) {
                $cur = $lines[$j];
                if (! empty($cur['is_heading'])) {
                    break;
                }
                $curDesc = (string) ($cur['description'] ?? '');
                if ($this->isPdfCommuteOrDebrisLine($curDesc)) {
                    break;
                }
                if ($curDesc === 'Material' && ! empty($cur['apply_hst'])) {
                    $taxableCombined += (float) ($cur['amount'] ?? 0);
                    $taxableCombinedHst += ClientProjectQuotation::lineHstAmount($cur);
                } elseif (str_starts_with($curDesc, 'Material')) {
                    $exemptMaterial += (float) ($cur['amount'] ?? 0);
                } elseif ($curDesc === 'Labour') {
                    if (! empty($cur['apply_hst'])) {
                        $taxableCombined += (float) ($cur['amount'] ?? 0);
                        $taxableCombinedHst += ClientProjectQuotation::lineHstAmount($cur);
                        $mergedTaxableLabour = true;
                    } else {
                        $passthrough[] = $cur;
                    }
                } else {
                    $passthrough[] = $cur;
                }
                $j++;
            }

            $taxableCombined = round($taxableCombined, 2);
            $exemptMaterial = round($exemptMaterial, 2);

            if ($taxableCombined > 0) {
                $lineLabel = $label;
                if (! $mergedTaxableLabour && ($label === 'Labour and material' || $label === 'Labour')) {
                    $lineLabel = 'Material';
                }
                $out[] = $this->withPdfHstAmount([
                    'description' => $lineLabel,
                    'amount' => $taxableCombined,
                    'apply_hst' => true,
                ], round($taxableCombinedHst, 2));
            }
            if ($exemptMaterial > 0) {
                $out[] = [
                    'description' => $taxableCombined > 0 ? 'Material — HST exempt' : $label,
                    'amount' => $exemptMaterial,
                    'apply_hst' => false,
                ];
            }
            foreach ($passthrough as $extra) {
                $out[] = $extra;
            }

            if ($j === $i) {
                $out[] = $row;
                $i++;
            } else {
                $i = $j;
            }
        }

        return $out;
    }

    protected function isPdfCommuteOrDebrisLine(string $description): bool
    {
        return str_starts_with($description, 'Commute expense')
            || \App\Services\DebrisRemovalExpenseService::isLineDescription($description);
    }

    /**
     * @param  list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>  $lines
     */
    protected function appendCommuteAndDebrisPdfLines(array &$lines): void
    {
        if ($this->include_commute && (float) $this->commute_amount > 0) {
            $commuteLine = \App\Services\CommuteExpenseService::projectLineItem(
                (float) $this->commute_amount,
                (int) ($this->commute_estimated_trips ?? 1)
            );
            if ($commuteLine !== null) {
                $lines[] = $commuteLine;
            }
        }

        if ($this->include_debris_removal && (float) $this->debris_removal_amount > 0) {
            $debrisLine = \App\Services\DebrisRemovalExpenseService::projectLineItem(
                (float) $this->debris_removal_amount
            );
            if ($debrisLine !== null) {
                $lines[] = $debrisLine;
            }
        }
    }

    /**
     * @return list<array{description: string, amount: float, apply_hst: bool, is_heading?: bool}>
     */
    protected function multiJobPdfLineItems(): array
    {
        $computed = $this->computedTotals();
        $jobs = is_array($computed['works'] ?? null) ? $computed['works'] : [];
        $lines = [];
        $jobNonElecPre = [];
        foreach ($jobs as $idx => $job) {
            $jobNonElecPre[$idx] = $this->nonElectricalMaterialsPreTax(is_array($job) ? $job : []);
        }
        $totalMatPre = round(array_sum($jobNonElecPre), 2);
        $contAmt = ! empty($computed['contingency_enabled'])
            ? (float) ($computed['contingency_amount'] ?? 0)
            : 0.0;
        $contTaxable = $contAmt > 0 ? (float) ($computed['contingency_amount_taxable'] ?? 0) : 0.0;
        $allocatedCont = 0.0;
        $allocatedContTax = 0.0;
        $allocatedContHst = 0.0;
        $allocatedLabHst = 0.0;
        $quoteContHst = $this->contingencyHstLikeEditor($computed);
        $quoteLabHst = $this->chargesLabourHst() ? round((float) ($computed['labour_hst'] ?? 0), 2) : 0.0;
        $jobCount = count($jobs);

        foreach ($jobs as $idx => $job) {
            if (! is_array($job)) {
                continue;
            }

            $title = trim((string) ($job['title'] ?? ''));
            if ($title === '') {
                $title = 'Job '.($idx + 1);
            }

            $lines[] = [
                'description' => $title,
                'amount' => 0.0,
                'apply_hst' => false,
                'is_heading' => true,
            ];

            $jobMatPre = $jobNonElecPre[$idx] ?? 0.0;
            $isLast = $idx === $jobCount - 1;
            if ($totalMatPre > 0 && $contAmt > 0) {
                if ($isLast) {
                    $jobCont = round($contAmt - $allocatedCont, 2);
                    $jobContTax = round($contTaxable - $allocatedContTax, 2);
                } else {
                    $ratio = $jobMatPre / $totalMatPre;
                    $jobCont = round($contAmt * $ratio, 2);
                    $jobContTax = round($contTaxable * $ratio, 2);
                    $allocatedCont += $jobCont;
                    $allocatedContTax += $jobContTax;
                }
            } else {
                $jobCont = 0.0;
                $jobContTax = 0.0;
            }
            $jobContExempt = round($jobCont - $jobContTax, 2);

            $taxable = 0.0;
            $taxableHst = 0.0;
            $exempt = 0.0;
            $electricalLines = [];

            foreach ($job['materials'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $amount = round((float) ($row['line_subtotal'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                if (self::isElectricalMaterialRow($row)) {
                    $desc = trim((string) ($row['description'] ?? ''));
                    $electricalLines[] = $this->withPdfHstAmount([
                        'description' => $desc !== '' ? $desc : self::ELECTRICAL_LINE_DESCRIPTION,
                        'amount' => $amount,
                        'apply_hst' => (bool) ($row['apply_hst'] ?? true),
                    ], $this->materialRowHst($row));
                    continue;
                }

                if (filter_var($row['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                    $taxable += $amount;
                    $taxableHst += $this->materialRowHst($row);
                } else {
                    $exempt += $amount;
                }
            }

            $jobContHst = 0.0;
            if ($contAmt > 0 && $quoteContHst > 0) {
                if ($isLast) {
                    $jobContHst = round($quoteContHst - $allocatedContHst, 2);
                } else {
                    $jobContHst = round($quoteContHst * ($jobMatPre / max($totalMatPre, 0.00001)), 2);
                    $allocatedContHst += $jobContHst;
                }
            }

            $taxable = round($taxable + $jobContTax, 2);
            $taxableHst = round($taxableHst + $jobContHst, 2);
            $exempt = round($exempt + $jobContExempt, 2);

            if ($taxable > 0) {
                $lines[] = $this->withPdfHstAmount([
                    'description' => 'Material',
                    'amount' => $taxable,
                    'apply_hst' => true,
                ], $taxableHst);
            } elseif ($isLast && $jobContHst != 0.0) {
                $this->addHstAmountToLastPdfLine($lines, 'Material', $jobContHst);
            }
            if ($exempt > 0) {
                $lines[] = [
                    'description' => $taxable > 0 ? 'Material — HST exempt' : 'Material',
                    'amount' => $exempt,
                    'apply_hst' => false,
                ];
            }

            foreach ($electricalLines as $electricalLine) {
                $lines[] = $electricalLine;
            }

            $jobLabHst = 0.0;
            if ($this->chargesLabourHst()) {
                $jobLabHst = (float) ($job['labour_hst'] ?? 0);
                $surcharge = (float) ($job['building_labor_surcharge'] ?? 0);
                if ($surcharge > 0) {
                    $jobLabHst += round($surcharge * ClientProjectQuotation::HST_RATE, 2);
                }
                if ($isLast) {
                    $jobLabHst = round($quoteLabHst - $allocatedLabHst, 2);
                } else {
                    $jobLabHst = round($jobLabHst, 2);
                    $allocatedLabHst += $jobLabHst;
                }
            }

            if ((float) ($job['labour_total'] ?? 0) > 0) {
                $labourLine = [
                    'description' => 'Labour',
                    'amount' => round((float) $job['labour_total'], 2),
                    'apply_hst' => $this->chargesLabourHst(),
                ];
                if ($this->chargesLabourHst()) {
                    $labourLine = $this->withPdfHstAmount($labourLine, $jobLabHst);
                }
                $lines[] = $labourLine;
            } elseif ($isLast && $jobLabHst != 0.0) {
                $this->addHstAmountToLastPdfLine($lines, 'Labour', $jobLabHst);
            }
        }

        $this->appendCommuteAndDebrisPdfLines($lines);

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    protected function withPdfHstAmount(array $line, float $hstAmount): array
    {
        if (! $this->usesNonLegacyPdfFold() || empty($line['apply_hst'])) {
            return $line;
        }

        $line['hst_amount'] = round($hstAmount, 2);

        return $line;
    }

    /**
     * Last-job leftover HST when that job has no Material / Labour line.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    protected function addHstAmountToLastPdfLine(array &$lines, string $description, float $hstAmount): void
    {
        if (! $this->usesNonLegacyPdfFold() || $hstAmount == 0.0) {
            return;
        }

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (! empty($lines[$i]['is_heading'])) {
                continue;
            }
            if (($lines[$i]['description'] ?? '') !== $description) {
                continue;
            }
            if (empty($lines[$i]['apply_hst'])) {
                continue;
            }
            $current = (float) ($lines[$i]['hst_amount'] ?? ClientProjectQuotation::lineHstAmount($lines[$i]));
            $lines[$i] = $this->withPdfHstAmount($lines[$i], round($current + $hstAmount, 2));

            return;
        }
    }

    /**
     * Editor JS: contHst = round(contAmt * (contBaseHst / contBase), 2).
     *
     * @param  array<string, mixed>  $computed
     */
    protected function contingencyHstLikeEditor(array $computed): float
    {
        $contAmt = round((float) ($computed['contingency_amount'] ?? 0), 2);
        if ($contAmt <= 0) {
            return 0.0;
        }

        $contBase = 0.0;
        $contBaseHst = 0.0;
        foreach ($computed['materials'] ?? [] as $row) {
            if (! is_array($row) || self::isElectricalMaterialRow($row)) {
                continue;
            }
            $contBase += (float) ($row['line_subtotal'] ?? 0);
            $contBaseHst += $this->materialRowHst($row);
        }
        $contBase = round($contBase, 2);
        $contBaseHst = round($contBaseHst, 2);
        if ($contBase <= 0) {
            return 0.0;
        }

        return round($contAmt * ($contBaseHst / $contBase), 2);
    }

    /**
     * Per-row HST as the editor JS computes it (round each line, then sum).
     *
     * @param  array<string, mixed>  $row
     */
    protected function materialRowHst(array $row): float
    {
        if (array_key_exists('line_hst', $row)) {
            return round((float) $row['line_hst'], 2);
        }

        if (! filter_var($row['apply_hst'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return 0.0;
        }

        return round((float) ($row['line_subtotal'] ?? 0) * ClientProjectQuotation::HST_RATE, 2);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    protected function nonElectricalMaterialsPreTax(array $job): float
    {
        $sum = 0.0;
        foreach ($job['materials'] ?? [] as $row) {
            if (! is_array($row) || self::isElectricalMaterialRow($row)) {
                continue;
            }
            $sum += (float) ($row['line_subtotal'] ?? 0);
        }

        return round($sum, 2);
    }
}
