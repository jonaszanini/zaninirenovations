<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->decimal('height_in', 8, 2)->nullable()->after('width_in');
        });
    }

    public function down(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->dropColumn('height_in');
        });
    }
};
