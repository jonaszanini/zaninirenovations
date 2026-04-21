<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link each hourly time entry to the invoice it was billed on (if any).
 *
 *   - NULL            → entry is "unbilled" and available for the next hourly invoice
 *   - {invoice_id}    → entry has been snapshotted into that invoice
 *
 * On invoice deletion (draft clean-up) the FK is set NULL so the entries
 * become unbilled again and can be pulled onto a new invoice.
 *
 * Fixed-price projects don't populate this column — their invoices are
 * installment-driven and don't snapshot individual time entries.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_time_entries', function (Blueprint $table) {
            $table->foreignId('client_project_invoice_id')
                ->nullable()
                ->after('client_project_id')
                ->constrained('client_project_invoices')
                ->nullOnDelete();

            $table->index(['client_project_id', 'client_project_invoice_id'], 'cpte_project_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_time_entries', function (Blueprint $table) {
            $table->dropIndex('cpte_project_invoice_idx');
            $table->dropConstrainedForeignId('client_project_invoice_id');
        });
    }
};
