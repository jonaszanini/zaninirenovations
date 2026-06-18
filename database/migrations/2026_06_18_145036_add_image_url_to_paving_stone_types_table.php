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
            $table->string('image_url', 500)->nullable()->after('product_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
