<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 120)->nullable();
            $table->string('vendor', 120)->nullable();
            $table->string('store_sku', 160)->nullable();
            $table->string('model', 160)->nullable();
            $table->decimal('price_cad', 10, 2)->default(0);
            $table->string('product_url', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedInteger('quantity_per_package')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable();
            $table->string('last_scrape_status', 32)->nullable();
            $table->text('last_scrape_error')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('store_sku');
            $table->index('is_active');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
