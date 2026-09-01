<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShedQuotation extends Model
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

    public function areaSqft(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
        $inputs = is_array($this->inputs) ? $this->inputs : [];

        if (isset($scope['area_sqft'])) {
            return round((float) $scope['area_sqft'], 2);
        }

        return round(max(0, (float) ($inputs['length_ft'] ?? 0)) * max(0, (float) ($inputs['width_ft'] ?? 0)), 2);
    }

    public function displayTitle(): string
    {
        return 'Shed — '.$this->quote_number;
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
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $len = round((float) ($scope['length_ft'] ?? $inputs['length_ft'] ?? 0), 1);
        $wid = round((float) ($scope['width_ft'] ?? $inputs['width_ft'] ?? 0), 1);
        $type = \App\Services\ShedQuotationCalculator::shedTypeLabel(
            (string) ($scope['shed_type'] ?? $inputs['shed_type'] ?? 'gable')
        );
        $finish = (($inputs['interior_finish'] ?? 'rough') === 'finished') ? 'finished' : 'rough';

        if ($len > 0 && $wid > 0) {
            return sprintf(
                'Shed – %s × %s ft, %s, %s',
                rtrim(rtrim(number_format($len, 1, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($wid, 1, '.', ''), '0'), '.'),
                $type,
                $finish
            );
        }

        return 'Shed – '.$type.', '.$finish;
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
        $len = round((float) ($scope['length_ft'] ?? $inputs['length_ft'] ?? 0), 1);
        $wid = round((float) ($scope['width_ft'] ?? $inputs['width_ft'] ?? 0), 1);
        $type = \App\Services\ShedQuotationCalculator::shedTypeLabel(
            (string) ($scope['shed_type'] ?? $inputs['shed_type'] ?? 'gable')
        );
        $lines[] = 'Shed construction — '.$type
            .($len > 0 && $wid > 0
                ? ' — '.rtrim(rtrim(number_format($len, 1, '.', ''), '0'), '.').' × '
                    .rtrim(rtrim(number_format($wid, 1, '.', ''), '0'), '.').' ft'
                : '');

        $height = round((float) ($scope['wall_height_ft'] ?? $inputs['wall_height_ft'] ?? 0), 2);
        if ($height > 0) {
            $lines[] = 'Wall height: '.$height.' ft';
        }

        $foundation = \App\Services\ShedQuotationCalculator::foundationLabel(
            (string) ($inputs['foundation'] ?? 'skids')
        );
        $lines[] = 'Foundation: '.$foundation;

        $roof = \App\Services\ShedQuotationCalculator::roofLabel(
            (string) ($inputs['roof_type'] ?? 'asphalt')
        );
        $siding = \App\Services\ShedQuotationCalculator::sidingLabel(
            (string) ($inputs['siding'] ?? 'vinyl')
        );
        $lines[] = 'Siding: '.$siding.'; roof: '.$roof;

        $finish = (($inputs['interior_finish'] ?? 'rough') === 'finished')
            ? 'Fully finished (insulation and drywall)'
            : 'Rough / shell';
        $lines[] = 'Interior: '.$finish;

        $warnings = is_array($scope['permit_warnings'] ?? null) ? $scope['permit_warnings'] : [];
        foreach ($warnings as $warning) {
            $text = trim((string) $warning);
            if ($text !== '') {
                $lines[] = 'Permit note: '.$text;
            }
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
            ->where('quote_number', 'like', "SH-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('SH-%d-%04d', $year, $next);
    }
}
