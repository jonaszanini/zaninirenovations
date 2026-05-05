<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->string('invoice_billing_name', 255)->nullable()->after('client_address');
            $table->text('invoice_billing_address')->nullable()->after('invoice_billing_name');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn(['invoice_billing_name', 'invoice_billing_address']);
        });
    }
};
