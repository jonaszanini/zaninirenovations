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
        Schema::create('tile_mortar_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('bag_weight_lbs', 10, 2)->default(50);
            $table->decimal('coverage_14_14', 10, 2)->default(0); // Coverage in sqft for 1/4" x 1/4"
            $table->decimal('coverage_14_38', 10, 2)->default(0); // Coverage in sqft for 1/4" x 3/8"
            $table->decimal('coverage_12_12', 10, 2)->default(0); // Coverage in sqft for 1/2" x 1/2"
            $table->decimal('cost_price', 10, 4)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tile_mortar_types');
    }
};
