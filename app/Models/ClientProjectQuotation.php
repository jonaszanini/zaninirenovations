<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProjectQuotation — structured quotation/estimate attached to a
 * ClientProject. Unlike the rich-text Contract, the body is structured
 * data (line items) so the PDF can be auto-rendered and totals
 * (subtotal / HST / grand total) auto-calculated.
 *
 * One quotation per project (unique on client_project_id).
 */
class ClientProjectQuotation extends Model
{
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_ESTIMATE = 'estimate';

    public const HST_RATE = 0.13;

    protected $fillable = [
        'client_project_id',
        'type',
        'labour',
        'material',
        'items',
        'quotation_number',
        'pdf_path',
        'sent_at',
        'version',
    ];

    protected $casts = [
        'labour' => 'boolean',
        'material' => 'boolean',
        'items' => 'array',
        'sent_at' => 'datetime',
        'version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /**
     * Defensive cleanup: when a quotation row is deleted directly (i.e. not
     * through the parent project's cascade, which doesn't fire model events),
     * also remove the latest PDF on disk.
     *
     * The parent ClientProject's deleting hook handles every version when a
     * whole project is deleted; this only ensures we don't orphan a single
     * file when a quotation is deleted on its own.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $quotation): void {
            if (filled($quotation->pdf_path)) {
                Storage::disk('local')->delete($quotation->pdf_path);
            }
        });
    }

    /** Has the quotation been emailed to the client at least once? */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isEstimate(): bool
    {
        return $this->type === self::TYPE_ESTIMATE;
    }

    /** Human-friendly type label ("Quotation" or "Estimate"). */
    public function typeLabel(): string
    {
        return $this->isEstimate() ? 'Estimate' : 'Quotation';
    }

    /** Document number prefix matching the current type. */
    public function numberPrefix(): string
    {
        return $this->isEstimate() ? 'EST' : 'QUO';
    }

    /** Sanitised line items: drops blank rows and coerces amounts to float. */
    public function cleanItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        $clean = [];
        foreach ($items as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            // Skip rows that are completely empty (no text AND zero amount).
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
            ];
        }

        return $clean;
    }

    /** True iff the quotation has at least one usable line item. */
    public function hasContent(): bool
    {
        return count($this->cleanItems()) > 0;
    }

    public function subtotal(): float
    {
        return round(array_sum(array_column($this->cleanItems(), 'amount')), 2);
    }

    public function hst(): float
    {
        return round($this->subtotal() * self::HST_RATE, 2);
    }

    public function grandTotal(): float
    {
        return round($this->subtotal() + $this->hst(), 2);
    }

    /**
     * Intro phrase printed before the items table, derived from the
     * labour/material checkboxes:
     *   - both   → "To supply material and labour of the following:"
     *   - labour → "To labour of the following:"
     *   - mat.   → "To supply material of the following:"
     *   - none   → fallback (UI also warns when neither is checked)
     */
    public function introPhrase(): string
    {
        if ($this->labour && $this->material) {
            return 'To supply material and labour of the following:';
        }
        if ($this->labour) {
            return 'To labour of the following:';
        }
        if ($this->material) {
            return 'To supply material of the following:';
        }

        return 'For the following scope of work:';
    }

    /**
     * Generate a unique number like "QUO-2026-0327" or "EST-2026-0327".
     *
     * Numeric suffix is shared across both prefixes per year, so changing
     * the type later only swaps the prefix without colliding.
     *
     * The very first number issued in a given year starts at a random offset
     * (150–500) so a brand-new client doesn't see "0001" and assume we're
     * just getting started.
     */
    public static function generateNumber(string $type): string
    {
        $prefix = $type === self::TYPE_ESTIMATE ? 'EST' : 'QUO';
        $year = (int) date('Y');

        // Find the highest numeric suffix used this year across both prefixes.
        $latest = self::query()
            ->where(function ($q) use ($year) {
                $q->where('quotation_number', 'like', "QUO-{$year}-%")
                    ->orWhere('quotation_number', 'like', "EST-{$year}-%");
            })
            ->orderByDesc('quotation_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quotation_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(150, 500);
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }
}
