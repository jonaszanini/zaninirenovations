<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->decimal('price_per_sq_ft', 10, 4)->nullable()->after('price_per_stone');
        });

        DB::statement('ALTER TABLE paving_stone_types MODIFY sq_ft_per_stone DECIMAL(10,4) NULL');
        DB::statement('ALTER TABLE paving_stone_types MODIFY price_per_stone DECIMAL(10,2) NULL');

        DB::update('UPDATE paving_stone_types SET price_per_sq_ft = ROUND(price_per_stone / sq_ft_per_stone, 4) WHERE sq_ft_per_stone IS NOT NULL AND sq_ft_per_stone > 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paving_stone_types', function (Blueprint $table) {
            $table->dropColumn('price_per_sq_ft');
        });

        DB::statement('ALTER TABLE paving_stone_types MODIFY sq_ft_per_stone DECIMAL(10,4) NOT NULL');
        DB::statement('ALTER TABLE paving_stone_types MODIFY price_per_stone DECIMAL(10,2) NOT NULL');
    }
};
