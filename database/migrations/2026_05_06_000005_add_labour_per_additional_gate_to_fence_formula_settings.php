<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->decimal('labour_per_additional_gate', 8, 2)
                ->default(0.00)
                ->after('labour_rate_8ft_per_ft');
        });
    }

    public function down(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn('labour_per_additional_gate');
        });
    }
};
