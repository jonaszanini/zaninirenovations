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
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->decimal('delivery_cost', 10, 2)->nullable()->after('price_per_sq_ft');
            $table->decimal('additional_cost', 10, 2)->nullable()->after('delivery_cost');
            $table->string('additional_cost_description', 255)->nullable()->after('additional_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->dropColumn(['delivery_cost', 'additional_cost', 'additional_cost_description']);
        });
    }
};
