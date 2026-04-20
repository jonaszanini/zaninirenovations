<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hourly-billing projects skip the quotation/contract flow because the
 * rate is agreed verbally up front. Instead, Jonas manually triggers a
 * "Service Confirmation" email once the dates are set. This column
 * records the latest send timestamp so the UI can show "Send" vs
 * "Re-send" and history doesn't get lost.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->timestamp('service_confirmation_sent_at')->nullable()->after('quote_sent_follow_up_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn('service_confirmation_sent_at');
        });
    }
};
