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
            $table->dropColumn('retail_price');
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
            $table->decimal('retail_price', 10, 4)->default(0)->after('cost_price');
        });
    }
};
