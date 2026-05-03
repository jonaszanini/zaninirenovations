<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an "HST exempt" toggle to invoices. When true, the invoice charges
 * no HST (e.g. cash payments). The column lives at the invoice level — not
 * the project level — because the same project can have one cash invoice
 * (no HST) and another paid by transfer (with HST).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->boolean('hst_exempt')->default(false)->after('hst');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn('hst_exempt');
        });
    }
};
