<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status_changed_at' => 'datetime',
        'calendar_email_sent_at' => 'datetime',
        'last_quote_reminder_sent_at' => 'datetime',
        'quote_sent_follow_up_due_at' => 'datetime',
        'quote_sent_follow_up_sent_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'payment_plan' => 'integer',
        'hourly_rate' => 'decimal:2',
    ];

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
        return $this->hasMany(ClientProjectTimeEntry::class)->orderBy('occurred_on');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClientProjectStatusHistoryEntry::class)->orderByDesc('changed_at');
    }

    // --- Status helpers ----------------------------------------------------

    /** All valid status keys (in workflow order). */
    public static function statuses(): array
    {
        return [
            self::STATUS_QUOTE_PENDING,
            self::STATUS_QUOTE_SENT,
            self::STATUS_PROPOSAL_ACCEPTED,
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
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /** Mapping of status key → short CSS modifier used for badges/calendar colors. */
    public static function statusColors(): array
    {
        return [
            self::STATUS_QUOTE_PENDING => '#f59e0b',      // amber
            self::STATUS_QUOTE_SENT => '#3b82f6',         // blue
            self::STATUS_PROPOSAL_ACCEPTED => '#10b981',  // green
            self::STATUS_IN_PROGRESS => '#6366f1',        // indigo
            self::STATUS_COMPLETED => '#22c55e',          // bright green
            self::STATUS_REJECTED => '#ef4444',           // red
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
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ], true);
    }

    /** True if the project is billed by the hour (rate × logged hours). */
    public function isHourly(): bool
    {
        return $this->billing_type === self::BILLING_HOURLY;
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
}
