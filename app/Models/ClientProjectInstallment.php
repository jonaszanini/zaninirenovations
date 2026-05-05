<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectInstallment extends Model
{
    /**
     * Fallback HST rate when no quotation is reachable (legacy data only).
     * For live projects HST is sliced proportionally from the quotation —
     * see effectiveHst() / effectiveTotal() below.
     */
    public const FALLBACK_HST_RATE = 0.13;

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

    /**
     * HST attributable to this installment, sliced proportionally from
     * all accepted project quotations' combined HST.
     *
     * Why proportional, not 13% × amount?
     * Quotation lines can opt out of HST individually (apply_hst flag),
     * so the effective rate is
     *   sum(quotation.hst()) / sum(quotation.subtotal())
     * across accepted quotations — never assume 13% across the board.
     *
     * Falls back to 13% × amount only when there is no accepted quotation
     * basis (legacy / manually-set total_amount).
     */
    public function effectiveHst(): float
    {
        $amount = (float) $this->amount;
        if ($amount <= 0) {
            return 0.0;
        }

        $project = $this->relationLoaded('project') ? $this->project : $this->project()->first();
        if (!$project) {
            return round($amount * self::FALLBACK_HST_RATE, 2);
        }

        $sub = $project->acceptedQuotationsFinancialAggregateSubtotal();
        if ($sub < 0.01) {
            return round($amount * self::FALLBACK_HST_RATE, 2);
        }

        $hstSum = $project->acceptedQuotationsFinancialAggregateHst();
        $share = $amount / $sub;

        return round($hstSum * $share, 2);
    }

    /**
     * What the client actually pays for this installment: pre-tax amount +
     * its proportional slice of the combined accepted quotations' HST. Routed
     * through the stored amount so it stays correct if quotations change
     * after the payment plan was saved.
     */
    public function effectiveTotal(): float
    {
        return round((float) $this->amount + $this->effectiveHst(), 2);
    }

    /**
     * Effective HST rate for THIS installment, derived from accepted quotations.
     * Used by the Financials JS preview so the live "Amount (incl. HST)"
     * stays in sync with the server-side numbers when percentages change.
     *
     * Returns FALLBACK_HST_RATE when no accepted quotation basis is available.
     */
    public function effectiveHstRate(): float
    {
        $project = $this->relationLoaded('project') ? $this->project : $this->project()->first();
        if (!$project) {
            return self::FALLBACK_HST_RATE;
        }

        $sub = $project->acceptedQuotationsFinancialAggregateSubtotal();
        if ($sub < 0.01) {
            return self::FALLBACK_HST_RATE;
        }

        return $project->acceptedQuotationsFinancialAggregateHst() / $sub;
    }
}
