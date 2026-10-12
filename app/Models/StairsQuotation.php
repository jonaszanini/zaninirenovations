<?php

namespace App\Models;

use App\Services\StairsQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StairsQuotation extends Model
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
        'additional_costs',
        'final_quote_total',
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

    public function materialType(): ?StairsMaterialType
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $mid = (int) ($inputs['material_type_id'] ?? 0);

        return $mid > 0 ? StairsMaterialType::query()->find($mid) : null;
    }

    /** Project quotation card title, e.g. "Vinyl Stairs — ST-2026-0163". */
    public function displayTitle(): string
    {
        $material = $this->materialType();
        $label = $material ? $material->quotationDisplayName() : 'Stairs';

        return $label.' — '.$this->quote_number;
    }

    public function materialPriceAmount(): float
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];

        return max(0, round((float) ($inputs['material_price'] ?? 0), 2));
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialPriceAmount() > 0;
    }

    /** Pre-HST labour-only total (excludes optional materials line). */
    public function labourPreTaxAmountForProjectQuotation(): float
    {
        return max(0, round($this->preTaxAmountForProjectQuotation() - $this->materialPriceAmount(), 2));
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

    public function summaryDescription(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $steps = (int) ($inputs['total_steps'] ?? 0);
        $material = $this->materialType();
        $matName = $material ? $material->name : 'Stairs';

        $legacy = $inputs['open_sides'] ?? null;
        $legacy = is_string($legacy) ? $legacy : null;
        $segments = is_array($inputs['segments'] ?? null) ? $inputs['segments'] : [];
        $openKeys = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $qty = max(0, (int) ($seg['qty'] ?? 0));
            if ($qty < 1) {
                continue;
            }
            $openKeys[] = StairsQuotationCalculator::normalizeSegmentOpenSidesForMaterial($seg, $material, $legacy);
        }
        $openKeys = array_values(array_unique($openKeys));
        if (count($openKeys) === 0) {
            $openPhrase = StairsQuotationCalculator::openSidesLabel(StairsQuotationCalculator::normalizeSegmentOpenSides([], $legacy));
        } elseif (count($openKeys) === 1) {
            $openPhrase = StairsQuotationCalculator::openSidesLabel($openKeys[0]);
        } else {
            $openPhrase = 'Mixed open-side sections';
        }

        return sprintf(
            'Stairs renovation — %d steps, %s, %s',
            $steps,
            $matName,
            $openPhrase
        );
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "ST-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('ST-%d-%04d', $year, $next);
    }
}
