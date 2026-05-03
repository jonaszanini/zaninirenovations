<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link expenses to an invoice draft/sent invoice (hourly billing: materials
 * lines snapshot). ON DELETE SET NULL restores expenses to "pool" when draft deleted.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_expenses', function (Blueprint $table) {
            $table->foreignId('client_project_invoice_id')
                ->nullable()
                ->after('client_project_id')
                ->constrained('client_project_invoices')
                ->nullOnDelete();

            $table->index(['client_project_id', 'client_project_invoice_id'], 'cpex_project_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_expenses', function (Blueprint $table) {
            $table->dropIndex('cpex_project_invoice_idx');
            $table->dropConstrainedForeignId('client_project_invoice_id');
        });
    }
};
