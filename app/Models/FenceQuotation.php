<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FenceQuotation — a saved fence calculator result.
 *
 * Stores both the raw inputs (so the quote can be re-opened and edited)
 * and a snapshot of the calculated breakdown (so older quotes keep the
 * same totals even after material prices change).
 *
 * The module deliberately has NO PDF of its own — the client-facing
 * document is always the project's main Quotation PDF. Linking a
 * fence quote to a project appends a single line item (description +
 * pre-tax amount) to that project's ClientProjectQuotation, which
 * then renders/sends through the existing flow.
 */
class FenceQuotation extends Model
{
    public const HST_RATE = 0.13;
    public const BOARD_STYLE_STANDARD = 'standard';
    public const BOARD_STYLE_OVERLAPPING = 'overlapping';

    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'fence_height_ft',
        'board_style',
        'overlap_in',
        'gate_count',
        'labour_rate_per_ft',
        'labour_rate_8ft_per_ft',
        'sides',
        'calculation',
        'materials_subtotal',
        'hst',
        'labour_total',
        'grand_total',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'sides' => 'array',
        'calculation' => 'array',
        'fence_height_ft' => 'decimal:2',
        'overlap_in' => 'decimal:3',
        'gate_count' => 'integer',
        'labour_rate_per_ft' => 'decimal:2',
        'labour_rate_8ft_per_ft' => 'decimal:2',
        'materials_subtotal' => 'decimal:2',
        'hst' => 'decimal:2',
        'labour_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'version' => 'integer',
        'linked_at' => 'datetime',
    ];

    /** Optional: the project this quote was linked into (if any). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    /**
     * Short human-friendly description used when linking a fence quote
     * into a project's main Quotation as a line item. Example:
     *   "Pressure treated wood fence 6' high — 40 linear ft, 1 gate"
     */
    public function summaryDescription(): string
    {
        $totalFt = collect($this->sides ?? [])
            ->sum(fn ($s) => (float) ($s['length_ft'] ?? 0));
        $height = (int) round((float) $this->fence_height_ft);
        $gates = (int) $this->gate_count;
        $gatesPart = $gates > 0 ? ', '.$gates.' '.\Illuminate\Support\Str::plural('gate', $gates) : '';

        return sprintf(
            "Pressure treated wood fence %d' high — %s linear ft%s",
            $height,
            rtrim(rtrim(number_format($totalFt, 2), '0'), '.'),
            $gatesPart
        );
    }

    /**
     * Generate a unique fence quote number like "FQ-2026-0001". Sequential
     * per year, independent of other document types, so the client-facing
     * identifier is short and obviously distinct from INV/QUO/CON.
     */
    public static function generateNumber(): string
    {
        $year = (int) date('Y');

        $latest = static::query()
            ->where('quote_number', 'like', "FQ-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('FQ-%d-%04d', $year, $next);
    }
}
