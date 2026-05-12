<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->boolean('labour_use_fixed')->default(false)->after('labour_hourly_rate');
            $table->decimal('labour_fixed_amount', 12, 2)->nullable()->after('labour_use_fixed');
            $table->boolean('labour_fixed_hst_included')->default(false)->after('labour_fixed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn(['labour_use_fixed', 'labour_fixed_amount', 'labour_fixed_hst_included']);
        });
    }
};
