<?php

namespace App\Models;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * to the client; the linked installment (if any) is automatically
 * marked paid on the Financials payment plan.
 */
class ClientProjectInvoice extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';

    public const HST_RATE = 0.13;

    /** Default due period (days after send) when not explicitly set. */
    public const DEFAULT_DUE_DAYS = 10;

    protected $fillable = [
        'client_project_id',
        'installment_number',
        'installment_description',
        'invoice_number',
        'items',
        'subtotal',
        'hst',
        'total',
        'status',
        'sent_at',
        'due_on',
        'paid_at',
        'paid_amount',
        'invoice_pdf_path',
        'receipt_pdf_path',
        'version',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'hst' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_on' => 'date',
        'version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
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

    // --- Item helpers -----------------------------------------------------

    /** Sanitised line items: drops blank rows and coerces amounts to float. */
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
            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
            ];
        }

        return $clean;
    }

    public function hasContent(): bool
    {
        return count($this->cleanItems()) > 0;
    }

    public function computedSubtotal(): float
    {
        return round(array_sum(array_column($this->cleanItems(), 'amount')), 2);
    }

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
