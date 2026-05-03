<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how the client paid an invoice (cash or e-Transfer). Captured
 * when the admin marks the invoice paid; surfaced on the Receipt PDF.
 *
 * Stored as a short string (cash, etransfer) rather than an enum so we
 * can introduce future methods (e.g. credit card) without another
 * migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->string('payment_method', 32)->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
