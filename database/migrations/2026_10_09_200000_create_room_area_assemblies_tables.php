<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('room_area_assembly_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_assembly_id')->constrained('room_area_assemblies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('formula', 200);
            $table->string('unit', 20)->default('ea');
            $table->boolean('whole_units')->default(true);
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('room_area_room_assemblies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_room_id')->constrained('room_area_rooms')->cascadeOnDelete();
            $table->foreignId('room_area_assembly_id')->constrained('room_area_assemblies')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['room_area_room_id', 'room_area_assembly_id'], 'ra_room_assembly_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_area_room_assemblies');
        Schema::dropIfExists('room_area_assembly_lines');
        Schema::dropIfExists('room_area_assemblies');
    }
};
