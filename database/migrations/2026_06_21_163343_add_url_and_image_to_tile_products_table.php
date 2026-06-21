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
        Schema::table('tile_products', function (Blueprint $table) {
            $table->text('url')->nullable()->after('name');
            $table->string('image_path', 255)->nullable()->after('url');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tile_products', function (Blueprint $table) {
            $table->dropColumn(['url', 'image_path']);
        });
    }
};
