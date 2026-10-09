<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->decimal('ceiling_height_ft', 8, 2)->nullable()->after('wall_height_ft');
            $table->decimal('flooring_waste_percent', 5, 2)->default(10)->after('ceiling_height_ft');
            $table->unsignedSmallInteger('flooring_direction')->default(0)->after('flooring_waste_percent');
            $table->decimal('flooring_box_sqft', 8, 2)->nullable()->after('flooring_direction');
        });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn([
                'ceiling_height_ft',
                'flooring_waste_percent',
                'flooring_direction',
                'flooring_box_sqft',
            ]);
        });
    }
};
