<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->decimal('wall_height_ft', 8, 2)->default(8)->after('area_sqft');
            $table->string('pdf_path')->nullable()->after('background_path');
        });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn(['wall_height_ft', 'pdf_path']);
        });
    }
};
