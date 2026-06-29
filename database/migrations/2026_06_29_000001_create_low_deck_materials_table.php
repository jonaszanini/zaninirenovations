<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * low_deck_materials — the fixed catalogue of materials used by the Low
 * Deck floating quotation calculator. Rows are seeded once and never
 * created/deleted by the admin UI; admins only link each row to a row
 * in the standalone `materials` catalog for live prices and product data.
 *
 * The `key` column is the stable identifier the calculator uses to look
 * up each material (e.g. "block_4x4", "joist_2x6_12", "board_5_4x6_12").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('low_deck_materials', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 160);
            $table->foreignId('material_id')
                ->nullable()
                ->constrained('materials')
                ->nullOnDelete();
            $table->string('long_name', 255)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('store_sku', 120)->nullable();
            $table->decimal('price_cad', 10, 2)->default(0);
            $table->string('url', 500)->nullable();
            // Retail pack/box size used to round bulk fasteners up to whole
            // purchasable boxes. Null for items sold individually.
            $table->unsignedInteger('quantity_per_box')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('low_deck_materials');
    }
};
