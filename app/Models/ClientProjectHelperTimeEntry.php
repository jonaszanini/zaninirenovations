<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectHelperTimeEntry extends Model
{
    public const TYPE_TIME = 'time';

    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'client_project_id',
        'helper_id',
        'helper_payment_id',
        'entry_type',
        'occurred_on',
        'start_time',
        'end_time',
        'hours',
        'hourly_rate',
        'expense_amount',
        'expense_description',
        'note',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'expense_amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ClientProjectHelperPayment::class, 'helper_payment_id');
    }

    public function isExpense(): bool
    {
        return $this->entry_type === self::TYPE_EXPENSE;
    }

    public function isOpen(): bool
    {
        if ($this->isExpense()) {
            return false;
        }

        return ! empty($this->start_time) && empty($this->end_time);
    }

    public function isPaid(): bool
    {
        return $this->helper_payment_id !== null;
    }

    public function isPayable(): bool
    {
        if ($this->isPaid() || $this->isOpen()) {
            return false;
        }

        if ($this->isExpense()) {
            return (float) ($this->expense_amount ?? 0) >= 0.01;
        }

        return $this->hours !== null;
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNull('helper_payment_id');
    }

    public function scopeClosed($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($time) {
                $time->where('entry_type', self::TYPE_TIME)->whereNotNull('hours');
            })->orWhere(function ($expense) {
                $expense->where('entry_type', self::TYPE_EXPENSE)
                    ->where('expense_amount', '>=', 0.01);
            });
        });
    }

    public function effectiveHourlyRate(?float $fallback = null): float
    {
        if ($this->hourly_rate !== null) {
            return round((float) $this->hourly_rate, 2);
        }

        if ($fallback !== null) {
            return round($fallback, 2);
        }

        return round((float) ($this->helper?->hourly_rate ?? 0), 2);
    }

    public function lineAmount(?float $rate = null): float
    {
        if ($this->isExpense()) {
            return round((float) ($this->expense_amount ?? 0), 2);
        }

        $hours = (float) ($this->hours ?? 0);
        if ($hours <= 0) {
            return 0.0;
        }

        return round($hours * $this->effectiveHourlyRate($rate), 2);
    }

    public function summaryDetailLine(): string
    {
        $dm = $this->occurred_on
            ? \App\Support\EasternStandardTimeDisplay::formatCalendarDayMonth($this->occurred_on)
            : '—';

        if ($this->isExpense()) {
            $reason = filled($this->expense_description) ? $this->expense_description : 'Reimbursement';

            return $dm.': '.$reason.' ('.ClientProject::formatMoney($this->lineAmount()).')';
        }

        $st = self::formatTimeForDisplay($this->start_time, $this->occurred_on);
        $et = self::formatTimeForDisplay($this->end_time, $this->occurred_on);

        if ($this->isOpen()) {
            $hoursPart = 'in progress';
        } elseif ($this->hours !== null) {
            $hoursPart = rtrim(rtrim(number_format((float) $this->hours, 2, '.', ''), '0'), '.').' hrs';
        } else {
            $hoursPart = '—';
        }

        $detail = $dm.': '.$st.' - '.$et.' = '.$hoursPart;

        if (! $this->isOpen() && $this->hours !== null) {
            $rate = $this->effectiveHourlyRate();
            $detail .= ' @ '.ClientProject::formatMoney($rate).'/h';
            $detail .= ' ('.ClientProject::formatMoney($this->lineAmount()).')';
        }

        return $detail;
    }

    public static function formatTimeForDisplay(?string $time, $occurredOn = null): string
    {
        return ClientProjectTimeEntry::formatTimeForDisplay($time, $occurredOn);
    }
}
