<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tile_mortar_types', function (Blueprint $table) {
            $table->dropColumn(['coverage_14_14', 'coverage_14_38', 'coverage_12_12']);
            $table->decimal('retail_price', 10, 4)->default(0)->after('cost_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tile_mortar_types', function (Blueprint $table) {
            $table->dropColumn('retail_price');
            $table->decimal('coverage_14_14', 10, 2)->default(0)->after('bag_weight_lbs');
            $table->decimal('coverage_14_38', 10, 2)->default(0)->after('coverage_14_14');
            $table->decimal('coverage_12_12', 10, 2)->default(0)->after('coverage_14_38');
        });
    }
};
