<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectExpense extends Model
{
    protected $fillable = [
        'client_project_id',
        'client_project_invoice_id',
        'occurred_on',
        'description',
        'amount',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientProjectInvoice::class, 'client_project_invoice_id');
    }
}
