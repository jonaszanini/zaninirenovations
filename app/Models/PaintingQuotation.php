<?php

namespace App\Models;

use App\Services\PaintingQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaintingQuotation extends Model
{
    public const HST_RATE = 0.13;

    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'is_draft',
        'inputs',
        'calculation',
        'final_quote_total',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
        'include_debris_removal',
        'debris_removal_amount',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'inputs' => 'array',
        'calculation' => 'array',
        'is_draft' => 'boolean',
        'final_quote_total' => 'decimal:2',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'include_commute' => 'boolean',
        'commute_trip_percent' => 'decimal:2',
        'commute_estimated_trips' => 'integer',
        'commute_round_trip_cost' => 'decimal:2',
        'commute_amount' => 'decimal:2',
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
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

    public function paintLine(): ?PaintingPaintLine
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $id = (int) ($inputs['paint_line_id'] ?? 0);

        return $id > 0 ? PaintingPaintLine::query()->find($id) : null;
    }

    public function displayTitle(): string
    {
        return 'Painting — '.$this->quote_number;
    }

    public function netPaintingArea(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        return round((float) ($scope['net_painting_area_sqf'] ?? 0), 2);
    }

    public function labourPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

        return max(0, round((float) ($summary['labor_pre_hst'] ?? 0), 2));
    }

    public function materialsPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

        return max(0, round((float) ($summary['materials_pre_hst'] ?? 0), 2));
    }

    /** Pre-HST total for syncing to {@see ClientProjectQuotation} line items (PDF adds 13% HST). */
    public function preTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        $pre = round((float) ($summary['subtotal_pre_hst'] ?? 0), 2);
        if ($pre > 0) {
            return $pre;
        }

        $final = round((float) $this->final_quote_total, 2);
        if ($final > 0) {
            return round($final / (1 + self::HST_RATE), 2);
        }

        return 0.0;
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0;
    }

    public function summaryDescription(): string
    {
        $paint = $this->paintLine();
        $paintName = $paint?->name ?? 'Interior painting';
        $net = $this->netPaintingArea();

        return sprintf(
            'Interior painting — %s sq ft net (%s)',
            number_format($net, 0),
            $paintName
        );
    }

    /** Scope text for PDF / project quotation work description block. */
    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        $lines = [];
        $total = round((float) ($scope['total_property_sqf'] ?? $inputs['total_property_sqf'] ?? 0), 0);
        $excluded = round((float) ($scope['excluded_total_sqf'] ?? 0), 0);
        $net = round((float) ($scope['net_painting_area_sqf'] ?? 0), 0);
        $lines[] = "Total property size: {$total} sq ft";
        if ($excluded > 0) {
            $lines[] = 'Excluded areas: '.number_format($excluded, 0).' sq ft';
            foreach ($scope['excluded_areas'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $detail = trim((string) ($row['detail'] ?? ''));
                if ($name !== '') {
                    $lines[] = '  • '.$name.($detail !== '' ? ' ('.$detail.')' : '');
                }
            }
        }
        $lines[] = 'Net painting area: '.number_format($net, 0).' sq ft';

        $paintName = trim((string) ($scope['paint_line_name'] ?? ''));
        if ($paintName !== '') {
            $coats = (int) ($scope['coats'] ?? PaintingQuotationCalculator::DEFAULT_COATS);
            $lines[] = "Paint: {$paintName} ({$coats} top coats)";
        }

        $prep = trim((string) ($inputs['surface_prep_description'] ?? ''));
        if ($prep !== '') {
            $lines[] = '';
            $lines[] = 'Surface preparation:';
            $lines[] = $prep;
        }

        $work = trim((string) ($this->work_description ?? ''));
        if ($work !== '') {
            $lines[] = '';
            $lines[] = $work;
        }

        return implode("\n", $lines);
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "PT-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('PT-%d-%04d', $year, $next);
    }
}
