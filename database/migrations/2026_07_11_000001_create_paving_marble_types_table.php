<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paving_marble_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('product_url', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->decimal('stone_size_in', 8, 2)->nullable();
            $table->decimal('reference_depth_in', 8, 2)->default(2);
            $table->decimal('sq_ft_per_bag_at_reference_depth', 10, 4);
            $table->decimal('price_per_bag', 10, 2);
            $table->unsignedSmallInteger('bulk_min_bags')->nullable();
            $table->decimal('bulk_discount_pct', 5, 2)->nullable();
            $table->decimal('delivery_cost', 10, 2)->nullable();
            $table->decimal('additional_cost', 10, 2)->nullable();
            $table->string('additional_cost_description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('paving_marble_types')->insert([
            [
                'name' => 'Appalache Valley White Marble 1.25 in',
                'product_url' => 'https://www.homedepot.ca/product/appalache-valley-white-marble-stone-1-1-4-in-18-kg-bag/1000419609',
                'image_url' => null,
                'stone_size_in' => 1.25,
                'reference_depth_in' => 2,
                'sq_ft_per_bag_at_reference_depth' => 9,
                'price_per_bag' => 9.98,
                'bulk_min_bags' => 70,
                'bulk_discount_pct' => 10,
                'delivery_cost' => null,
                'additional_cost' => null,
                'additional_cost_description' => null,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'White marble chips 3/4 in',
                'product_url' => null,
                'image_url' => null,
                'stone_size_in' => 0.75,
                'reference_depth_in' => 2,
                'sq_ft_per_bag_at_reference_depth' => 12,
                'price_per_bag' => 8.49,
                'bulk_min_bags' => null,
                'bulk_discount_pct' => null,
                'delivery_cost' => null,
                'additional_cost' => null,
                'additional_cost_description' => null,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('paving_marble_types');
    }
};
