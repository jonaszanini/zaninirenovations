<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tile_edges')) {
            return;
        }

        Schema::create('tile_edges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('url')->nullable();
            $table->string('thickness', 32)->nullable();
            $table->string('color', 120)->nullable();
            $table->decimal('unit_price', 10, 4)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tile_edges');
    }
};
