<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->string('product_url', 500)->nullable()->after('name');
            $table->decimal('length_in', 8, 2)->nullable()->after('product_url');
            $table->decimal('width_in', 8, 2)->nullable()->after('length_in');
        });
    }

    public function down(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->dropColumn(['product_url', 'length_in', 'width_in']);
        });
    }
};
