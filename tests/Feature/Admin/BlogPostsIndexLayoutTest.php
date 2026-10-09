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

        $placeholder = $this->storeListPlaceholderImage();
        $post = BlogPost::query()->create([
            'title' => 'The Ultimate Decking Showdown',
            'slug' => 'ultimate-decking-showdown',
            'excerpt' => 'A long multi-category post for the admin list.',
            'html_content' => '<p>Body copy for the layout test post.</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'published_at' => null,
            'author_name' => 'Jonas Zanini',
            'views_count' => 123456,
            'featured_image_path' => $placeholder,
            'featured_image_alt' => 'Deck boards',
        ]);
        $post->categories()->sync([$first->id, $second->id, $third->id]);

        $response = $this->get(route('admin.blog.posts.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('bp-list-table', $html);
        $this->assertStringContainsString('admin-table--stack', $html);
        $this->assertStringContainsString('bp-list-seo--card', $html);
        $this->assertStringContainsString('bp-list-thumb', $html);
        $this->assertStringContainsString('onerror=', $html);
        $this->assertStringNotContainsString('bp-list-thumb--empty', $html);
        $this->assertStringContainsString('>Views<', $html);
        $this->assertStringContainsString('123.5K', $html);
        $this->assertStringContainsString('title="123,456"', $html);
        $this->assertStringContainsString('aria-label="123,456 views"', $html);
        $this->assertStringContainsString('sr-only', $html);
        $this->assertStringContainsString('bp-list-post__title-link', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-haspopup="menu"', $html);

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

    public function test_index_shows_missing_image_warning_when_featured_file_is_absent(): void
    {
        $this->actingAsAdmin();

        BlogPost::query()->create([
            'title' => 'Missing image post',
            'slug' => 'missing-image-post',
            'html_content' => '<p>Body.</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'author_name' => 'Jonas Zanini',
            'featured_image_path' => 'blog/does-not-exist.jpg',
        ]);

        $html = $this->get(route('admin.blog.posts.index'))->getContent();
        $this->assertStringContainsString('Missing image post', $html);
        $this->assertStringContainsString('missing image', $html);
        $this->assertStringNotContainsString('bp-list-thumb', $html);
        $this->assertStringNotContainsString('does-not-exist.jpg', $html);
    }

    public function test_featured_image_list_src_keeps_https_and_uses_content_hash(): void
    {
        $post = new BlogPost([
            'featured_image_path' => 'https://cdn.example.com/blog/deck.webp',
        ]);

        $this->assertSame('https://cdn.example.com/blog/deck.webp', $post->featuredImageListSrc());
        $this->assertFalse($post->featuredImageIsMissing());

        $http = new BlogPost([
            'featured_image_path' => 'http://cdn.example.com/blog/deck.webp',
        ]);
        $this->assertNull($http->featuredImageListSrc());
        $this->assertTrue($http->featuredImageIsMissing());

        $relative = $this->storeListPlaceholderImage();
        $absolute = storage_path('app/public/'.$relative);
        $local = new BlogPost(['featured_image_path' => $relative]);
        $src = $local->featuredImageListSrc();
        $this->assertNotNull($src);
        $this->assertStringContainsString('?v='.substr(hash_file('sha1', $absolute), 0, 12), $src);
        $this->assertStringNotContainsString('?v='.filemtime($absolute), $src);
    }

    public function test_format_views_count_compacts_five_and_six_digit_totals(): void
    {
        $this->assertSame('42', (new BlogPost(['views_count' => 42]))->formatViewsCount());
        $this->assertSame('1,234', (new BlogPost(['views_count' => 1234]))->formatViewsCount());
        $this->assertSame('12.3K', (new BlogPost(['views_count' => 12345]))->formatViewsCount());
        $this->assertSame('123.5K', (new BlogPost(['views_count' => 123456]))->formatViewsCount());
        $this->assertSame('1M', (new BlogPost(['views_count' => 999999]))->formatViewsCount());
        $this->assertSame('12.3M', (new BlogPost(['views_count' => 12345678]))->formatViewsCount());
    }

    public function test_column_geometry_css_prevents_overflow_at_1024_and_768(): void
    {
        $css = file_get_contents(public_path('css/admin-blog.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.bp-list-table \.bp-list-col--date\s*\{[^}]*width:\s*6\.25rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.bp-list-table \.bp-list-col--views\s*\{[^}]*width:\s*4\.75rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.bp-list-table th\.bp-list-col--actions[\s\S]*?width:\s*8\.25rem/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1100px\)[\s\S]*\.bp-list-col--date\s*\{[^}]*width:\s*6rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1100px\)[\s\S]*\.bp-list-col--views\s*\{[^}]*width:\s*4\.5rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1100px\)[\s\S]*\.bp-list-col--actions[\s\S]*?width:\s*8rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 768px\) and \(max-width: 900px\)[\s\S]*\.bp-list-col--cat\s*\{[^}]*display:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 901px\) and \(max-width: 1000px\)[\s\S]*\.bp-list-col--cat\s*\{[^}]*display:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.bp-list-seo\s*\{[^}]*flex-wrap:\s*wrap/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.bp-list-seo(?:--card)?\s*\{[^}]*flex-wrap:\s*nowrap/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.bp-list-actions[\s\S]*?gap:\s*12px/',
            $css
        );
        $this->assertStringContainsString('text-overflow: ellipsis', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/@media \(min-width: 768px\)[\s\S]*\.bp-list-seo--card\s*\{\s*flex-wrap:\s*nowrap/s',
            $css
        );

        $this->actingAsAdmin();
        BlogPost::query()->create([
            'title' => 'Six-digit views geometry post',
            'slug' => 'six-digit-views-geometry',
            'html_content' => '<p>Body.</p>',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'author_name' => 'Jonas Zanini',
            'views_count' => 123456,
        ]);
        $html = $this->get(route('admin.blog.posts.index'))->getContent();
        $this->assertStringContainsString('123.5K', $html);
        $this->assertStringNotContainsString('123,456</td>', $html);

        $this->assertBlogPostsGeometryHarness();
    }

    public function test_dropdown_script_moves_focus_into_portaled_menu(): void
    {
        $js = file_get_contents(public_path('js/admin-actions-dropdown.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('aria-expanded', $js);
        $this->assertStringContainsString('items[0].focus()', $js);
        $this->assertStringContainsString("e.key === 'Tab'", $js);
        $this->assertStringContainsString("role', 'menu'", $js);
        $this->assertStringContainsString("role', 'menuitem'", $js);
        $this->assertStringContainsString('toggle.focus()', $js);
        $this->assertStringNotContainsString('first.focus()', $js);
    }

    public function test_published_badge_and_dark_tabs_meet_aa_contrast_tokens(): void
    {
        $light = file_get_contents(public_path('css/admin-blog.css'));
        $this->assertMatchesRegularExpression(
            '/\.bp-status-badge\.blog-post-status--published\s*\{[^}]*color:\s*#146c34/s',
            $light
        );
        $this->assertStringContainsString('a.bp-list-tab.is-active', $light);
    }

    private function assertBlogPostsGeometryHarness(): void
    {
        $script = base_path('tests/Feature/Admin/blog-posts-geometry.js');
        $this->assertFileExists($script);

        $puppeteer = base_path('node_modules/puppeteer');
        $core = base_path('node_modules/puppeteer-core');
        $this->assertTrue(
            is_dir($puppeteer) || is_dir($core),
            'Blog posts geometry harness cannot run: install puppeteer (npm install puppeteer).'
        );

        $cmd = 'NODE_PATH='.escapeshellarg(base_path('node_modules')).' node '.escapeshellarg($script).' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        $joined = implode("\n", $output);
        $this->assertSame(0, $code, $joined);
        $this->assertStringContainsString('"overflowing":[]', $joined);
        $this->assertStringNotContainsString('GEOMETRY_HARNESS_UNAVAILABLE', $joined);
    }

    private function storeListPlaceholderImage(): string
    {
        $relative = 'blog/test-list-placeholder.webp';
        $dest = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }

        $this->assertTrue(function_exists('imagecreatetruecolor'));
        $im = imagecreatetruecolor(96, 64);
        $this->assertNotFalse($im);
        $bg = imagecolorallocate($im, 176, 184, 192);
        $fg = imagecolorallocate($im, 148, 156, 164);
        imagefilledrectangle($im, 0, 0, 95, 63, $bg);
        imagefilledrectangle($im, 28, 16, 68, 48, $fg);
        $this->assertTrue(imagewebp($im, $dest, 80));
        imagedestroy($im);
        $this->assertFileExists($dest);

        return $relative;
    }
}
