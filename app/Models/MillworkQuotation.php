<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MillworkQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
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

    public function sheetCount(): int
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $cut = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];

        return max(0, (int) ($cut['sheet_count'] ?? 0));
    }

    public function displayTitle(): string
    {
        return 'Custom Millwork — '.$this->quote_number;
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
        $label = trim((string) ($scope['piece_summary'] ?? ''));
        if ($label !== '') {
            return 'Custom millwork – '.$label;
        }

        $n = (int) ($scope['piece_count'] ?? 0);

        return $n > 0
            ? 'Custom millwork – '.$n.' piece'.($n === 1 ? '' : 's')
            : 'Custom millwork';
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

        $lines = [];
        $lines[] = 'Custom millwork — shop-built cabinets, closets, built-ins, and finish carpentry.';
        $summary = trim((string) ($scope['piece_summary'] ?? ''));
        if ($summary !== '') {
            $lines[] = $summary;
        }

        $sheets = $this->sheetCount();
        if ($sheets > 0) {
            $lines[] = 'Sheet goods: '.$sheets.' sheet'.($sheets === 1 ? '' : 's').' nested on a cut plan.';
        }

        $edgeLf = (float) ($calc['summary']['edge_band_lf'] ?? 0);
        if ($edgeLf > 0) {
            $lines[] = 'Edge banding: '.rtrim(rtrim(number_format($edgeLf, 1, '.', ''), '0'), '.').' lf.';
        }

        foreach ($calc['labor'] ?? [] as $row) {
            if (! is_array($row) || (float) ($row['hours'] ?? 0) <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '' && ($row['key'] ?? '') !== 'markup') {
                $lines[] = '  • '.$label;
            }
        }

        $finish = trim((string) ($scope['finish_label'] ?? ''));
        if ($finish !== '') {
            $lines[] = 'Finish: '.$finish;
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
            ->where('quote_number', 'like', "MW-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('MW-%d-%04d', $year, $next);
    }
}
