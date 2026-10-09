<?php

namespace Database\Seeders;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoomAreaGeometrySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@zanini.ca';

    public const ADMIN_PASSWORD = 'password';

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Jonas Zanini',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => User::ROLE_ADMIN,
            ]
        );

        $project = RoomAreaProject::query()->firstOrCreate(
            ['name' => 'Geometry House'],
            ['scale_pixels_per_foot' => 10]
        );

        RoomAreaRoom::query()->firstOrCreate(
            [
                'room_area_project_id' => $project->id,
                'name' => 'Kitchen',
            ],
            [
                'scale_pixels_per_foot' => 10,
                'area_sqft' => 96,
                'wall_height_ft' => 8,
                'vertices' => [
                    'v' => 3,
                    'groups' => [[
                        'id' => 'g1',
                        'color' => '#024768',
                        'polygons' => [[
                            'id' => 'p1',
                            'name' => 'Kitchen',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 120, 'y' => 0],
                                ['x' => 120, 'y' => 80],
                                ['x' => 0, 'y' => 80],
                            ],
                        ]],
                    ]],
                    'objects' => [],
                ],
            ]
        );
    }
}
