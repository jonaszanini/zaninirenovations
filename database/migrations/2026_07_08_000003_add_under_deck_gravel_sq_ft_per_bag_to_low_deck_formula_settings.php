<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('low_deck_formula_settings', function (Blueprint $table) {
            $table->decimal('under_deck_gravel_sq_ft_per_bag', 6, 2)
                ->default(8.00)
                ->after('gravel_cubic_ft_per_bag');
        });
    }

    public function down(): void
    {
        Schema::table('low_deck_formula_settings', function (Blueprint $table) {
            $table->dropColumn('under_deck_gravel_sq_ft_per_bag');
        });
    }
};
