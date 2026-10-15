<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->json('legacy_canvas_data')->nullable()->after('vertices');
            $table->json('openplan3d_import_meta')->nullable()->after('legacy_canvas_data');
        });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn([
                'legacy_canvas_data',
                'openplan3d_import_meta',
            ]);
        });
    }
};
