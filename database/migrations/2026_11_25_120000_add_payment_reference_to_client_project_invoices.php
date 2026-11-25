<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional payment reference captured when an invoice is marked paid
 * (for example an e-transfer confirmation). Nullable so older paid
 * invoices keep working with it blank.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};
