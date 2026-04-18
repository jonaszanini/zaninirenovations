<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            // 'fixed' (default) keeps the existing total_amount + payment plan flow.
            // 'hourly' uses hourly_rate * total logged hours as the revenue figure.
            $table->string('billing_type', 20)->default('fixed')->after('payment_plan');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('billing_type');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'hourly_rate']);
        });
    }
};
