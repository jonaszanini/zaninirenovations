<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->decimal('labour_rate_8ft_per_ft', 8, 2)
                ->default(55.00)
                ->after('labour_rate_per_ft');
        });

        // Start 8' rate equal to the existing 6' rate until the admin adjusts it.
        DB::table('fence_formula_settings')->update([
            'labour_rate_8ft_per_ft' => DB::raw('labour_rate_per_ft'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn('labour_rate_8ft_per_ft');
        });
    }
};
