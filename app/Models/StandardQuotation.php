<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StandardQuotation — standalone materials + labour estimate.
 *
 * Linking appends one line to the project's ClientProjectQuotation with
 * amount = grand_total and apply_hst = false (total already includes
 * material HST; labour is untaxed).
 */
class StandardQuotation extends Model
{
    public const HST_RATE = 0.13;

    protected $fillable = [
        'client_project_id',
        'document_type',
        'quote_number',
        'client_name',
        'work_description',
        'includes_material',
        'includes_labour',
        'materials',
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
        'grand_total',
        'contingency_enabled',
        'contingency_percent',
        'contingency_amount',
        'contingency_hst',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'includes_material' => 'boolean',
        'includes_labour' => 'boolean',
        'materials' => 'array',
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
        'grand_total' => 'decimal:2',
        'contingency_enabled' => 'boolean',
        'contingency_percent' => 'decimal:2',
        'contingency_amount' => 'decimal:2',
        'contingency_hst' => 'decimal:2',
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
}
