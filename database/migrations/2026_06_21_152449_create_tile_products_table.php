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
        Schema::create('tile_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('length_inches', 10, 4)->default(0);
            $table->decimal('width_inches', 10, 4)->default(0);
            $table->decimal('thickness_inches', 10, 4)->default(0);
            $table->decimal('cost_per_sqft', 10, 4)->default(0);
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
        Schema::dropIfExists('tile_products');
    }
};
