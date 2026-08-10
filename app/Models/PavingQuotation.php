<?php

namespace App\Models;

use App\Services\PavingStoneEntryResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PavingQuotation extends Model
{
    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'work_description',
        'payment_installments',
        'payment_percentages',
        'is_draft',
        'inputs',
        'calculation',
        'final_quote_total',
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

    public function summaryDescription(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $area = (float) ($inputs['total_area_sq_ft'] ?? 0);
        $entries = PavingStoneEntryResolver::normalize($inputs);
        $stoneNames = array_values(array_filter(array_map(
            static fn (array $entry) => trim((string) ($entry['stone_name'] ?? '')),
            $entries
        )));
        $stoneLabel = $stoneNames !== []
            ? implode(' + ', $stoneNames)
            : 'Paving stones';

        return sprintf(
            'Paving Stones — %s sq ft %s',
            rtrim(rtrim(number_format($area, 2), '0'), '.'),
            $stoneLabel
        );
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "PQ-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('PQ-%d-%04d', $year, $next);
    }
}
