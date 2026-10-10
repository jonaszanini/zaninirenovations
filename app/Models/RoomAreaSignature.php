<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAreaSignature extends Model
{
    protected $fillable = [
        'room_area_project_id',
        'standard_quotation_id',
        'quote_version',
        'active',
        'signer_name',
        'signed_at',
        'admin_user_id',
        'ip',
        'user_agent',
        'snapshot_hash',
        'pdf_path',
        'signature_path',
        'voided_at',
        'void_reason',
        'voided_by',
    ];

    protected $casts = [
        'quote_version' => 'integer',
        'active' => 'integer',
        'signed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(StandardQuotation::class, 'standard_quotation_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isActive(): bool
    {
        return $this->voided_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
