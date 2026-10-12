<?php

namespace Tests\Unit\Support;

use App\Models\BlogPost;
use App\Models\Project;
use App\Support\LlmsTxtGenerator;
use App\Support\PublicSitemap;
use App\Support\RobotsTxtGenerator;
use App\Support\SeoStructuredData;
use Carbon\Carbon;
use Tests\TestCase;

class SeoGeoTest extends TestCase
{
    public function test_robots_txt_allows_ai_search_bots_and_points_at_llms_txt(): void
    {
        $txt = RobotsTxtGenerator::generate();

        $this->assertStringContainsString('User-agent: OAI-SearchBot', $txt);
        $this->assertStringContainsString('User-agent: GPTBot', $txt);
        $this->assertStringContainsString('Sitemap:', $txt);
        $this->assertStringContainsString('/llms.txt', $txt);
        $this->assertStringContainsString('Disallow: /admin', $txt);
    }

    public function test_llms_txt_states_labour_facts_without_admin_paths(): void
    {
        $txt = LlmsTxtGenerator::generate(collect(), collect());

        $this->assertStringContainsString('# Zanini Renovations', $txt);
        $this->assertStringContainsString('Fully insured', $txt);
        $this->assertStringContainsString('(647) 834-7570', $txt);
        $this->assertStringContainsString('/warranty', $txt);
        $this->assertStringNotContainsString('/admin', $txt);
        $this->assertStringNotContainsString('$2M', $txt);
    }

    public function test_llms_txt_lists_supplied_blog_posts(): void
    {
        $post = new BlogPost([
            'title' => 'Deck footings in Ottawa',
            'slug' => 'deck-footings-ottawa',
            'excerpt' => 'Footings must sit below the frost line.',
        ]);

        $txt = LlmsTxtGenerator::generate(collect([$post]), collect());

        $this->assertStringContainsString('## Blog', $txt);
        $this->assertStringContainsString('/blog/deck-footings-ottawa', $txt);
        $this->assertStringContainsString('Deck footings in Ottawa', $txt);
    }

    public function test_sitemap_static_lastmod_matches_view_file_mtime(): void
    {
        $path = resource_path('views/site/warranty.blade.php');
        $urls = PublicSitemap::urls(collect(), collect());
        $warranty = collect($urls)->first(fn ($row) => str_contains($row['loc'], '/warranty'));

        $this->assertNotNull($warranty);
        $this->assertSame(PublicSitemap::lastmodFromFile($path), $warranty['lastmod']);
    }

    public function test_sitemap_uses_blog_post_updated_at_for_article_url(): void
    {
        $post = new BlogPost(['slug' => 'fence-heights', 'title' => 'Fence heights']);
        $post->updated_at = Carbon::parse('2024-06-01 12:00:00', config('app.timezone'));

        $urls = PublicSitemap::urls(collect([$post]), collect());
        $article = collect($urls)->first(fn ($row) => str_contains($row['loc'], '/blog/fence-heights'));

        $this->assertNotNull($article);
        $this->assertSame($post->updated_at->toAtomString(), $article['lastmod']);
    }

    public function test_home_graph_knows_about_is_a_topic_list(): void
    {
        $graph = SeoStructuredData::homeGraph();
        $business = collect($graph['@graph'])->first(
            fn ($node) => ($node['@type'] ?? '') === 'HomeAndConstructionBusiness'
        );

        $this->assertIsArray($business['knowsAbout']);
        $this->assertContains('Deck construction', $business['knowsAbout']);
        $this->assertNotContains('Red Seal carpentry', $business['knowsAbout']);
        $this->assertArrayNotHasKey('aggregateRating', $business);
        $this->assertArrayNotHasKey('review', $business);
        $this->assertArrayNotHasKey('geo', $business, 'Geo coordinates should not be published for a service-area business');
        $this->assertSame('2020', $business['foundingDate']);
        $this->assertSame(config('seo.area_served'), $business['areaServed']);
        $this->assertContains(
            'https://maps.google.com/?cid=7676669856408637146',
            $business['sameAs']
        );
        $webpage = collect($graph['@graph'])->first(
            fn ($node) => ($node['@type'] ?? '') === 'WebPage'
        );
        $this->assertSame((string) config('seo.home_title'), $webpage['name']);
        $this->assertStringContainsString('fully insured', strtolower((string) $business['description']));
        $encoded = SeoStructuredData::toJsonLd($graph);
        $this->assertStringNotContainsString('$2M', $encoded);
        $this->assertStringNotContainsString('aggregateRating', $encoded);
        $this->assertStringNotContainsString('Red Seal', $encoded);
    }

    public function test_blog_posting_json_ld_describes_the_article(): void
    {
        $post = new BlogPost([
            'title' => 'How long a wood fence lasts in Ottawa',
            'slug' => 'wood-fence-lifespan-ottawa',
            'excerpt' => 'A well-built cedar fence typically lasts 15 to 20 years.',
            'html_content' => '<p>A well-built cedar fence typically lasts 15 to 20 years with staining.</p>',
            'author_name' => 'Jonas Zanini',
        ]);
        $post->published_at = Carbon::parse('2025-04-01 09:00:00', config('app.timezone'));
        $post->updated_at = Carbon::parse('2025-04-02 09:00:00', config('app.timezone'));
        $post->setRelation('tags', collect());

        $data = SeoStructuredData::blogPosting($post);
        $article = collect($data['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'BlogPosting');

        $this->assertNotNull($article);
        $this->assertSame($post->title, $article['headline']);
        $this->assertSame('Jonas Zanini', $article['author']['name']);
        $this->assertStringContainsString('#person-jonas', $article['author']['@id']);
        $this->assertSame($post->published_at->toAtomString(), $article['datePublished']);
    }

    public function test_sitemap_includes_a_portfolio_project_url(): void
    {
        $project = new Project([
            'title' => 'Deck for Gazebo',
            'slug' => 'deck-for-gazebo',
        ]);
        $project->updated_at = Carbon::parse('2026-06-09 15:00:00', config('app.timezone'));

        $urls = PublicSitemap::urls(collect(), collect([$project]));
        $page = collect($urls)->first(fn ($row) => str_ends_with($row['loc'], '/portfolio/deck-for-gazebo'));
        $index = collect($urls)->first(fn ($row) => str_ends_with($row['loc'], '/portfolio'));

        $this->assertNotNull($page);
        $this->assertSame($project->updated_at->toAtomString(), $page['lastmod']);
        $this->assertNotNull($index);
    }

    public function test_llms_txt_lists_supplied_portfolio_projects(): void
    {
        $project = new Project([
            'title' => 'Lattice fence',
            'slug' => 'lattice-fence',
            'description' => 'Cedar lattice along the side yard.',
        ]);

        $txt = LlmsTxtGenerator::generate(collect(), collect([$project]));

        $this->assertStringContainsString('## Portfolio', $txt);
        $this->assertStringContainsString('/portfolio/lattice-fence', $txt);
        $this->assertStringContainsString('Lattice fence', $txt);
        $this->assertStringContainsString('/portfolio):', $txt);
    }

    public function test_portfolio_project_json_ld_describes_the_gallery(): void
    {
        $project = new Project([
            'title' => 'Fireplace renovation',
            'slug' => 'fireplace-renovation',
            'description' => 'New mantel and tile surround.',
            'meta_title' => 'Fireplace renovation in Ottawa',
        ]);
        $project->completed_on = Carbon::parse('2026-06-05');
        $project->setRelation('photos', collect());
        $project->setRelation('categories', collect());

        $data = SeoStructuredData::portfolioProject($project);
        $gallery = collect($data['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'ImageGallery');
        $crumb = collect($data['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'BreadcrumbList');

        $this->assertNotNull($gallery);
        $this->assertSame('Fireplace renovation', $gallery['name']);
        $this->assertSame('2026-06-05', $gallery['datePublished']);
        $this->assertNotNull($crumb);
        $this->assertSame('Fireplace renovation in Ottawa', $project->seoTitle());
    }
}
