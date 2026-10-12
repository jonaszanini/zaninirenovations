<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_area_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_area_room_id')
                ->constrained('room_area_rooms')
                ->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('caption', 160)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['room_area_room_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_area_photos');
    }
};
