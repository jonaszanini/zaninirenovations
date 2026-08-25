<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasementQuotation extends Model
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

        return round((float) ($scope['area_sqft'] ?? $inputs['area_sqft'] ?? 0), 2);
    }

    public function displayTitle(): string
    {
        return 'Basement Renovation — '.$this->quote_number;
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
        $area = $this->areaSqft();
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $bath = ! empty($inputs['include_bathroom']);
        $finish = $bath ? 'Full finish with bathroom' : 'Full finish';

        if ($area > 0) {
            return sprintf('Basement Renovation – %s sq ft, %s', number_format($area, 0), $finish);
        }

        return 'Basement Renovation – '.$finish;
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
        $area = round((float) ($scope['area_sqft'] ?? $inputs['area_sqft'] ?? 0), 0);
        $height = round((float) ($scope['ceiling_height_ft'] ?? $inputs['ceiling_height_ft'] ?? 0), 2);
        $rooms = (int) ($inputs['room_count'] ?? 0);

        $lines[] = 'Basement renovation'.($area > 0 ? ' — '.number_format($area, 0).' sq ft' : '');
        if ($height > 0) {
            $lines[] = 'Ceiling height: '.$height.' ft';
        }
        if ($rooms > 0) {
            $lines[] = 'Rooms / zones: '.$rooms;
        }
        if (! empty($inputs['include_bathroom'])) {
            $type = ($inputs['bath_type'] ?? '') === 'half' ? 'Half bath' : 'Full bath';
            $lines[] = 'Bathroom: '.$type;
        }

        $notes = trim((string) ($inputs['existing_notes'] ?? ''));
        if ($notes !== '') {
            $lines[] = 'Existing conditions: '.$notes;
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
            ->where('quote_number', 'like', "BR-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('BR-%d-%04d', $year, $next);
    }
}
