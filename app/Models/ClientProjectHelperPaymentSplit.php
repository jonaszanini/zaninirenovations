<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectHelperPaymentSplit extends Model
{
    protected $fillable = [
        'client_project_helper_payment_id',
        'payment_method',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ClientProjectHelperPayment::class, 'client_project_helper_payment_id');
    }

    /** Human-readable label for this split's method. */
    public function paymentMethodLabel(): string
    {
        return ClientProjectHelperPayment::paymentMethodOptions()[$this->payment_method]
            ?? ucfirst(str_replace('_', ' ', (string) $this->payment_method));
    }
}
