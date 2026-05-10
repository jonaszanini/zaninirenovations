<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->decimal('discount_percent', 7, 4)->nullable()->after('total');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount']);
        });
    }
};
