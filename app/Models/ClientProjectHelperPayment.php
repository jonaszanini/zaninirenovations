<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientProjectHelperPayment extends Model
{
    public const METHOD_E_TRANSFER = 'e_transfer';

    public const METHOD_WISE = 'wise';

    public const METHOD_CASH = 'cash';

    protected $fillable = [
        'client_project_id',
        'helper_id',
        'payment_method',
        'total_amount',
        'total_hours',
        'hourly_rate',
        'client_project_expense_id',
        'paid_at',
        'email_sent_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'paid_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function paymentMethodOptions(): array
    {
        return [
            self::METHOD_E_TRANSFER => 'e-Transfer',
            self::METHOD_WISE => 'Wise',
            self::METHOD_CASH => 'Cash',
        ];
    }

    public function paymentMethodLabel(): string
    {
        // If splits are already loaded (eager-loaded), derive the label from them.
        if ($this->relationLoaded('splits') && $this->splits->isNotEmpty()) {
            return $this->splits
                ->map(fn ($s) => $s->paymentMethodLabel())
                ->unique()
                ->implode(' + ');
        }

        // Fallback for legacy payments that have no splits, or when splits aren't loaded.
        return self::paymentMethodOptions()[$this->payment_method]
            ?? ucfirst(str_replace('_', ' ', (string) $this->payment_method));
    }

    /**
     * Return each split as "CA$400.00 — Cash" lines, for use in emails.
     * Falls back to a single line using the stored payment_method when no splits exist.
     *
     * @return array<string>
     */
    public function paymentSplitLines(): array
    {
        if ($this->relationLoaded('splits') && $this->splits->isNotEmpty()) {
            return $this->splits
                ->map(fn ($s) => ClientProject::formatMoney((float) $s->amount).' — '.$s->paymentMethodLabel())
                ->values()
                ->all();
        }

        // Legacy: single line
        return [ClientProject::formatMoney((float) $this->total_amount).' — '.$this->paymentMethodLabel()];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(ClientProjectExpense::class, 'client_project_expense_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ClientProjectHelperPaymentSplit::class, 'client_project_helper_payment_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ClientProjectHelperTimeEntry::class, 'helper_payment_id');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
