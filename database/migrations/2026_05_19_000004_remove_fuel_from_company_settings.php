<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['fuel_consumption_l100km', 'gas_price_per_liter']);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('fuel_consumption_l100km', 6, 2)->nullable()->after('home_address_longitude');
            $table->decimal('gas_price_per_liter', 6, 3)->nullable()->after('fuel_consumption_l100km');
        });
    }
};
