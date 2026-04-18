<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectInstallment extends Model
{
    protected $fillable = [
        'client_project_id',
        'sort_order',
        'label',
        'percentage',
        'amount',
        'is_paid',
        'paid_on',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_on' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }
}
