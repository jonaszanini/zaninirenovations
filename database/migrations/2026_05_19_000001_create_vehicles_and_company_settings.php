<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('license_plate', 32)->nullable();
            $table->decimal('fuel_consumption_l100km', 6, 2)->nullable();
            $table->decimal('gas_price_per_liter', 6, 3)->nullable();
            $table->unsignedInteger('odometer_reading')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('home_address', 500)->nullable();
            $table->string('home_address_place_id', 255)->nullable();
            $table->decimal('home_address_latitude', 10, 7)->nullable();
            $table->decimal('home_address_longitude', 10, 7)->nullable();
            $table->unsignedInteger('average_km_per_month')->default(2000);
            $table->timestamps();
        });

        Schema::table('client_projects', function (Blueprint $table) {
            $table->string('client_address_place_id', 255)->nullable()->after('client_address');
            $table->decimal('client_address_latitude', 10, 7)->nullable()->after('client_address_place_id');
            $table->decimal('client_address_longitude', 10, 7)->nullable()->after('client_address_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('client_projects', function (Blueprint $table) {
            $table->dropColumn([
                'client_address_place_id',
                'client_address_latitude',
                'client_address_longitude',
            ]);
        });

        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('vehicles');
    }
};
