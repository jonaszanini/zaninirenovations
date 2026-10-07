<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Support\SeoStructuredData;
use Illuminate\Support\Carbon;
use Tests\IntegrationTestCase;

class BlogPhase3PublicTest extends IntegrationTestCase
{
    public function test_seo_title_is_used_for_document_and_og_title(): void
    {
        $post = $this->makePublishedPost([
            'title' => 'Visible heading about decks',
            'slug' => 'seo-title-post',
            'seo_title' => 'Ottawa deck repair tips',
        ]);

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();

        $this->assertStringContainsString('Ottawa deck repair tips — Zanini Renovations', $html);
        $this->assertStringContainsString('property="og:title" content="Ottawa deck repair tips — Zanini Renovations"', $html);
        $this->assertStringContainsString('Visible heading about decks', $html);
    }

    public function test_featured_image_alt_is_used_for_img_and_og(): void
    {
        $post = $this->makePublishedPost([
            'slug' => 'alt-post',
            'featured_image_path' => 'blog-featured/1/deck.webp',
            'featured_image_alt' => 'Cedar deck in Ottawa after stain',
        ]);

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();

        $this->assertStringContainsString('alt="Cedar deck in Ottawa after stain"', $html);
        $this->assertStringContainsString('property="og:image:alt" content="Cedar deck in Ottawa after stain"', $html);
    }

    public function test_noindex_post_is_excluded_from_sitemap_and_sends_robots(): void
    {
        $live = $this->makePublishedPost(['slug' => 'public-live', 'noindex' => false]);
        $hidden = $this->makePublishedPost([
            'slug' => 'hidden-noindex',
            'noindex' => true,
            'title' => 'Hidden from search',
        ]);

        $page = $this->get(route('blog.show', $hidden))->assertOk();
        $page->assertSee('noindex, follow', false);
        $page->assertSee('Hidden from search');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/blog/public-live', $sitemap);
        $this->assertStringNotContainsString('/blog/hidden-noindex', $sitemap);
        $this->assertTrue($live->exists);
    }

    public function test_empty_listing_pages_return_404(): void
    {
        $this->makePublishedPost(['slug' => 'only-one']);

        $this->get(route('blog.index', ['page' => 99]))->assertNotFound();
        $this->get(route('blog.index', ['category' => 'does-not-exist']))->assertNotFound();
        $this->get(route('blog.index', ['tag' => 'missing-tag']))->assertNotFound();
    }

    public function test_empty_category_and_tag_with_no_posts_return_404(): void
    {
        BlogCategory::query()->create(['name' => 'Empty Cat', 'slug' => 'empty-cat']);
        BlogTag::query()->create(['name' => 'Empty Tag', 'slug' => 'empty-tag']);

        $this->get(route('blog.index', ['category' => 'empty-cat']))->assertNotFound();
        $this->get(route('blog.index', ['tag' => 'empty-tag']))->assertNotFound();
    }

    public function test_json_ld_includes_author_dates_and_image(): void
    {
        Carbon::setTestNow('2026-10-06 14:00:00');
        $post = $this->makePublishedPost([
            'slug' => 'jsonld-post',
            'title' => 'Fence repair after frost',
            'author_name' => 'Jonas Zanini',
            'published_at' => Carbon::parse('2026-10-06 09:00:00'),
            'featured_image_path' => 'blog-featured/1/fence.webp',
            'featured_image_alt' => 'Repaired fence in Gatineau',
        ]);
        $post->updated_at = Carbon::parse('2026-10-06 14:00:00');
        $post->saveQuietly();
        $post->setRelation('tags', collect());

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
        $this->assertStringContainsString('"name":"Jonas Zanini"', $html);
        $this->assertStringContainsString('datePublished', $html);
        $this->assertStringContainsString('dateModified', $html);
        $this->assertStringContainsString('Oct 6, 2026', $html);
        $this->assertStringContainsString('Free estimate', $html);
        $this->assertStringContainsString('tel:+16478347570', $html);

        $graph = SeoStructuredData::blogPosting($post->fresh()->load('tags'));
        $article = collect($graph['@graph'] ?? [])->first(fn ($node) => ($node['@type'] ?? '') === 'BlogPosting');
        $this->assertNotNull($article);
        $this->assertSame('Jonas Zanini', $article['author']['name'] ?? null);
        $this->assertNotEmpty($article['datePublished'] ?? null);
        $this->assertNotEmpty($article['dateModified'] ?? null);
        $this->assertIsArray($article['image'] ?? null);
        $this->assertSame('ImageObject', $article['image']['@type'] ?? null);
        $this->assertNotEmpty($article['image']['url'] ?? null);
    }

    public function test_cta_uses_service_copy_when_set(): void
    {
        $post = $this->makePublishedPost([
            'slug' => 'cta-decks',
            'cta_service' => 'decks',
            'html_content' => '<p>First paragraph about staining a deck.</p><p>Second paragraph continues.</p>',
        ]);

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();
        $this->assertStringContainsString('Need a deck?', $html);
        $this->assertStringContainsString('Free estimate', $html);
        $this->assertStringContainsString('tel:+16478347570', $html);
        $this->assertStringContainsString((string) config('seo.service_area_text'), $html);
        $this->assertStringNotContainsString('Ottawa or Gatineau', $html);
        $this->assertStringNotContainsString('licensed', strtolower($html));

        $introPos = strpos($html, 'First paragraph about staining a deck.');
        $ctaPos = strpos($html, 'Need a deck?');
        $secondPos = strpos($html, 'Second paragraph continues.');
        $this->assertNotFalse($introPos);
        $this->assertNotFalse($ctaPos);
        $this->assertNotFalse($secondPos);
        $this->assertTrue($introPos < $ctaPos && $ctaPos < $secondPos);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedPost(array $overrides = []): BlogPost
    {
        return BlogPost::query()->create(array_merge([
            'title' => 'Phase 3 public post',
            'slug' => 'phase-3-public-'.uniqid(),
            'excerpt' => 'A short excerpt.',
            'html_content' => '<p>Body copy for homeowners.</p>',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'author_name' => 'Jonas Zanini',
            'views_count' => 0,
            'noindex' => false,
        ], $overrides));
    }
}
