<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('gas_price_per_liter', 6, 3)->nullable()->after('home_address_longitude');
        });

        $gasPrice = DB::table('vehicles')
            ->where('is_active', true)
            ->whereNotNull('gas_price_per_liter')
            ->value('gas_price_per_liter');

        if ($gasPrice === null) {
            $gasPrice = DB::table('vehicles')
                ->whereNotNull('gas_price_per_liter')
                ->orderByDesc('updated_at')
                ->value('gas_price_per_liter');
        }

        if ($gasPrice !== null) {
            DB::table('company_settings')->where('id', 1)->update([
                'gas_price_per_liter' => $gasPrice,
            ]);
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('gas_price_per_liter');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('gas_price_per_liter', 6, 3)->nullable()->after('fuel_consumption_l100km');
        });

        $gasPrice = DB::table('company_settings')->where('id', 1)->value('gas_price_per_liter');

        if ($gasPrice !== null) {
            DB::table('vehicles')
                ->where('is_active', true)
                ->update(['gas_price_per_liter' => $gasPrice]);
        }

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('gas_price_per_liter');
        });
    }
};
