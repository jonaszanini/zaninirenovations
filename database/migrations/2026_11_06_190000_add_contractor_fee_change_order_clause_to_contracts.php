<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_contracts', function (Blueprint $table) {
            $table->boolean('include_contractor_fee_change_order_clause')->default(true);
        });

        // Existing rows keep their agreed wording. New contracts get the clause.
        DB::table('client_project_contracts')->update([
            'include_contractor_fee_change_order_clause' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('client_project_contracts', function (Blueprint $table) {
            $table->dropColumn('include_contractor_fee_change_order_clause');
        });
    }
};
