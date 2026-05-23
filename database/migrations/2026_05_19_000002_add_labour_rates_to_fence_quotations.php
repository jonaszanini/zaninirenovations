<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->decimal('labour_rate_per_ft', 8, 2)->nullable()->after('gate_count');
            $table->decimal('labour_rate_8ft_per_ft', 8, 2)->nullable()->after('labour_rate_per_ft');
        });

        $defaults = DB::table('fence_formula_settings')->where('id', 1)->first();
        $rate6 = $defaults->labour_rate_per_ft ?? 55.00;
        $rate8 = $defaults->labour_rate_8ft_per_ft ?? $rate6;

        DB::table('fence_quotations')->update([
            'labour_rate_per_ft' => $rate6,
            'labour_rate_8ft_per_ft' => $rate8,
        ]);
    }

    public function down(): void
    {
        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->dropColumn(['labour_rate_per_ft', 'labour_rate_8ft_per_ft']);
        });
    }
};
