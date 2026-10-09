<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrimmingQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
    use \App\Models\Concerns\HasPdfFoldVersion;
    use \App\Models\Concerns\HasReplyToAcceptChoice;

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
        'include_debris_removal',
        'debris_removal_amount',
        'additional_costs',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
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
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
        'additional_costs' => 'array',
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

    public function displayTitle(): string
    {
        return 'Trimming — '.$this->quote_number;
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

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0;
    }

    public function summaryDescription(): string
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
        $families = is_array($scope['families'] ?? null) ? $scope['families'] : [];
        $lf = round((float) ($scope['total_lf'] ?? 0), 0);

        $labels = [];
        foreach ($families as $key) {
            $label = \App\Services\TrimmingQuotationCalculator::FAMILIES[$key] ?? null;
            if (is_string($label) && $label !== '') {
                $labels[] = $label;
            }
        }

        $bits = $labels !== [] ? implode(', ', $labels) : 'Interior trim';
        if ($lf > 0) {
            return 'Trimming – '.$bits.' ('.number_format($lf, 0).' lf)';
        }

        return 'Trimming – '.$bits;
    }

    public function projectTitleSuffix(): string
    {
        return $this->summaryDescription();
    }

    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        $lines = ['Interior trimming'];
        $lf = round((float) ($scope['total_lf'] ?? 0), 0);
        if ($lf > 0) {
            $lines[] = 'Measured trim: '.number_format($lf, 0).' linear ft';
        }

        $doors = (int) ($inputs['door_qty'] ?? 0);
        $windows = (int) ($inputs['window_qty'] ?? 0);
        if ($doors > 0) {
            $lines[] = 'Door openings: '.$doors;
        }
        if ($windows > 0) {
            $lines[] = 'Window openings: '.$windows;
        }

        foreach ($calc['labor'] ?? [] as $row) {
            if (! is_array($row) || (float) ($row['hours'] ?? 0) <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $lines[] = '  • '.$label;
            }
        }

        return \App\Support\QuotationWorkDescriptionHtml::combineAutoScopeWithWork(
            $lines,
            $this->work_description
        );
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "TR-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('TR-%d-%04d', $year, $next);
    }
}
