<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices — structured invoices attached to a ClientProject.
 *
 * One project can have multiple invoices (one per installment, plus
 * ad-hoc "manual" invoices not linked to any installment). The
 * installment_number column points back to the ordinal position of
 * the linked row in the Financials payment plan (1 through 4), or is
 * NULL for manual invoices.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_project_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')
                ->constrained('client_projects')
                ->cascadeOnDelete();

            // The ordinal position (1..4) of the installment this invoice
            // covers in the Financials payment plan. NULL means the invoice
            // is a one-off / manual amount not tied to any installment.
            $table->unsignedTinyInteger('installment_number')->nullable();

            // Human-readable description snapshotted from the payment plan
            // at the time the invoice was created (e.g. "On signing
            // (holds the schedule)"). Also used for manual invoices where
            // the user types their own description.
            $table->string('installment_description', 255)->nullable();

            // Public-facing unique number printed on the PDF (INV-2026-0001).
            $table->string('invoice_number', 40)->unique();

            // Line items stored as JSON: [{description, amount}]. Amounts
            // are pre-HST so they can be summed to produce `subtotal`.
            $table->json('items')->nullable();

            // Snapshot of the computed totals the last time the invoice
            // was saved. subtotal + hst = total. When installment_number
            // is set, total must match installment.amount * 1.13 to the
            // nearest cent (enforced in the controller).
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('hst', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // draft | sent | paid
            $table->string('status', 20)->default('draft');

            // Issue / pay / due dates.
            $table->timestamp('sent_at')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();

            // Relative paths under storage/app to the latest generated PDFs.
            $table->string('invoice_pdf_path', 500)->nullable();
            $table->string('receipt_pdf_path', 500)->nullable();

            // Auto-incremented every time a NEW invoice PDF is generated
            // after the invoice has already been sent at least once.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Prevent two invoices for the same installment on the same
            // project. MySQL treats NULLs as distinct, so manual invoices
            // (NULL installment_number) can coexist freely.
            $table->unique(['client_project_id', 'installment_number'], 'cp_inv_project_installment_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_project_invoices');
    }
};
