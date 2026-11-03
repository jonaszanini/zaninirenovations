<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class QuotationSendAudit extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'client_project_id',
        'client_project_quotation_id',
        'quotation_type',
        'quotation_id',
        'admin_id',
        'admin_name',
        'server_time',
        'recipient_email',
        'cc_email',
        'subject',
        'message',
        'quote_version',
        'pdf_hash',
        'status',
        'error',
        'idempotency_key',
    ];

    protected $casts = [
        'server_time' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Quotation send audit records are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Quotation send audit records are immutable and cannot be deleted.');
        });
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Quotation send audit records are immutable and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Quotation send audit records are immutable and cannot be deleted.');
    }

    public function getRecipientsAttribute(): array
    {
        return array_values(array_filter([$this->recipient_email, $this->cc_email]));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function clientProjectQuotation(): BelongsTo
    {
        return $this->belongsTo(ClientProjectQuotation::class, 'client_project_quotation_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
