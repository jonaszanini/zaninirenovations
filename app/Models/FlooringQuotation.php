<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlooringQuotation extends Model
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
        'version',
        'linked_at',
    ];

    protected $casts = [
        'inputs' => 'array',
        'calculation' => 'array',
        'is_draft' => 'boolean',
        'final_quote_total' => 'decimal:2',
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

    public function flooringType(): ?FlooringType
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $id = (int) ($inputs['flooring_type_id'] ?? 0);

        return $id > 0 ? FlooringType::query()->find($id) : null;
    }

    public function displayTitle(): string
    {
        return 'Flooring — '.$this->quote_number;
    }

    public function squareFootage(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        return round((float) ($scope['square_footage'] ?? 0), 2);
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
        $type = $this->flooringType();
        $typeName = $type?->name ?? 'Flooring';
        $sqft = $this->squareFootage();

        return sprintf(
            'Flooring installation — %s sq ft (%s)',
            number_format($sqft, 0),
            $typeName
        );
    }

    /**
     * @return array{
     *   name: string,
     *   color: string,
     *   url: string,
     *   cost_per_sqft: float,
     *   waste_percent: float,
     *   delivery_fee: float,
     *   total_pre_tax: float,
     *   square_footage: float
     * }|null
     */
    public function materialSelectionForPdf(): ?array
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        if (empty($inputs['include_material_details'])) {
            return null;
        }

        $name = trim((string) ($inputs['material_name'] ?? ''));
        $color = trim((string) ($inputs['material_color'] ?? ''));
        $url = trim((string) ($inputs['material_url'] ?? ''));
        $cost = round(max(0, (float) ($inputs['material_cost_per_sqft'] ?? 0)), 4);
        $wastePercent = round(max(0, (float) ($inputs['material_waste_percent'] ?? 5)), 2);
        $deliveryFee = round(max(0, (float) ($inputs['material_delivery_fee'] ?? 60)), 2);
        $sqft = round(max(0, (float) ($inputs['square_footage'] ?? 0)), 2);
        $baseAmount = round($sqft * $cost, 2);
        $wasteAmount = round($baseAmount * ($wastePercent / 100), 2);
        $totalPreTax = round($baseAmount + $wasteAmount + $deliveryFee, 2);

        if ($name === '' && $color === '' && $url === '' && $cost <= 0) {
            return null;
        }

        return [
            'name' => $name,
            'color' => $color,
            'url' => $url,
            'cost_per_sqft' => $cost,
            'waste_percent' => $wastePercent,
            'delivery_fee' => $deliveryFee,
            'total_pre_tax' => $totalPreTax,
            'square_footage' => $sqft,
        ];
    }

    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        $lines = [];
        $sqft = round((float) ($scope['square_footage'] ?? $inputs['square_footage'] ?? 0), 0);
        $typeName = trim((string) ($scope['flooring_type_name'] ?? ''));
        if ($typeName !== '') {
            $lines[] = "Flooring type: {$typeName}";
        }
        $lines[] = 'Total area: '.number_format($sqft, 0).' sq ft';

        foreach ($calc['breakdown'] ?? [] as $row) {
            if (! is_array($row) || ($row['section'] ?? '') !== 'labor') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $detail = trim((string) ($row['detail'] ?? ''));
            if ($label !== '') {
                $lines[] = '  • '.$label.($detail !== '' ? ' ('.$detail.')' : '');
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
            ->where('quote_number', 'like', "FL-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('FL-%d-%04d', $year, $next);
    }
}
