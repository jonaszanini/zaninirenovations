<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Project;
use App\Support\LlmsTxtGenerator;
use App\Support\PublicSitemap;
use App\Support\SeoStructuredData;
use Tests\IntegrationTestCase;

class OnPageSeoBatch2Test extends IntegrationTestCase
{
    public function test_leftover_public_files_are_removed(): void
    {
        $this->assertFileDoesNotExist(public_path('contact_email.php'));
        $this->assertFileDoesNotExist(public_path('admin-login.html'));
    }

    public function test_404_has_no_canonical_and_is_noindex(): void
    {
        $html = $this->get('/about-nonexistent')->assertStatus(404)->getContent();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
    }

    public function test_500_view_has_no_canonical_and_is_noindex(): void
    {
        $html = view('errors.500', [
            'exception' => new \RuntimeException('test'),
        ])->render();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
    }

    public function test_homepage_cta_and_footer_copy(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Work directly with Jonas, from the first visit to the final walkthrough.', $html);
        $this->assertStringNotContainsString('Client oriented, long experienced', $html);
        $this->assertStringContainsString('Zanini Renovations is a carpentry and renovation business based in Carleton Place, in business since 2020, handling residential and commercial work, including kitchen remodels.', $html);
        $this->assertStringContainsString('in business since 2020', $html);
        $this->assertStringContainsString('residential and commercial work, including kitchen remodels', $html);
        $this->assertStringNotContainsString('best professional pricing', $html);
        $this->assertStringNotContainsString("Ontario's trusted expert", $html);
        $this->assertStringNotContainsString('Ontario’s trusted expert', $html);
    }

    public function test_warranty_meta_is_at_most_155_and_ends_on_a_word(): void
    {
        $html = $this->get('/warranty')->assertOk()->getContent();
        $desc = $this->metaDescription($html);

        $this->assertNotSame('', $desc);
        $this->assertLessThanOrEqual(155, mb_strlen($desc));
        $this->assertMatchesRegularExpression('/[A-Za-z0-9.]$/u', $desc);
        $this->assertStringNotContainsString('unattache', $desc);
        $this->assertStringNotContainsString('WSIB', $desc);
        $this->assertSame(
            'One-year workmanship warranty and $3M general liability insurance, explained. Renovate with confidence in Lanark County and Ottawa.',
            $desc
        );
    }

    public function test_blog_hub_title_is_at_most_60_characters(): void
    {
        $html = $this->get('/blog')->assertOk()->getContent();
        $title = $this->documentTitle($html);

        $this->assertSame('Renovation & Carpentry Tips for Ottawa Homeowners | Zanini', $title);
        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }

    public function test_blog_post_title_prefers_seo_title_and_stays_within_60(): void
    {
        $short = BlogPost::query()->create($this->postAttrs([
            'title' => 'A very long visible heading about pressure treated decking versus cedar',
            'slug' => 'short-seo-title-post',
            'seo_title' => 'Pressure-treated vs cedar decking',
        ]));
        $long = BlogPost::query()->create($this->postAttrs([
            'title' => 'The ultimate homeowners guide to reviving your deck this weekend with stain and sealer',
            'slug' => 'long-title-no-seo',
            'seo_title' => null,
        ]));

        $shortHtml = $this->get(route('blog.show', $short))->assertOk()->getContent();
        $shortTitle = $this->documentTitle($shortHtml);
        $this->assertSame('Pressure-treated vs cedar decking', $shortTitle);
        $this->assertLessThanOrEqual(60, mb_strlen($shortTitle));

        $longHtml = $this->get(route('blog.show', $long))->assertOk()->getContent();
        $longTitle = $this->documentTitle($longHtml);
        $this->assertLessThanOrEqual(60, mb_strlen($longTitle));
        $this->assertStringNotContainsString('  ', $longTitle);
    }

    public function test_blog_post_meta_description_is_at_most_155_at_a_word_boundary(): void
    {
        $post = BlogPost::query()->create($this->postAttrs([
            'slug' => 'long-meta-post',
            'meta_description' => 'Protect your deck from moisture, UV, and winter damage, plus many extra words that would previously be chopped mid-word leaving a trailing fragment in the live meta snippet for Ottawa homeowners this season.',
        ]));

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();
        $desc = $this->metaDescription($html);
        $this->assertLessThanOrEqual(155, mb_strlen($desc));
        $this->assertDoesNotMatchRegularExpression('/,$/u', $desc);
        $this->assertStringNotContainsString('unattache', $desc);
    }

    public function test_project_meta_and_json_ld_truncate_at_a_word_boundary(): void
    {
        $prefix = substr('A custom floating deck with hidden fasteners, gravel around the posts, and a clean finish built and more detail and more detail and more detail a', 0, 145).' ';
        $this->assertSame(146, mb_strlen($prefix));
        $project = Project::query()->create([
            'title' => 'Floating deck',
            'slug' => 'floating-deck-word-boundary',
            'description' => $prefix.'unattached sheds in the Ottawa region with extra filler for search snippets.',
        ]);

        $naive = mb_substr($project->description, 0, 155);
        $this->assertStringEndsWith('unattache', $naive);

        $desc = $project->seoDescription();
        $this->assertLessThanOrEqual(155, mb_strlen($desc));
        $this->assertStringNotContainsString('unattache', $desc);
        $this->assertDoesNotMatchRegularExpression('/unattache(?!d)/u', $desc);

        $graph = SeoStructuredData::portfolioProject($project->fresh()->setRelation('photos', collect())->setRelation('categories', collect()));
        $gallery = collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'ImageGallery');
        $page = collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'WebPage');
        $this->assertSame($desc, $gallery['description'] ?? null);
        $this->assertSame($desc, $page['description'] ?? null);
        $this->assertLessThanOrEqual(155, mb_strlen((string) ($gallery['description'] ?? '')));
    }

    public function test_area_served_faq_llms_and_json_ld_come_from_config(): void
    {
        $areaText = (string) config('seo.service_area_text');
        $areas = config('seo.area_served');

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString($areaText, $home);
        $this->assertStringNotContainsString('Orléans', $home);
        $this->assertStringNotContainsString('Orleans', $home);

        $txt = LlmsTxtGenerator::generate(collect(), collect());
        $this->assertStringContainsString($areaText, $txt);
        $this->assertStringNotContainsString('Orléans', $txt);

        $graph = SeoStructuredData::homeGraph();
        $business = collect($graph['@graph'])->first(
            fn ($node) => ($node['@type'] ?? '') === 'HomeAndConstructionBusiness'
        );
        $this->assertSame($areas, $business['areaServed'] ?? null);
        $this->assertSame('2020', $business['foundingDate'] ?? null);

        $encoded = SeoStructuredData::toJsonLd($graph);
        $this->assertStringNotContainsString('"@type":"ItemList"', $encoded);
        $this->assertStringNotContainsString('Barrhaven', $encoded);
        $this->assertStringNotContainsString('Orléans', $encoded);
        $this->assertStringContainsString($areaText, $encoded);

        foreach ($areas as $place) {
            $name = (string) ($place['name'] ?? '');
            $this->assertNotSame('', $name);
            $this->assertTrue(
                str_contains($areaText, $name),
                "areaServed name [{$name}] must appear in seo.service_area_text"
            );
        }
    }

    public function test_public_pages_do_not_claim_licensed_wsib_or_one_person(): void
    {
        foreach (['/', '/warranty', '/blog', '/portfolio', '/privacy-policy', '/terms-and-conditions', '/llms.txt'] as $path) {
            $content = strtolower($this->get($path)->assertOk()->getContent());
            $this->assertStringNotContainsString('licensed', $content, $path.' must not contain licensed');
            $this->assertStringNotContainsString('wsib', $content, $path.' must not contain WSIB');
            $this->assertStringNotContainsString('workplace safety', $content, $path.' must not contain Workplace Safety');
            $this->assertStringNotContainsString('one-person', $content, $path.' must not contain one-person');
            $this->assertStringNotContainsString('one person', $content, $path.' must not contain one person');
        }

        $notFound = strtolower($this->get('/about-nonexistent')->assertStatus(404)->getContent());
        $this->assertStringNotContainsString('licensed', $notFound);
        $this->assertStringNotContainsString('wsib', $notFound);
        $this->assertStringNotContainsString('one-person', $notFound);
        $this->assertStringNotContainsString('one person', $notFound);

        $graph = strtolower(SeoStructuredData::toJsonLd(SeoStructuredData::homeGraph()));
        $this->assertStringNotContainsString('wsib', $graph);
        $this->assertStringNotContainsString('one-person', $graph);
        $this->assertStringNotContainsString('one person', $graph);
    }

    public function test_blog_tag_and_category_are_noindex_below_the_threshold(): void
    {
        $category = BlogCategory::query()->create(['name' => 'Decks', 'slug' => 'decks-filter']);
        $tag = BlogTag::query()->create(['name' => 'Trim', 'slug' => 'trim-filter']);
        $post = BlogPost::query()->create($this->postAttrs([
            'slug' => 'filter-index-post',
            'title' => 'Deck stain tips',
        ]));
        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);

        config(['seo.blog_filter_index_min_posts' => 8]);

        $catHtml = $this->get('/blog?category=decks-filter')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex, follow"', $catHtml);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.index', ['category' => 'decks-filter']).'"', $catHtml);

        $tagHtml = $this->get('/blog?tag=trim-filter')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex, follow"', $tagHtml);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.index', ['tag' => 'trim-filter']).'"', $tagHtml);

        $hub = $this->get('/blog')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="index,follow"', $hub);
    }

    public function test_blog_tag_and_category_are_indexable_once_the_threshold_is_met(): void
    {
        $category = BlogCategory::query()->create(['name' => 'Fences', 'slug' => 'fences-filter']);
        $first = BlogPost::query()->create($this->postAttrs([
            'slug' => 'fence-filter-post',
            'title' => 'Fence posts',
        ]));
        $first->categories()->attach($category->id);

        for ($i = 2; $i <= 8; $i++) {
            BlogPost::query()->create($this->postAttrs([
                'slug' => 'extra-published-'.$i,
                'title' => 'Extra post '.$i,
            ]));
        }

        config(['seo.blog_filter_index_min_posts' => 8]);

        $html = $this->get('/blog?category=fences-filter')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="index,follow"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.index', ['category' => 'fences-filter']).'"', $html);
    }

    public function test_blog_search_is_always_noindex(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            BlogPost::query()->create($this->postAttrs([
                'slug' => 'search-post-'.$i,
                'title' => 'Searchable deck article '.$i,
            ]));
        }

        config(['seo.blog_filter_index_min_posts' => 8]);

        $html = $this->get('/blog?q=deck')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.index').'"', $html);
        $this->assertStringContainsString('property="og:url" content="'.route('blog.index').'"', $html);
    }

    public function test_sitemap_never_lists_blog_tag_or_category_query_urls(): void
    {
        $category = BlogCategory::query()->create(['name' => 'Stairs', 'slug' => 'stairs-filter']);
        $post = BlogPost::query()->create($this->postAttrs([
            'slug' => 'stairs-sitemap-post',
            'title' => 'Stair tips',
        ]));
        $post->categories()->attach($category->id);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('?tag=', $xml);
        $this->assertStringNotContainsString('?category=', $xml);
        $this->assertStringNotContainsString('/blog?tag=', $xml);
        $this->assertStringNotContainsString('/blog?category=', $xml);

        $urls = PublicSitemap::urls();
        foreach ($urls as $row) {
            $this->assertStringNotContainsString('?tag=', $row['loc']);
            $this->assertStringNotContainsString('?category=', $row['loc']);
        }
    }

    public function test_og_url_matches_the_canonical_url(): void
    {
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertSame($this->canonicalHref($home), $this->ogUrl($home));

        $warranty = $this->get('/warranty')->assertOk()->getContent();
        $this->assertSame($this->canonicalHref($warranty), $this->ogUrl($warranty));
        $this->assertSame(url('/warranty'), $this->ogUrl($warranty));
    }

    public function test_homepage_scripts_are_deferred_and_contact_does_not_use_legacy_php(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<script src="[^"]*jquery\.min\.js"[^>]*defer/i', $html);
        $this->assertMatchesRegularExpression('/<script src="[^"]*custom-scripts\.js[^"]*"[^>]*defer/i', $html);
        $this->assertStringNotContainsString('contact_email.php', $html);
        $this->assertStringContainsString('name="contact-post-url" content="'.url('/contact-email').'"', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postAttrs(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Published post',
            'slug' => 'published-post-'.uniqid(),
            'excerpt' => 'A short excerpt.',
            'html_content' => '<p>Body copy for homeowners.</p>',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'author_name' => 'Jonas Zanini',
            'views_count' => 0,
            'noindex' => false,
        ], $overrides);
    }

    private function documentTitle(string $html): string
    {
        $this->assertSame(1, preg_match('/<title>(.*?)<\/title>/s', $html, $m));

        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function metaDescription(string $html): string
    {
        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)"/', $html, $m));

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function canonicalHref(string $html): string
    {
        $this->assertSame(1, preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $m));

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function ogUrl(string $html): string
    {
        $this->assertSame(1, preg_match('/<meta property="og:url" content="([^"]*)"/', $html, $m));

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
