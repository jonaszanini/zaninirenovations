<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_project_id')
                ->constrained('room_area_projects')
                ->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->foreignId('phase_id')
                ->nullable()
                ->after('room_area_project_id')
                ->constrained('room_area_phases')
                ->nullOnDelete();
        });

        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phase_id');
        });

        Schema::table('room_area_projects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('room_area_phases');
    }
};
