<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProjectQuotation — structured quotation/estimate attached to a
 * ClientProject. Line items are synced from Fence / Standard / Paving / Stairs
 * calculators; legacy manual rows remain as read-only snapshots.
 *
 * A project may have multiple quotations. Use {@see ClientProject::quotations()}.
 */
class ClientProjectQuotation extends Model
{
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_ESTIMATE = 'estimate';

    public const HST_RATE = 0.13;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    /** Legacy: line items were edited on the project before calculator-only flow. */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FENCE = 'fence';

    public const SOURCE_PAVING = 'paving';

    public const SOURCE_STANDARD = 'standard';

    public const SOURCE_STAIRS = 'stairs';

    protected $fillable = [
        'client_project_id',
        'title',
        'status',
        'total_amount',
        'subtotal_override',
        'source_type',
        'source_id',
        'source_snapshot',
        'type',
        'labour',
        'material',
        'items',
        'work_description',
        'quotation_number',
        'pdf_path',
        'sent_at',
        'version',
    ];

    protected $casts = [
        'labour' => 'boolean',
        'material' => 'boolean',
        'items' => 'array',
        'source_snapshot' => 'array',
        'sent_at' => 'datetime',
        'version' => 'integer',
        'total_amount' => 'decimal:2',
        'subtotal_override' => 'decimal:2',
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

        static::saving(function (self $quotation): void {
            if ($quotation->hasContent()) {
                $quotation->total_amount = $quotation->grandTotal();
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

    /**
     * Rows created by the old manual line-item editor (`source_type` null
     * or explicit `manual`). Read-only in the admin UI; edit in calculators.
     */
    public function isLegacyManualSource(): bool
    {
        return $this->source_type === null || $this->source_type === self::SOURCE_MANUAL;
    }

    /**
     * Swap QUO ↔ EST prefix before the first send; same rule as the old
     * project editor.
     */
    public static function swapNumberPrefixForType(?string $number, string $newType): string
    {
        if (! $number) {
            return static::generateNumber($newType);
        }
        $newPrefix = $newType === self::TYPE_ESTIMATE ? 'EST' : 'QUO';

        return preg_replace('/^[A-Z]+/', $newPrefix, $number);
    }

    /** Human-friendly type label ("Quotation" or "Estimate"). */
    public function typeLabel(): string
    {
        return $this->isEstimate() ? 'Estimate' : 'Quotation';
    }

    /**
     * Heading line from the linked {@see StandardQuotation}. Prefer
     * {@see $source_snapshot} when in sync; otherwise read the live SQ row
     * (e.g. after a DB backfill before the next save).
     */
    public function standardDisplayTitle(): ?string
    {
        if ($this->source_type !== self::SOURCE_STANDARD) {
            return null;
        }
        $snap = $this->source_snapshot;
        if (is_array($snap)) {
            $t = trim((string) ($snap['display_title'] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        $sourceId = (int) ($this->source_id ?? 0);
        if ($sourceId <= 0) {
            return null;
        }
        $t = trim((string) (StandardQuotation::query()->whereKey($sourceId)->value('display_title') ?? ''));

        return $t !== '' ? $t : null;
    }

    /**
     * Card heading for linked calculator rows. Stairs titles follow the selected material
     * (e.g. "Vinyl Stairs — ST-2026-0163"); other sources use the stored title column.
     */
    public function resolvedTitle(): string
    {
        if ($this->source_type === self::SOURCE_STAIRS && $this->source_id) {
            $stairs = StairsQuotation::query()->find($this->source_id);
            if ($stairs) {
                return $stairs->displayTitle();
            }
        }

        $stored = trim((string) ($this->title ?? ''));

        return $stored !== '' ? $stored : $this->typeLabel();
    }

    /** Document number prefix matching the current type. */
    public function numberPrefix(): string
    {
        return $this->isEstimate() ? 'EST' : 'QUO';
    }

    /** Remove internal calculator cross-reference suffixes from client-facing text. */
    public static function stripCalculatorReferenceSuffix(string $description): string
    {
        return trim((string) preg_replace('/\s*\(see\s+[A-Z]{2,}-\d{4}-\d+\)\s*$/iu', '', $description));
    }

    /** Sanitised line items: drops blank rows and coerces amounts to float. */
    public function cleanItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        $clean = [];
        foreach ($items as $row) {
            $description = self::stripCalculatorReferenceSuffix(trim((string) ($row['description'] ?? '')));
            $amount = (float) ($row['amount'] ?? 0);
            // Skip rows that are completely empty (no text AND zero amount).
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $applyHst = !array_key_exists('apply_hst', $row)
                || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);

            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
                'apply_hst' => $applyHst,
            ];
        }

        return $clean;
    }

    /** True iff the quotation has at least one usable line item. */
    public function hasContent(): bool
    {
        return count($this->cleanItems()) > 0;
    }

    /**
     * Sum of line-item pre-tax amounts (ignores {@see $subtotal_override}).
     */
    public function lineItemsSubtotal(): float
    {
        return round(array_sum(array_column($this->cleanItems(), 'amount')), 2);
    }

    /**
     * When non-null and not 0.00, {@see subtotal()} and {@see hst()} use this
     * instead of the raw line sum (HST is scaled to preserve taxable vs exempt mix).
     */
    public function hasSubtotalOverrideActive(): bool
    {
        if ($this->subtotal_override === null) {
            return false;
        }

        return round((float) $this->subtotal_override, 2) != 0.0;
    }

    /** Effective pre-tax subtotal (override or sum of lines). */
    public function subtotal(): float
    {
        if ($this->hasSubtotalOverrideActive()) {
            return round((float) $this->subtotal_override, 2);
        }

        return $this->lineItemsSubtotal();
    }

    /** 13% HST only on rows where `apply_hst` is true (default). */
    public function hst(): float
    {
        $items = $this->cleanItems();

        if (! $this->hasSubtotalOverrideActive()) {
            $sum = 0.0;
            foreach ($items as $row) {
                if (empty($row['apply_hst'])) {
                    continue;
                }
                $sum += (float) ($row['amount'] ?? 0) * self::HST_RATE;
            }

            return round($sum, 2);
        }

        $lineSub = $this->lineItemsSubtotal();
        $override = round((float) $this->subtotal_override, 2);

        if ($lineSub > 0.00001) {
            $ratio = $override / $lineSub;
            $sum = 0.0;
            foreach ($items as $row) {
                if (empty($row['apply_hst'])) {
                    continue;
                }
                $sum += (float) ($row['amount'] ?? 0) * $ratio * self::HST_RATE;
            }

            return round($sum, 2);
        }

        $anyTaxable = false;
        foreach ($items as $row) {
            if (! empty($row['apply_hst'])) {
                $anyTaxable = true;
                break;
            }
        }

        if ($anyTaxable) {
            return round($override * self::HST_RATE, 2);
        }

        return 0.0;
    }

    public function grandTotal(): float
    {
        return round($this->subtotal() + $this->hst(), 2);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Draft',
        };
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Delete this row and the linked Fence / Standard / Paving / Stairs calculator
     * record. Does nothing for legacy manual documents — callers must guard.
     */
    public static function deleteWithLinkedCalculatorSource(self $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            $projectId = (int) $quotation->client_project_id;
            $sourceType = $quotation->source_type;
            $sourceId = (int) ($quotation->source_id ?? 0);

            $quotation->delete();

            if ($sourceId > 0) {
                match ($sourceType) {
                    self::SOURCE_FENCE => FenceQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_STANDARD => StandardQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_PAVING => PavingQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_STAIRS => StairsQuotation::query()->whereKey($sourceId)->delete(),
                    default => null,
                };
            }

            $project = ClientProject::query()->find($projectId);
            if ($project && ! $project->isHourly()) {
                $project->syncFixedContractTotalsFromQuotation();
            }
        });
    }

    /** Sum of accepted quotations' stored total_amount (grand total, incl. line HST). */
    public static function acceptedGrandTotalForProject(ClientProject $project): float
    {
        return round(
            (float) static::query()
                ->where('client_project_id', $project->id)
                ->where('status', self::STATUS_ACCEPTED)
                ->sum('total_amount'),
            2
        );
    }

    /** HST amount attributed to a single cleaned row (for PDFs / previews). */
    public static function lineHstAmount(array $row): float
    {
        if (empty($row['apply_hst'])) {
            return 0.0;
        }

        return round((float) ($row['amount'] ?? 0) * self::HST_RATE, 2);
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
