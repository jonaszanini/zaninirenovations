<?php

namespace App\Models;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProjectInvoice — structured invoice attached to a ClientProject.
 *
 * Mirrors ClientProjectQuotation: structured line items stored as JSON,
 * auto-numbered (INV-YYYY-NNNN), PDF generated on demand. The big
 * difference is that multiple invoices are allowed per project — one
 * per installment in the Financials payment plan, plus an arbitrary
 * number of manual (non-installment) invoices.
 *
 * When marked as paid, a separate Receipt PDF is generated and emailed
 * to the client; the linked installment (if any) is updated to the
 * invoice’s billed pre-tax subtotal and marked paid on the Financials
 * payment plan.
 */
class ClientProjectInvoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';

    public const HST_RATE = 0.13;

    /** Payment methods captured when the invoice is marked paid. */
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_ETRANSFER = 'etransfer';

    /** Display labels for the receipt PDF + admin UI. */
    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Cash',
        self::PAYMENT_METHOD_ETRANSFER => 'e-Transfer',
    ];

    /** Default due period (days after send) when not explicitly set. */
    public const DEFAULT_DUE_DAYS = 10;

    /** Invoice line maps to hourly labour — HST applies. */
    public const LINE_KIND_LABOUR = 'labour';

    /** Invoice line maps to materials; amount is stored tax-included; no extra HST. */
    public const LINE_KIND_MATERIALS = 'materials';

    /** Pre-tax labour deduction (e.g. prepaid toward estimate). Amount stored positive; subtracts from labour for HST. */
    public const LINE_KIND_LABOUR_CREDIT = 'labour_credit';

    /** Gas expense. Amount is stored tax-included; no extra HST. */
    public const LINE_KIND_GAS = 'gas';

    protected $fillable = [
        'client_project_id',
        'installment_number',
        'installment_description',
        'invoice_number',
        'items',
        'subtotal',
        'hst',
        'hst_exempt',
        'total',
        'discount_percent',
        'discount_amount',
        'discount_comment',
        'status',
        'sent_at',
        'due_on',
        'paid_at',
        'paid_amount',
        'payment_method',
        'invoice_pdf_path',
        'receipt_pdf_path',
        'version',
        'included_project_quotation_ids',
        'included_quotations_snapshot',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'hst' => 'decimal:2',
        'hst_exempt' => 'boolean',
        'total' => 'decimal:2',
        'discount_percent' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_on' => 'date',
        'version' => 'integer',
        'included_project_quotation_ids' => 'array',
        'included_quotations_snapshot' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /**
     * Time entries snapshotted onto this invoice. Only populated for
     * "hourly" invoices (hourly-billed projects where the admin clicks
     * "Create invoice from hours"). Empty for installment / manual
     * invoices.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(ClientProjectTimeEntry::class, 'client_project_invoice_id')
            ->orderBy('occurred_on');
    }

    /**
     * Expense rows pulled onto an hourly invoice as materials lines (snapshot).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(ClientProjectExpense::class, 'client_project_invoice_id');
    }

    /**
     * True if this invoice was built from time entries (regardless of
     * whether the user has since edited the line items).
     */
    public function isFromTimeEntries(): bool
    {
        // relationLoaded first to avoid an extra query when the caller
        // has eager-loaded the relation (most view paths do).
        if ($this->relationLoaded('timeEntries')) {
            return $this->timeEntries->isNotEmpty();
        }

        return $this->timeEntries()->exists();
    }

    /**
     * Defensive cleanup when an invoice row is deleted directly. The
     * parent ClientProject's deleting hook sweeps the whole
     * storage/app/invoices (and /receipts) folder for this project, so
     * this is only needed to avoid orphaning the currently-stored PDFs
     * when a single invoice is deleted on its own.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $invoice): void {
            if (filled($invoice->invoice_pdf_path)) {
                Storage::disk('local')->delete($invoice->invoice_pdf_path);
            }
            if (filled($invoice->receipt_pdf_path)) {
                Storage::disk('local')->delete($invoice->receipt_pdf_path);
            }
        });
    }

    // --- Status helpers ---------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Has the invoice been emailed to the client at least once? */
    public function wasEverSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** Not tied to any Financials installment — user-supplied amount. */
    public function isManual(): bool
    {
        return $this->installment_number === null;
    }

    /**
     * True when the admin has flagged this invoice as HST-exempt (typically
     * for cash payments). Side effects: HST is forced to $0 and the total
     * collapses to the subtotal at save time, the PDF / receipt hide the
     * HST row, and the editor swaps the breakdown to a "no HST" line.
     */
    public function isHstExempt(): bool
    {
        return (bool) $this->hst_exempt;
    }

    /**
     * Friendly label for the payment method ("Cash", "e-Transfer", …).
     * Returns null if the invoice was paid before this field existed or
     * the method wasn't captured at mark-paid time.
     */
    public function paymentMethodLabel(): ?string
    {
        if (!$this->payment_method) {
            return null;
        }

        return self::PAYMENT_METHOD_LABELS[$this->payment_method]
            ?? ucfirst($this->payment_method);
    }

    // --- Item helpers -----------------------------------------------------

    /** Sanitised line items: drops blank rows; preserves `line_kind` and `expense_id` for hourly. */
    public function cleanItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        $clean = [];
        foreach ($items as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($description === '' && $amount == 0.0) {
                continue;
            }

            $lineKind = strtolower((string) ($row['line_kind'] ?? self::LINE_KIND_LABOUR));
            if ($lineKind === self::LINE_KIND_LABOUR_CREDIT) {
                $lineKind = self::LINE_KIND_LABOUR_CREDIT;
            } elseif ($lineKind === self::LINE_KIND_MATERIALS) {
                $lineKind = self::LINE_KIND_MATERIALS;
            } elseif ($lineKind === self::LINE_KIND_GAS) {
                $lineKind = self::LINE_KIND_GAS;
            } else {
                $lineKind = self::LINE_KIND_LABOUR;
            }

            $out = [
                'description' => $description,
                'amount' => round($amount, 2),
                'line_kind' => $lineKind,
            ];
            if ($lineKind === self::LINE_KIND_MATERIALS && ! empty($row['expense_id'])) {
                $out['expense_id'] = (int) $row['expense_id'];
            }
            $clean[] = $out;
        }

        return $clean;
    }

    public function hasContent(): bool
    {
        return count($this->cleanItems()) > 0;
    }

    /** Pre-HST labour subtotal from line items (hourly split). */
    public function hourlyLabourTotalFromItems(): float
    {
        $sum = 0.0;
        foreach ($this->cleanItems() as $row) {
            $kind = $row['line_kind'] ?? self::LINE_KIND_LABOUR;
            if ($kind === self::LINE_KIND_MATERIALS || $kind === self::LINE_KIND_GAS) {
                continue;
            }
            $amt = (float) ($row['amount'] ?? 0);
            if ($kind === self::LINE_KIND_LABOUR_CREDIT) {
                $sum -= abs($amt);
            } else {
                $sum += $amt;
            }
        }

        return round($sum, 2);
    }

    /** Materials bucket: amounts are tax-included (hourly). */
    public function hourlyMaterialsTotalFromItems(): float
    {
        $sum = 0.0;
        foreach ($this->cleanItems() as $row) {
            $kind = $row['line_kind'] ?? self::LINE_KIND_LABOUR;
            if ($kind === self::LINE_KIND_MATERIALS || $kind === self::LINE_KIND_GAS) {
                $sum += (float) ($row['amount'] ?? 0);
            }
        }

        return round($sum, 2);
    }

    /**
     * @deprecated Use persisted subtotal/hst/total; kept for simple non-hourly checks.
     */
    public function computedSubtotal(): float
    {
        return round(array_sum(array_column($this->cleanItems(), 'amount')), 2);
    }

    /** Sum of line-item amounts (pre-discount). */
    public function grossLineSubtotal(): float
    {
        return $this->computedSubtotal();
    }

    /**
     * Pre-tax discount for installment-linked invoices (percentage or fixed amount).
     */
    public function effectiveDiscountAmount(): float
    {
        if ($this->isManual() || $this->installment_number === null) {
            return 0.0;
        }

        $gross = $this->grossLineSubtotal();
        $pct = (float) ($this->discount_percent ?? 0);

        if ($pct > 0.00001) {
            return round(min($gross * $pct / 100, $gross), 2);
        }

        $amt = (float) ($this->discount_amount ?? 0);

        return round(min($amt, $gross), 2);
    }

    /**
     * PDF label for the discount row: default "Discount", or the saved comment verbatim.
     */
    public function discountPdfLabel(): string
    {
        $c = trim((string) ($this->discount_comment ?? ''));

        return $c !== '' ? $c : 'Discount';
    }

    /**
     * Label for the HST row on invoice / receipt PDFs.
     * Manual invoices use flat 13% on the subtotal; installment invoices use
     * the accepted quotation’s tax mix (scaled), which is not always 13% × the
     * amount shown — so we avoid implying "13% of subtotal" on those PDFs.
     */
    public function hstPdfRowLabel(): string
    {
        return $this->isManual() ? 'HST (13%)' : 'HST (from accepted quotation)';
    }

    /**
     * @deprecated Applies 13% to full line sum — wrong for hourly materials split.
     */
    public function computedHst(): float
    {
        return round($this->computedSubtotal() * self::HST_RATE, 2);
    }

    public function computedTotal(): float
    {
        return round($this->computedSubtotal() + $this->computedHst(), 2);
    }

    /**
     * Friendly title used in UI + PDF, e.g.
     *   "1st Installment — On signing (holds the schedule)"
     *   "Manual invoice"
     */
    public function installmentTitle(): string
    {
        if ($this->isManual()) {
            return $this->installment_description ?: 'Manual invoice';
        }

        $ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];
        $ord = $ordinals[$this->installment_number] ?? ($this->installment_number.'th');
        $desc = $this->installment_description ?: '';

        return $desc
            ? "{$ord} Installment — {$desc}"
            : "{$ord} Installment";
    }

    /** Status badge copy for the UI ("No invoice" / "Draft" / "Sent" / "Paid"). */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            default => 'No Invoice',
        };
    }

    /** Status badge color hex. */
    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => '#f59e0b',    // amber
            self::STATUS_SENT => '#3b82f6',     // blue
            self::STATUS_PAID => '#22c55e',     // green
            default => '#9ca3af',               // slate
        };
    }

    // --- Numbering --------------------------------------------------------

    /**
     * Generate a unique invoice number like "INV-2026-0001". Sequential
     * per year, independent of any other document type. First number of
     * a brand-new year starts at a small random offset so the very first
     * client doesn't see "#0001".
     */
    public static function generateNumber(): string
    {
        $year = (int) date('Y');

        $latest = self::query()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->orderByDesc('invoice_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->invoice_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('INV-%d-%04d', $year, $next);
    }

    /**
     * Canonical description for the N-th installment of a given payment
     * plan. Proxies to the Financials controller so there is a single
     * source of truth — changing plan wording only happens in one place.
     */
    public static function installmentDescriptionFor(int $plan, int $installmentNumber): string
    {
        // installment_number is 1-based in the UI, but the controller
        // helper expects a 0-based sort_order index.
        return ClientProjectFinanceController::installmentLabel($plan, $installmentNumber - 1);
    }
}
