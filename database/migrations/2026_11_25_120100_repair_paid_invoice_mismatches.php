<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * One-time repair for invoices left unpaid after the installment was marked
 * paid (or a payment was stored without flipping status).
 *
 * Repairs an invoice only when status is not paid and not refunded, and any
 * of these is true: linked installment is_paid, paid_at is set, paid_amount
 * is greater than zero, or payment_method is filled.
 *
 * Sets status to paid, fills paid_at from the recorded date when blank,
 * and pauses reminders. Already paid invoices are not selected and are not
 * changed. This never sends client email. Safe to run again.
 */
return new class extends Migration {
    public function up(): void
    {
        Artisan::call('invoices:repair-paid-mismatch');
    }

    public function down(): void
    {
        // Recorded payments stay paid. Reversing them would be a guess.
    }
};
