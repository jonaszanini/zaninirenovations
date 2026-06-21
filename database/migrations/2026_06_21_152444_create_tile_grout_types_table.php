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
        Schema::create('tile_grout_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('bag_weight_lbs', 10, 2)->default(10);
            $table->decimal('density_factor', 10, 4)->default(1.6);
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
        Schema::dropIfExists('tile_grout_types');
    }
};
