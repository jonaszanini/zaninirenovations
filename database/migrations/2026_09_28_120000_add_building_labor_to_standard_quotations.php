<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('standard_quotations', 'include_building')) {
                $table->boolean('include_building')->default(false)->after('include_debris_removal');
            }
            if (! Schema::hasColumn('standard_quotations', 'building_labor_percent')) {
                $table->decimal('building_labor_percent', 6, 2)->default(30)->after('include_building');
            }
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('standard_quotations', 'building_labor_percent') ? 'building_labor_percent' : null,
                Schema::hasColumn('standard_quotations', 'include_building') ? 'include_building' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
