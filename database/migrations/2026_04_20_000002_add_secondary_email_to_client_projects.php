<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some clients want a spouse/partner copied on every outgoing email
 * (contract, quotation, service confirmation, thank-you, etc.). This
 * column holds that optional second address. When present it's CC'd
 * on every client-facing send.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->string('client_email_secondary', 255)->nullable()->after('client_email');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn('client_email_secondary');
        });
    }
};
