<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();
            $table->decimal('scale_pixels_per_foot', 12, 4)->nullable();
            $table->string('background_path')->nullable();
            $table->timestamps();
        });

        Schema::create('room_area_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_project_id')
                ->constrained('room_area_projects')
                ->cascadeOnDelete();
            $table->string('name');
            $table->json('vertices');
            $table->decimal('area_sqft', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_area_rooms');
        Schema::dropIfExists('room_area_projects');
    }
};
