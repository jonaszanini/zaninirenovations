<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectHelperTimeEntry extends Model
{
    protected $fillable = [
        'client_project_id',
        'helper_id',
        'helper_payment_id',
        'occurred_on',
        'start_time',
        'end_time',
        'hours',
        'note',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'hours' => 'decimal:2',
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

    public function isOpen(): bool
    {
        return ! empty($this->start_time) && empty($this->end_time);
    }

    public function isPaid(): bool
    {
        return $this->helper_payment_id !== null;
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNull('helper_payment_id');
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('hours');
    }

    public function lineAmount(?float $rate = null): float
    {
        $hours = (float) ($this->hours ?? 0);
        if ($hours <= 0) {
            return 0.0;
        }
        $rate = $rate ?? (float) ($this->helper?->hourly_rate ?? 0);

        return round($hours * $rate, 2);
    }

    public static function formatTimeForDisplay(?string $time, $occurredOn = null): string
    {
        return ClientProjectTimeEntry::formatTimeForDisplay($time, $occurredOn);
    }
}
