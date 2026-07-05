<?php

use App\Support\LowDeckWorkDescriptionGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('low_deck_formula_settings')) {
            return;
        }

        Schema::table('low_deck_formula_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('low_deck_formula_settings', 'work_description_template')) {
                $table->text('work_description_template')->nullable()->after('waste_dump_fee_cad');
            }
        });

        if (Schema::hasColumn('low_deck_formula_settings', 'work_description_template')) {
            DB::table('low_deck_formula_settings')
                ->where('id', 1)
                ->whereNull('work_description_template')
                ->update(['work_description_template' => LowDeckWorkDescriptionGenerator::defaultTemplate()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('low_deck_formula_settings')) {
            return;
        }

        Schema::table('low_deck_formula_settings', function (Blueprint $table) {
            if (Schema::hasColumn('low_deck_formula_settings', 'work_description_template')) {
                $table->dropColumn('work_description_template');
            }
        });
    }
};
