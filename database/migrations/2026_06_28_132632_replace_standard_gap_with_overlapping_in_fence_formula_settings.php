<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            if (Schema::hasColumn('fence_formula_settings', 'standard_gap_in')) {
                $table->dropColumn('standard_gap_in');
            }
            if (! Schema::hasColumn('fence_formula_settings', 'overlapping_in')) {
                $table->decimal('overlapping_in', 5, 3)->default(1.000)->after('board_width_in');
            }
        });

        Schema::table('fence_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('fence_quotations', 'overlap_in')) {
                $table->dropColumn('overlap_in');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn('overlapping_in');
            $table->decimal('standard_gap_in', 5, 3)->default(0.125)->after('board_width_in');
        });

        Schema::table('fence_quotations', function (Blueprint $table) {
            $table->decimal('overlap_in', 5, 3)->default(0)->after('board_style');
        });
    }
};
