<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtransferNotice extends Model
{
    use HasFactory;

    public const SOURCE_API = 'api';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_IGNORED = 'ignored';

    public const CURRENCY_CAD = 'CAD';

    protected $fillable = [
        'received_at',
        'sender_name',
        'sender_email',
        'amount',
        'currency',
        'reference_number',
        'message',
        'raw_text',
        'source',
        'status',
        'invoice_id',
        'payment_id',
        'match_score',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'amount' => 'decimal:2',
        'invoice_id' => 'integer',
        'payment_id' => 'integer',
        'match_score' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientProjectInvoice::class, 'invoice_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function setReferenceNumberAttribute(mixed $value): void
    {
        $trimmed = trim((string) $value);
        $this->attributes['reference_number'] = $trimmed === '' ? null : $trimmed;
    }

    public function setSenderEmailAttribute(mixed $value): void
    {
        $trimmed = strtolower(trim((string) $value));
        $this->attributes['sender_email'] = $trimmed === '' ? null : $trimmed;
    }
}
