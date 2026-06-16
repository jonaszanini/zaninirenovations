<?php

use App\Models\RoomAreaProject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->string('background_path')->nullable()->after('vertices');
            $table->decimal('scale_pixels_per_foot', 12, 4)->nullable()->after('background_path');
        });

        RoomAreaProject::query()
            ->with('rooms')
            ->where(function ($query) {
                $query->whereNotNull('background_path')
                    ->orWhereNotNull('scale_pixels_per_foot');
            })
            ->get()
            ->each(function (RoomAreaProject $project) {
                foreach ($project->rooms as $room) {
                    $updates = [];

                    if ($project->scale_pixels_per_foot && ! $room->scale_pixels_per_foot) {
                        $updates['scale_pixels_per_foot'] = $project->scale_pixels_per_foot;
                    }

                    if ($project->background_path && ! $room->background_path) {
                        $disk = Storage::disk('local');
                        if ($disk->exists($project->background_path)) {
                            $extension = pathinfo($project->background_path, PATHINFO_EXTENSION) ?: 'jpg';
                            $newPath = 'room-plans/room-' . $room->id . '-' . uniqid() . '.' . $extension;
                            $disk->put($newPath, $disk->get($project->background_path));
                            $updates['background_path'] = $newPath;
                        }
                    }

                    if ($updates !== []) {
                        $room->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn(['background_path', 'scale_pixels_per_foot']);
        });
    }
};
