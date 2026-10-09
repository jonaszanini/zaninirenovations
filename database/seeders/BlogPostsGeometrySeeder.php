<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BlogPostsGeometrySeeder extends Seeder
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

        $first = BlogCategory::query()->firstOrCreate(
            ['slug' => 'decks-fences-outdoor-living'],
            ['name' => 'Decks, Fences & Outdoor Living']
        );
        $second = BlogCategory::query()->firstOrCreate(
            ['slug' => 'guides-comparisons'],
            ['name' => 'Guides & Comparisons']
        );
        $third = BlogCategory::query()->firstOrCreate(
            ['slug' => 'home-improvement'],
            ['name' => 'Home Improvement']
        );

        $posts = [
            [
                'slug' => 'geometry-four-chip-views',
                'title' => 'The Ultimate Decking Showdown For Ottawa Backyards And Long Titles',
                'views_count' => 123456,
            ],
            [
                'slug' => 'geometry-long-title-many-chips',
                'title' => 'Pressure-Treated Cedar Composite And PVC Decking Compared For Ontario Homes This Season',
                'views_count' => 123456,
            ],
        ];

        foreach ($posts as $data) {
            $post = BlogPost::query()->firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'html_content' => '<p>Geometry fixture post.</p>',
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => now()->subDay(),
                    'author_name' => 'Jonas Zanini',
                    'views_count' => $data['views_count'],
                    'featured_image_path' => 'blog/does-not-exist-'.$data['slug'].'.jpg',
                ]
            );
            $post->categories()->sync([$first->id, $second->id, $third->id]);
        }
    }
}
