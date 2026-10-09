<?php

namespace Tests\Feature\Admin;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BlogPostsIndexLayoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_index_uses_column_classes_and_category_plus_n_chip(): void
    {
        $this->actingAsAdmin();

        $first = BlogCategory::query()->create([
            'name' => 'Decks, Fences & Outdoor Living',
            'slug' => 'decks-fences-outdoor-living',
        ]);
        $second = BlogCategory::query()->create([
            'name' => 'Guides & Comparisons',
            'slug' => 'guides-comparisons',
        ]);
        $third = BlogCategory::query()->create([
            'name' => 'Home Improvement',
            'slug' => 'home-improvement',
        ]);

        $post = BlogPost::query()->create([
            'title' => 'The Ultimate Decking Showdown',
            'slug' => 'ultimate-decking-showdown',
            'excerpt' => 'A long multi-category post for the admin list.',
            'html_content' => '<p>Body copy for the layout test post.</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'author_name' => 'Jonas Zanini',
            'views_count' => 128,
        ]);
        $post->categories()->sync([$first->id, $second->id, $third->id]);

        $response = $this->get(route('admin.blog.posts.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('bp-list-table', $html);
        $this->assertStringContainsString('admin-table--fixed', $html);
        $this->assertStringContainsString('admin-table--stack', $html);

        foreach ([
            'bp-list-col--post',
            'bp-list-col--cat',
            'bp-list-col--status',
            'bp-list-col--date',
            'bp-list-col--views',
            'bp-list-col--actions',
        ] as $class) {
            $this->assertStringContainsString($class, $html);
        }

        $this->assertStringContainsString('Decks, Fences &amp; Outdoor Living', $html);
        $this->assertStringContainsString('bp-list-cat__more', $html);
        $this->assertStringContainsString('>+2<', $html);
        $this->assertStringContainsString(
            'title="Decks, Fences &amp; Outdoor Living, Guides &amp; Comparisons, Home Improvement"',
            $html
        );
        $this->assertStringNotContainsString(
            'Decks, Fences &amp; Outdoor Living, Guides &amp; Comparisons, Home Improvement</td>',
            $html
        );

        $this->assertStringContainsString('bp-status-badge', $html);
        $this->assertStringContainsString('blog-post-status--draft', $html);
        $this->assertStringContainsString('bp-list-actions', $html);
        $this->assertStringContainsString('bp-list-icon-btn', $html);
        $this->assertStringContainsString('admin-actions-dropdown__toggle', $html);

        $this->assertStringContainsString('data-admin-filters-accordion', $html);
        $this->assertStringContainsString('id="admin-filters-blog-posts"', $html);
        $this->assertStringContainsString('admin-filters-accordion__title', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<details[^>]*id="admin-filters-blog-posts"[^>]*\sopen/i',
            $html
        );
    }

    public function test_index_opens_filters_accordion_when_query_filters_are_active(): void
    {
        $this->actingAsAdmin();

        $category = BlogCategory::query()->create([
            'name' => 'Basements & Interior Renovations',
            'slug' => 'basements-interior',
        ]);
        $post = BlogPost::query()->create([
            'title' => 'Finishing a Basement in Ottawa',
            'slug' => 'finishing-a-basement-ottawa',
            'excerpt' => 'Single-category post.',
            'html_content' => '<p>Body copy.</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'author_name' => 'Jonas Zanini',
        ]);
        $post->categories()->sync([$category->id]);

        $response = $this->get(route('admin.blog.posts.index', [
            'q' => 'Basement',
            'category' => $category->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-active-count="2"', $html);
        $this->assertStringContainsString('2 active', $html);
        $this->assertMatchesRegularExpression(
            '/<details[^>]*id="admin-filters-blog-posts"[^>]*\sopen/i',
            $html
        );
        $this->assertStringContainsString('Finishing a Basement in Ottawa', $html);
        $this->assertStringContainsString('Basements &amp; Interior Renovations', $html);
        $this->assertStringNotContainsString('bp-list-cat__more', $html);
    }
}
