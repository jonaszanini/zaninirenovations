<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            // No ->after(): labour_rate_8ft_per_ft is added in a later
            // migration (2026_05_10), so positioning relative to it breaks a
            // fresh migrate and forward migration on databases not yet at that
            // point. Column order is cosmetic.
            $table->decimal('labour_per_additional_gate', 8, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('fence_formula_settings', function (Blueprint $table) {
            $table->dropColumn('labour_per_additional_gate');
        });
    }
};
