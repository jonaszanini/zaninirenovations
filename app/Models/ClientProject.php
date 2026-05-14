<?php

namespace App\Models;

use App\Support\EasternStandardTimeDisplay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProject — full business lifecycle for a renovation project
 * (from first contact / visit through completion).
 *
 * This is distinct from the legacy Project model which only manages
 * portfolio photos shown on the public home page.
 */
class ClientProject extends Model
{
    // Canonical status keys (snake_case for DB, human labels in statusLabels()).
    /** Lead / intake: on-site visit agreed, quote not sent yet. */
    public const STATUS_VISIT_SCHEDULED = 'visit_scheduled';
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

    /** How the client first reached you (intake source). */
    public const CONTACTED_VIA_WEBSITE_FORM = 'website_form';
    public const CONTACTED_VIA_SMS = 'sms';
    public const CONTACTED_VIA_EMAIL = 'email';
    public const CONTACTED_VIA_CALL = 'call';
    public const CONTACTED_VIA_FACEBOOK = 'facebook';
    public const CONTACTED_VIA_INSTAGRAM = 'instagram';
    public const CONTACTED_VIA_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'client_id',
        'name',
        'contacted_via',
        'client_name',
        'client_email',
        'client_email_secondary',
        'client_phone',
        'client_address',
        'invoice_billing_name',
        'invoice_billing_address',
        'notes',
        'status',
        'status_changed_at',
        'start_date',
        'day_before_start_sent_for_date',
        'end_date',
        'visit_required',
        'visit_date',
        'visit_done',
        'calendar_email_sent_at',
        'total_amount',
        'payment_plan',
        'billing_type',
        'hourly_rate',
        'last_quote_reminder_sent_at',
        'quote_sent_follow_up_due_at',
        'quote_sent_follow_up_sent_at',
        'service_confirmation_sent_at',
        'fixed_schedule_confirmation_sent_at',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'start_date' => 'date',
        'day_before_start_sent_for_date' => 'date',
        'end_date' => 'date',
        'visit_required' => 'boolean',
        'visit_date' => 'date',
        'visit_done' => 'boolean',
        'status_changed_at' => 'datetime',
        'calendar_email_sent_at' => 'datetime',
        'last_quote_reminder_sent_at' => 'datetime',
        'quote_sent_follow_up_due_at' => 'datetime',
        'quote_sent_follow_up_sent_at' => 'datetime',
        'service_confirmation_sent_at' => 'datetime',
        'fixed_schedule_confirmation_sent_at' => 'datetime',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

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

    public function quotations(): HasMany
    {
        return $this->hasMany(ClientProjectQuotation::class, 'client_project_id')->orderBy('id');
    }
    public function standardQuotations(): HasMany
    {
        return $this->hasMany(StandardQuotation::class)->orderByDesc('id');
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

    /**
     * Quotation delivery requires at least one outbound channel:
     * a primary email OR an SMS-capable phone number.
     */
    public function hasQuotationDeliveryChannel(): bool
    {
        return filled(trim((string) $this->client_email))
            || self::phoneDigitsForSms($this->client_phone) !== null;
    }

    /**
     * Human labels for missing quotation delivery channels.
     *
     * @return list<string>
     */
    public function missingQuotationDeliveryChannels(): array
    {
        $missing = [];
        if (blank(trim((string) $this->client_email))) {
            $missing[] = 'Client email';
        }
        if (self::phoneDigitsForSms($this->client_phone) === null) {
            $missing[] = 'Client phone (SMS-capable)';
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

    /**
     * Statuses before a formal quotation email has been sent (visit / lead → quote prep).
     *
     * @return list<string>
     */
    public static function preQuoteSentStatuses(): array
    {
        return [
            self::STATUS_VISIT_SCHEDULED,
            self::STATUS_QUOTE_PENDING,
        ];
    }

    /** True while the project is still in the visit or quote-pending intake stage. */
    public function isAwaitingQuotePreparation(): bool
    {
        return in_array($this->status, self::preQuoteSentStatuses(), true);
    }

    /** All valid status keys (in workflow order). */
    public static function statuses(): array
    {
        return [
            self::STATUS_VISIT_SCHEDULED,
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

    /** Intake channel options for create/edit forms (key → label). */
    public static function contactedViaOptions(): array
    {
        return [
            self::CONTACTED_VIA_WEBSITE_FORM => 'Web site form',
            self::CONTACTED_VIA_SMS => 'SMS',
            self::CONTACTED_VIA_EMAIL => 'Email',
            self::CONTACTED_VIA_CALL => 'Call',
            self::CONTACTED_VIA_FACEBOOK => 'Facebook',
            self::CONTACTED_VIA_INSTAGRAM => 'Instagram',
            self::CONTACTED_VIA_WHATSAPP => 'WhatsApp',
        ];
    }

    /** Label for list/detail UI; em dash when unset or unknown. */
    public function contactedViaDisplay(): string
    {
        if ($this->contacted_via === null || $this->contacted_via === '') {
            return '—';
        }

        return self::contactedViaOptions()[$this->contacted_via] ?? '—';
    }

    /**
     * Stored `contacted_via` key when it maps to a known option (for CSS styling).
     */
    public function contactedViaStyleKey(): ?string
    {
        if ($this->contacted_via === null || $this->contacted_via === '') {
            return null;
        }

        return array_key_exists($this->contacted_via, self::contactedViaOptions())
            ? $this->contacted_via
            : null;
    }

    /** Mapping of status key → human readable label. */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_VISIT_SCHEDULED => 'Visit scheduled',
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
            self::STATUS_VISIT_SCHEDULED => '#0891b2',       // cyan — pre-quote / visit
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
     * Accepted project quotations: used for payment-plan and installment
     * HST allocation (sum of per-line HST; lines may be HST-exempt).
     *
     * @return \Illuminate\Support\Collection<int, ClientProjectQuotation>
     */
    public function acceptedQuotationsForFinancials()
    {
        $rows = $this->relationLoaded('quotations')
            ? $this->quotations
            : $this->quotations()->orderBy('id')->get();

        return $rows
            ->where('status', ClientProjectQuotation::STATUS_ACCEPTED)
            ->values();
    }

    /** Sum of pre-tax subtotals for all accepted quotations. */
    public function acceptedQuotationsFinancialAggregateSubtotal(): float
    {
        $sum = 0.0;
        foreach ($this->acceptedQuotationsForFinancials() as $q) {
            $sum += $q->subtotal();
        }

        return round($sum, 2);
    }

    /** Sum of HST for all accepted quotations (reflects per-line HST flags). */
    public function acceptedQuotationsFinancialAggregateHst(): float
    {
        $sum = 0.0;
        foreach ($this->acceptedQuotationsForFinancials() as $q) {
            $sum += $q->hst();
        }

        return round($sum, 2);
    }

    /**
     * Fixed-price: sum of accepted quotation line amounts (pre-HST). Used as the basis
     * for payment-plan percentages — matches invoice line subtotals + proportional HST.
     */
    public function quotationSubtotalForFinancials(): ?float
    {
        if ($this->isHourly()) {
            return null;
        }

        $sub = $this->acceptedQuotationsFinancialAggregateSubtotal();

        return $sub >= 0.01 ? $sub : null;
    }

    /** Fixed-price: sum of accepted quotations' totals (incl. per-line HST). */
    public function quotationGrandTotalForFinancials(): ?float
    {
        if ($this->isHourly()) {
            return null;
        }

        $grand = ClientProjectQuotation::acceptedGrandTotalForProject($this);

        return $grand >= 0.01 ? $grand : null;
    }

    /**
     * First quotation with content, otherwise first row — for contract email / legacy links.
     */
    public function primaryQuotation(): ?ClientProjectQuotation
    {
        $rows = $this->relationLoaded('quotations')
            ? $this->quotations
            : $this->quotations()->orderBy('id')->get();

        $firstWithContent = $rows->first(fn (ClientProjectQuotation $q) => $q->hasContent());

        return $firstWithContent ?? $rows->first();
    }

    /**
     * After the quotation changes, copy its pre-tax subtotal into `total_amount`
     * and refresh each installment's dollar amount (same %) when nothing is paid yet.
     */
    public function syncFixedContractTotalsFromQuotation(): bool
    {
        if ($this->isHourly()) {
            return false;
        }

        $sub = $this->quotationSubtotalForFinancials();
        if ($sub === null || $sub < 0.01) {
            return false;
        }

        if ($this->installments()->where('is_paid', true)->exists()) {
            return false;
        }

        $this->total_amount = $sub;
        $this->saveQuietly();

        foreach ($this->installments()->orderBy('sort_order')->get() as $inst) {
            $inst->amount = round($sub * (float) $inst->percentage / 100, 2);
            $inst->saveQuietly();
        }

        return true;
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

    /**
     * Project list "Dates" column: show visit date + "Visit" while status is
     * Visit scheduled and the on-site visit is not marked done yet.
     */
    public function showsVisitLayoutInProjectList(): bool
    {
        return $this->status === self::STATUS_VISIT_SCHEDULED && !$this->visit_done;
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

    /**
     * Cash costs that reduce the owner's gross/net profit calculations.
     * On hourly projects, expense lines are tracked for materials passed through /
     * reimbursed on client invoices—they are excluded here so labour-only profit matches reality.
     */
    public function totalSpentForProfit(): float
    {
        if ($this->isHourly()) {
            return 0.0;
        }

        return $this->totalSpent();
    }

    /**
     * Sum of paid installments (pre-tax `ClientProjectInstallment::amount`).
     * For installment invoices, amounts are aligned to the invoice’s billed
     * subtotal (after discount) when the invoice is marked paid.
     */
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

    /**
     * Legal / accounting name shown on invoice & receipt PDFs when the payer
     * is a corporation (or other entity) distinct from the day-to-day contact.
     */
    public function invoiceBillToName(): string
    {
        $override = trim((string) ($this->invoice_billing_name ?? ''));

        return $override !== '' ? $override : (string) ($this->client_name ?? '');
    }

    /**
     * Address block for invoice & receipt PDFs. Uses the optional billing
     * address when set; otherwise the project client address.
     */
    public function invoiceBillToAddress(): string
    {
        $override = trim((string) ($this->invoice_billing_address ?? ''));
        if ($override !== '') {
            return $override;
        }

        return (string) ($this->client_address ?? '');
    }

    public function usesInvoiceBillingOverride(): bool
    {
        return filled(trim((string) ($this->invoice_billing_name ?? '')));
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

    /**
     * Hourly “Earned (rate × hours)” is pre-tax; this is that subtotal plus
     * 13% HST — the gross amount if all logged hours were invoiced with tax.
     */
    public function hourlyFinalAmountAfterHst(): float
    {
        if (!$this->isHourly()) {
            return 0.0;
        }

        return round($this->hourlyAmountEarned() * (1 + self::HST_RATE), 2);
    }

    public function grossProfit(): float
    {
        return $this->revenueForProfit() - $this->totalSpentForProfit();
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

    /**
     * Plain-text bundle of project settings + every quotation linked to the
     * project (calculator snapshots, line items, totals) for pasting into an
     * external AI when drafting contract language. Not client-facing.
     */
    public function contractDraftingContextPlainText(): string
    {
        $this->loadMissing(['quotations', 'installments']);

        $lines = [];
        $lines[] = '--- Project ---';
        $lines[] = 'Name: '.$this->name;
        $lines[] = 'Billing: '.$this->billingSummaryLine();
        if ($this->isHourly() && $this->hourly_rate !== null) {
            $lines[] = 'Hourly rate: '.self::formatMoney((float) $this->hourly_rate).' / hour';
        }
        if (!$this->isHourly() && $this->total_amount !== null) {
            $lines[] = 'Contract total (fixed): '.self::formatMoney((float) $this->total_amount);
        }
        $this->appendContractContextInstallmentLines($lines);
        $lines[] = 'e-Transfer: zaninirenovations@gmail.com (include the invoice number in the payment message when applicable).';

        if ($this->start_date || $this->end_date || $this->visit_required || $this->visit_done || $this->visit_date) {
            $lines[] = '';
            $lines[] = '--- Schedule ---';
            if ($this->start_date) {
                $lines[] = 'Start date: '.self::formatDate($this->start_date);
            }
            if ($this->end_date) {
                $lines[] = 'End date: '.self::formatDate($this->end_date);
            }
            if ($this->visit_required || $this->visit_done || $this->visit_date) {
                $lines[] = 'Visit required: '.($this->visit_required ? 'Yes' : 'No');
                if ($this->visit_date) {
                    $lines[] = 'Visit date: '.self::formatDate($this->visit_date);
                }
                $lines[] = 'Visit done: '.($this->visit_done ? 'Yes' : 'No');
            }
        }

        $lines[] = '';
        $lines[] = '--- Client & site ---';
        $lines[] = 'Client name: '.($this->client_name ?: '—');
        $lines[] = 'Email: '.(trim((string) $this->client_email) !== '' ? $this->client_email : '—');
        if (trim((string) ($this->client_email_secondary ?? '')) !== '') {
            $lines[] = 'Secondary email (CC): '.$this->client_email_secondary;
        }
        $lines[] = 'Phone: '.(trim((string) $this->client_phone) !== '' ? self::formatPhone($this->client_phone) : '—');
        $lines[] = 'Service address: '.(trim((string) ($this->client_address ?? '')) !== '' ? $this->client_address : '—');
        if (trim((string) ($this->invoice_billing_name ?? '')) !== '') {
            $lines[] = 'Invoice billing name: '.$this->invoice_billing_name;
        }
        if (trim((string) ($this->invoice_billing_address ?? '')) !== '') {
            $lines[] = 'Invoice billing address: '.$this->invoice_billing_address;
        }

        $notes = trim((string) ($this->notes ?? ''));
        $lines[] = '';
        $lines[] = '--- Internal notes (from Edit project) ---';
        $lines[] = $notes !== '' ? $notes : '(none)';

        $lines[] = '';
        $lines[] = '--- Quotations (linked to this project) ---';
        $quotes = $this->quotations->sortBy('id')->values();
        if ($quotes->isEmpty()) {
            $lines[] = '(No quotations linked to this project yet.)';
        } else {
            $i = 0;
            foreach ($quotes as $q) {
                $i++;
                $this->appendContractContextQuotation($lines, $q, $i);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendContractContextInstallmentLines(array &$lines): void
    {
        if ($this->isHourly()) {
            return;
        }

        $sorted = $this->installments->sortBy('sort_order')->values();
        if ($sorted->isEmpty()) {
            return;
        }

        $plan = (int) ($this->payment_plan ?? 0);
        if ($plan >= 1) {
            $lines[] = 'Payment plan: '.$plan.' installment(s)';
        } else {
            $lines[] = 'Installments (from Financials):';
        }

        $n = 0;
        foreach ($sorted as $inst) {
            $n++;
            $inst->setRelation('project', $this);
            $label = trim((string) ($inst->label ?? '')) !== '' ? $inst->label : 'Installment '.$n;
            $pre = (float) $inst->amount;
            $hst = $inst->effectiveHst();
            $tot = $inst->effectiveTotal();
            $paid = $inst->is_paid ? ' [paid]' : '';
            $lines[] = '  '.$n.'. '.$label.$paid;
            $lines[] = '     Pre-tax: '.self::formatMoney($pre).' | HST: '.self::formatMoney($hst).' | Total incl. HST: '.self::formatMoney($tot);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendContractContextQuotation(array &$lines, ClientProjectQuotation $q, int $ordinal): void
    {
        $lines[] = '';
        $lines[] = '=== Quotation #'.$ordinal.' ===';
        $lines[] = 'Status: '.$q->statusLabel();
        $lines[] = 'Document: '.($q->quotation_number ?: '—').' ('.$q->typeLabel().')';
        $lines[] = 'Title: '.(trim((string) ($q->title ?? '')) !== '' ? $q->title : '—');
        $lines[] = 'Source: '.$this->quotationSourceCalculatorLabel($q);
        $lines[] = 'Labour line on quote: '.($q->labour ? 'Yes' : 'No').' | Material line on quote: '.($q->material ? 'Yes' : 'No');

        $snap = $q->source_snapshot;
        if (is_array($snap) && $snap !== []) {
            $json = json_encode(
                $snap,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            $lines[] = '';
            $lines[] = 'Calculator / linked document fields (JSON — e.g. fence heights, sides, gates, materials grid):';
            $lines[] = $json !== false ? $json : '(could not encode snapshot)';
        }

        $wd = $this->plainTextFromPossibleHtml($q->work_description);
        $lines[] = '';
        $lines[] = 'Work / scope description (as on quotation):';
        $lines[] = $wd !== '' ? $wd : '(none)';

        $items = $q->cleanItems();
        $lines[] = '';
        if ($items === []) {
            $lines[] = 'Line items: (none — see calculator JSON above if linked from a calculator.)';
        } else {
            $lines[] = 'Line items (pre-tax; HST per line):';
            foreach ($items as $row) {
                $hst = !empty($row['apply_hst']) ? 'HST 13%' : 'No HST';
                $lines[] = '  - '.($row['description'] !== '' ? $row['description'] : '(no description)')
                    .' | '.self::formatMoney((float) $row['amount']).' | '.$hst;
            }
        }
        if ($q->hasSubtotalOverrideActive()) {
            $lines[] = 'Subtotal override (pre-tax): '.self::formatMoney((float) $q->subtotal_override);
        }
        $lines[] = 'Subtotal (pre-tax): '.self::formatMoney($q->subtotal());
        $lines[] = 'HST: '.self::formatMoney($q->hst());
        $lines[] = 'Total (with HST): '.self::formatMoney($q->grandTotal());
    }

    private function billingSummaryLine(): string
    {
        return $this->isHourly() ? 'Hourly' : 'Fixed price';
    }

    private function quotationSourceCalculatorLabel(ClientProjectQuotation $q): string
    {
        return match ($q->source_type) {
            ClientProjectQuotation::SOURCE_FENCE => 'Fence calculator',
            ClientProjectQuotation::SOURCE_STANDARD => 'Standard calculator',
            ClientProjectQuotation::SOURCE_PAVING => 'Paving calculator',
            ClientProjectQuotation::SOURCE_MANUAL => 'Manual / legacy',
            null, '' => 'Manual / legacy',
            default => (string) $q->source_type,
        };
    }

    private function plainTextFromPossibleHtml(?string $html): string
    {
        $t = trim(html_entity_decode(strip_tags((string) ($html ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace("/[ \t]+\n/", "\n", $t) ?? $t;
    }

    // --- Presentation helpers ---------------------------------------------

    public static function formatMoney(?float $amount): string
    {
        $n = (float) ($amount ?? 0);

        return 'CA$'.number_format($n, 2, '.', ',');
    }

    public static function formatDate($date): string
    {
        return EasternStandardTimeDisplay::formatCalendarDate($date);
    }

    /**
     * Date + time: Toronto wall clock, suffixed with {@see EasternStandardTimeDisplay::DISPLAY_TIMEZONE_LABEL}.
     */
    public static function formatDateTime($dateTime): string
    {
        return EasternStandardTimeDisplay::formatDateTime($dateTime);
    }

    /**
     * Digits-only destination for SMS gateways (e.g. Mocean `mocean-to`).
     * Stored 10-digit NANP numbers get a leading country code 1.
     */
    public static function phoneDigitsForSms(?string $phone): ?string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if ($d === '') {
            return null;
        }

        if (strlen($d) === 10) {
            return '1'.$d;
        }

        if (strlen($d) === 11 && str_starts_with($d, '1')) {
            return $d;
        }

        if (strlen($d) >= 10 && strlen($d) <= 15) {
            return $d;
        }

        return null;
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
