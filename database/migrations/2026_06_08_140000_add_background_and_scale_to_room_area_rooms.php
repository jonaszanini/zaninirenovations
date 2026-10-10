<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Query builder — do not use RoomAreaProject here. SoftDeletes adds
        // deleted_at to Eloquent queries, and that column is created later.
        $projects = DB::table('room_area_projects')
            ->where(function ($query) {
                $query->whereNotNull('background_path')
                    ->orWhereNotNull('scale_pixels_per_foot');
            })
            ->get();

        foreach ($projects as $project) {
            $rooms = DB::table('room_area_rooms')
                ->where('room_area_project_id', $project->id)
                ->get();

            foreach ($rooms as $room) {
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
                    DB::table('room_area_rooms')->where('id', $room->id)->update($updates);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('room_area_rooms', function (Blueprint $table) {
            $table->dropColumn(['background_path', 'scale_pixels_per_foot']);
        });
    }
};
