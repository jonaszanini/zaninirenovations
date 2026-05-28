<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_helper_time_entries', function (Blueprint $table) {
            $table->string('entry_type', 16)->default('time')->after('helper_payment_id');
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('hours');
            $table->decimal('expense_amount', 12, 2)->nullable()->after('hourly_rate');
            $table->string('expense_description', 500)->nullable()->after('expense_amount');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_helper_time_entries', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'hourly_rate', 'expense_amount', 'expense_description']);
        });
    }
};
