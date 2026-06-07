<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('address_place_id')->nullable()->after('address');
            $table->decimal('address_latitude', 10, 7)->nullable()->after('address_place_id');
            $table->decimal('address_longitude', 10, 7)->nullable()->after('address_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'address_place_id',
                'address_latitude',
                'address_longitude',
            ]);
        });
    }
};
