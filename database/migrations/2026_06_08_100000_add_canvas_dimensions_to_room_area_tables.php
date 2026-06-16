<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('canvas_width')->nullable()->after('background_path');
            $table->unsignedSmallInteger('canvas_height')->nullable()->after('canvas_width');
        });

        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('canvas_width')->nullable()->after('vertices');
            $table->unsignedSmallInteger('canvas_height')->nullable()->after('canvas_width');
        });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn(['canvas_width', 'canvas_height']);
        });

        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->dropColumn(['canvas_width', 'canvas_height']);
        });
    }
};
