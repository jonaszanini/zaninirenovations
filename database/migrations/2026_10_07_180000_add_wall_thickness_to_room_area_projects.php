<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->decimal('wall_thickness_in', 8, 2)->default(0)->after('scale_pixels_per_foot');
        });
    }

    public function down(): void
    {
        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->dropColumn('wall_thickness_in');
        });
    }
};
