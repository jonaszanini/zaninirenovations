<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TileQuotation extends Model
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

    public function displayTitle(): string
    {
        return 'Tile — '.$this->quote_number;
    }

    public function squareFootage(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        return round((float) ($calc['total_area'] ?? 0), 2);
    }

    public function labourPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        return max(0, round((float) ($calc['labor_total'] ?? 0), 2));
    }

    public function materialsPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        
        $mortar = (float) ($calc['mortar_total_price'] ?? 0);
        $grout = (float) ($calc['grout_total_price'] ?? 0);
        $tile = (float) ($calc['tile_total_price'] ?? 0);
        
        return max(0, round($mortar + $grout + $tile, 2));
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0;
    }

    public function summaryDescription(): string
    {
        $sqft = $this->squareFootage();
        return sprintf('Tile installation — %s sq ft', number_format($sqft, 0));
    }

    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];

        $lines = [];
        $sqft = round((float) ($calc['total_area'] ?? 0), 0);
        $lines[] = 'Total area: '.number_format($sqft, 0).' sq ft';

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
            ->where('quote_number', 'like', "TL-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('TL-%d-%04d', $year, $next);
    }
}
