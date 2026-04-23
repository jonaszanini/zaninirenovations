<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fence_materials — the fixed catalogue of materials used by the Fence
 * quotation calculator. Rows are seeded once and never created/deleted
 * by the admin UI; only `price_cad`, `url`, and `quantity_per_box`
 * (nails only) are editable.
 *
 * The `key` column is the stable identifier the calculator uses to
 * look up each material (e.g. "post_10", "board_6", "nails").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fence_materials', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 160);
            $table->decimal('price_cad', 10, 2)->default(0);
            $table->string('url', 500)->nullable();
            // Only populated for the "nails" row so the calculator can
            // derive a per-nail unit price. Null for everything else.
            $table->unsignedInteger('quantity_per_box')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fence_materials');
    }
};
