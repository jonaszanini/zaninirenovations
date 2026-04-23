<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProject — full business lifecycle for a renovation project
 * (from quote request through completion).
 *
 * This is distinct from the legacy Project model which only manages
 * portfolio photos shown on the public home page.
 */
class ClientProject extends Model
{
    // Canonical status keys (snake_case for DB, human labels in statusLabels()).
    public const STATUS_QUOTE_PENDING = 'quote_pending';
    public const STATUS_QUOTE_SENT = 'quote_sent';
    public const STATUS_PROPOSAL_ACCEPTED = 'proposal_accepted';
    public const STATUS_PENDING_FIRST_PAYMENT = 'pending_first_payment';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    // Canadian HST rate applied to revenue for set-aside calculation.
    public const HST_RATE = 0.13;

    // Billing modes — fixed = traditional total amount + installments;
    // hourly  = revenue is logged hours * hourly_rate.
    public const BILLING_FIXED = 'fixed';
    public const BILLING_HOURLY = 'hourly';

    protected $fillable = [
        'name',
        'client_name',
        'client_email',
        'client_email_secondary',
        'client_phone',
        'client_address',
        'notes',
        'status',
        'status_changed_at',
        'start_date',
        'end_date',
        'calendar_email_sent_at',
        'total_amount',
        'payment_plan',
        'billing_type',
        'hourly_rate',
        'last_quote_reminder_sent_at',
        'quote_sent_follow_up_due_at',
        'quote_sent_follow_up_sent_at',
        'service_confirmation_sent_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status_changed_at' => 'datetime',
        'calendar_email_sent_at' => 'datetime',
        'last_quote_reminder_sent_at' => 'datetime',
        'quote_sent_follow_up_due_at' => 'datetime',
        'quote_sent_follow_up_sent_at' => 'datetime',
        'service_confirmation_sent_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'payment_plan' => 'integer',
        'hourly_rate' => 'decimal:2',
    ];

    // --- Lifecycle hooks --------------------------------------------------

    /**
     * When a project is deleted, the FK cascade removes the contract and
     * quotation rows from the DB — but the generated PDF files on the
     * `local` disk would otherwise be orphaned forever. This hook deletes
     * every version of both documents for this project ID so we leave
     * nothing behind on the server.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $project): void {
            $project->purgeGeneratedPdfs('contracts');
            $project->purgeGeneratedPdfs('quotations');
            $project->purgeGeneratedPdfs('invoices');
            $project->purgeGeneratedPdfs('receipts');
        });
    }

    /**
     * Delete every PDF on the local disk that belongs to this project,
     * across all stored versions. File names follow the pattern
     * "{$dir}/project-{id}-v{N}.pdf" (see the contract / quotation
     * controllers' send() methods).
     */
    protected function purgeGeneratedPdfs(string $dir): void
    {
        $disk = Storage::disk('local');

        if (!$disk->exists($dir)) {
            return;
        }

        // Match any PDF whose filename starts with this project's prefix.
        // Existing patterns:
        //   contracts/quotations: "project-{id}-v{n}.pdf"
        //   invoices / receipts:  "project-{id}-installment-{N}-v{n}.pdf"
        //                         "project-{id}-manual-{invoice_id}-v{n}.pdf"
        // A leading-prefix check plus the project-scoped regex below is
        // enough to avoid nuking files for similarly-numbered projects.
        $prefix = "project-{$this->id}-";
        $pattern = '/^project-'.$this->id.'-(?:v\d+|installment-\d+-v\d+|manual-\d+-v\d+)\.pdf$/';

        foreach ($disk->files($dir) as $path) {
            $name = basename($path);
            if (str_starts_with($name, $prefix) && preg_match($pattern, $name)) {
                $disk->delete($path);
            }
        }
    }

    // --- Relationships -----------------------------------------------------

    public function installments(): HasMany
    {
        return $this->hasMany(ClientProjectInstallment::class)->orderBy('sort_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ClientProjectExpense::class)->orderBy('occurred_on');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ClientProjectReturn::class)->orderBy('occurred_on');
    }

    public function timeEntries(): HasMany
    {
        // Newest first (same day: higher id last on that day → appears first).
        return $this->hasMany(ClientProjectTimeEntry::class)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClientProjectStatusHistoryEntry::class)->orderByDesc('changed_at');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(ClientProjectContract::class);
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(ClientProjectQuotation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ClientProjectInvoice::class)
            ->orderByRaw('installment_number IS NULL') // installment-linked first, then manual
            ->orderBy('installment_number')
            ->orderBy('id');
    }

    /**
     * True iff the project has all the client info required to email a
     * client-facing document (contract or quotation): name, phone, email,
     * service address.
     */
    public function hasClientContactInfo(): bool
    {
        return filled($this->client_name)
            && filled($this->client_phone)
            && filled($this->client_email)
            && filled($this->client_address);
    }

    /** Returns a list of human labels for the missing client contact fields. */
    public function missingClientContactFields(): array
    {
        $missing = [];
        if (blank($this->client_name)) {
            $missing[] = 'Client name';
        }
        if (blank($this->client_phone)) {
            $missing[] = 'Client phone';
        }
        if (blank($this->client_email)) {
            $missing[] = 'Client email';
        }
        if (blank($this->client_address)) {
            $missing[] = 'Service address';
        }

        return $missing;
    }

    /** @deprecated Use hasClientContactInfo(); kept for the contract feature. */
    public function hasContactInfoForContract(): bool
    {
        return $this->hasClientContactInfo();
    }

    /** @deprecated Use missingClientContactFields(); kept for the contract feature. */
    public function missingContactFieldsForContract(): array
    {
        return $this->missingClientContactFields();
    }

    /**
     * The secondary email (spouse/partner) if set, normalised to lowercase
     * and trimmed. Empty when not configured. Never includes the primary
     * address even if the user accidentally typed the same value twice —
     * callers should treat it as a safe-to-CC value.
     */
    public function secondaryClientEmail(): ?string
    {
        $secondary = strtolower(trim((string) $this->client_email_secondary));
        if ($secondary === '') {
            return null;
        }

        $primary = strtolower(trim((string) $this->client_email));
        if ($primary !== '' && $secondary === $primary) {
            return null;
        }

        return $secondary;
    }

    /**
     * Human-friendly recipient list for History / success-flash copy.
     * Examples:
     *   "alice@example.com"
     *   "alice@example.com (cc bob@example.com)"
     */
    public function clientEmailRecipientsLabel(): string
    {
        $primary = (string) $this->client_email;
        $cc = $this->secondaryClientEmail();

        return $cc ? "{$primary} (cc {$cc})" : $primary;
    }

    // --- Status helpers ----------------------------------------------------

    /** All valid status keys (in workflow order). */
    public static function statuses(): array
    {
        return [
            self::STATUS_QUOTE_PENDING,
            self::STATUS_QUOTE_SENT,
            self::STATUS_PROPOSAL_ACCEPTED,
            self::STATUS_PENDING_FIRST_PAYMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_REJECTED,
        ];
    }

    /** Mapping of status key → human readable label. */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_QUOTE_PENDING => 'Quote Pending',
            self::STATUS_QUOTE_SENT => 'Quote Sent',
            self::STATUS_PROPOSAL_ACCEPTED => 'Proposal Accepted',
            self::STATUS_PENDING_FIRST_PAYMENT => 'Pending First Payment',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /** Mapping of status key → short CSS modifier used for badges/calendar colors. */
    public static function statusColors(): array
    {
        return [
            self::STATUS_QUOTE_PENDING => '#f59e0b',         // amber
            self::STATUS_QUOTE_SENT => '#3b82f6',            // blue
            self::STATUS_PROPOSAL_ACCEPTED => '#10b981',     // green
            self::STATUS_PENDING_FIRST_PAYMENT => '#f97316', // orange
            self::STATUS_SCHEDULED => '#8b5cf6',             // violet
            self::STATUS_IN_PROGRESS => '#6366f1',           // indigo
            self::STATUS_COMPLETED => '#22c55e',             // bright green
            self::STATUS_REJECTED => '#ef4444',              // red
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function statusColor(): string
    {
        return self::statusColors()[$this->status] ?? '#6b7280';
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /** True once the proposal has been accepted (unlocks financials/time). */
    public function isAccepted(): bool
    {
        return in_array($this->status, [
            self::STATUS_PROPOSAL_ACCEPTED,
            self::STATUS_PENDING_FIRST_PAYMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ], true);
    }

    /** True if the project is billed by the hour (rate × logged hours). */
    public function isHourly(): bool
    {
        return $this->billing_type === self::BILLING_HOURLY;
    }

    /**
     * First installment of the payment plan (lowest sort_order), or null
     * if the project has no installments yet (e.g. hourly-billed, or
     * financials not filled in).
     */
    public function firstInstallment(): ?ClientProjectInstallment
    {
        return $this->installments()->orderBy('sort_order')->first();
    }

    /** True once the first installment has been marked paid. */
    public function firstInstallmentPaid(): bool
    {
        $first = $this->firstInstallment();

        return $first !== null && (bool) $first->is_paid;
    }

    /** True if both start + end dates are saved. */
    public function hasSchedule(): bool
    {
        return filled($this->start_date) && filled($this->end_date);
    }

    // --- Money & profit calculations --------------------------------------

    /** Sum of expense amounts (without subtracting returns). */
    public function totalExpensesAmount(): float
    {
        return (float) $this->expenses->sum('amount');
    }

    /** Sum of return / credit amounts. */
    public function totalReturnsAmount(): float
    {
        return (float) $this->returns->sum('amount');
    }

    /** Net cost = expenses − returns. */
    public function totalSpent(): float
    {
        return max(0.0, $this->totalExpensesAmount() - $this->totalReturnsAmount());
    }

    /** Sum of paid installments (revenue recognised so far). */
    public function totalRevenueCollected(): float
    {
        if ($this->isHourly()) {
            return $this->hourlyAmountEarned();
        }

        return (float) $this->installments
            ->where('is_paid', true)
            ->sum('amount');
    }

    /** For "pay in full" plan revenue is total amount once the single installment is paid. */
    public function revenueForProfit(): float
    {
        return $this->totalRevenueCollected();
    }

    /** Money earned so far on an hourly project = rate × closed-entry hours. */
    public function hourlyAmountEarned(): float
    {
        $rate = (float) ($this->hourly_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round($rate * $this->totalHours(), 2);
    }

    public function grossProfit(): float
    {
        return $this->revenueForProfit() - $this->totalSpent();
    }

    public function hstToSave(): float
    {
        return round($this->revenueForProfit() * self::HST_RATE, 2);
    }

    public function netProfitAfterHst(): float
    {
        return $this->grossProfit() - $this->hstToSave();
    }

    /**
     * Sum of hours across closed entries only — entries with just a start
     * time but no end time (in-progress) don't count yet.
     */
    public function totalHours(): float
    {
        return (float) $this->timeEntries
            ->filter(fn ($entry) => $entry->hours !== null)
            ->sum('hours');
    }

    public function effectiveHourlyRate(): ?float
    {
        $hours = $this->totalHours();
        if ($hours <= 0) {
            return null;
        }

        return round($this->netProfitAfterHst() / $hours, 2);
    }

    public function totalPercentageAllocated(): float
    {
        return (float) $this->installments->sum('percentage');
    }

    public function remainingPercentage(): float
    {
        return round(100 - $this->totalPercentageAllocated(), 4);
    }

    public function percentPaid(): float
    {
        if (!$this->total_amount || (float) $this->total_amount <= 0) {
            return 0.0;
        }

        return round(($this->totalRevenueCollected() / (float) $this->total_amount) * 100, 2);
    }

    // --- Presentation helpers ---------------------------------------------

    public static function formatMoney(?float $amount): string
    {
        $n = (float) ($amount ?? 0);

        return 'CA$'.number_format($n, 2, '.', ',');
    }

    public static function formatDate($date): string
    {
        if (!$date) {
            return '—';
        }
        if (!$date instanceof \DateTimeInterface) {
            $date = \Illuminate\Support\Carbon::parse((string) $date);
        }

        return $date->format('d/m/Y');
    }

    /**
     * Format a phone number for display as (xxx) xxx-xxxx.
     *
     * Internally we normalise all input to a 10-digit string before saving
     * (see the client_phone mutator), so 99% of calls here will hit the
     * "10 digits" branch. The graceful fallbacks cover legacy rows that
     * existed before normalisation was introduced.
     */
    public static function formatPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return '';
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }

        // 11-digit NANP numbers like "16478347570" are stored legacy-style
        // with the leading country code — strip it and reformat.
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return self::formatPhone(substr($digits, 1));
        }

        // Anything else (shorter/longer/unusual) → return the raw value
        // as the user typed it, so we don't silently hide bad data.
        return (string) $phone;
    }

    /**
     * Client phone mutator: strip everything but digits before persistence
     * so "(647) 834-7570", "647-834-7570" and "6478347570" are all stored
     * the same way. Display-side we always reformat via formatPhone().
     *
     * Empty / non-numeric input is stored as NULL so the column stays
     * genuinely optional.
     */
    public function setClientPhoneAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['client_phone'] = null;

            return;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        $this->attributes['client_phone'] = $digits === '' ? null : $digits;
    }
}
