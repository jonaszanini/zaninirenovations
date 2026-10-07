<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\ServiceAreas;
use Tests\IntegrationTestCase;

class OnPageSeoBatch5Test extends IntegrationTestCase
{
    /** @var list<string> */
    private const BANNED = [
        'licensed',
        'wsib',
        'one-person',
        'one person',
        'solo',
        '$3m',
        '$3 m',
        '$3 million',
        'orléans',
        'orleans',
    ];

    public function test_published_town_pages_and_hub_return_200_with_seo_tags(): void
    {
        $this->seedPlanProjects();

        $pages = [
            '/service-areas' => (string) config('service_areas.hub.title'),
            '/service-areas/carleton-place' => (string) config('service_areas.pages.carleton-place.title'),
            '/service-areas/kanata' => (string) config('service_areas.pages.kanata.title'),
            '/service-areas/barrhaven' => (string) config('service_areas.pages.barrhaven.title'),
        ];

        foreach ($pages as $path => $title) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame($title, $this->documentTitle($html), $path);
            $this->assertNotSame('', $this->metaDescription($html), $path);
            $this->assertLessThanOrEqual(155, mb_strlen($this->metaDescription($html)), $path);
            $this->assertSame(url($path), $this->canonicalHref($html), $path);
            $this->assertStringNotContainsString('data-sticky_header', $html, $path);
            $this->assertStringContainsString('Get a free estimate', $html, $path);
            $this->assertStringContainsString('sa-btn', $html, $path);
            $this->assertStringContainsString('fully insured', strtolower($html), $path);
            $this->assertStringContainsString('qualified trades', strtolower($html), $path);
        }

        $kanata = $this->get('/service-areas/kanata')->assertOk()->getContent();
        $this->assertStringContainsString('Kanata &amp; Stittsville', $kanata);
        $this->assertStringContainsString('Carpentry, Basements &amp; Decks in Kanata &amp; Stittsville', $kanata);

        $cp = $this->get('/service-areas/carleton-place')->assertOk()->getContent();
        $this->assertStringContainsString('Beckwith', $cp);
        $this->assertStringContainsString('Franktown', $cp);
    }

    public function test_sitemap_lists_hub_and_published_towns_only(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/service-areas</loc>', $xml);
        $this->assertStringContainsString('/service-areas/carleton-place</loc>', $xml);
        $this->assertStringContainsString('/service-areas/kanata</loc>', $xml);
        $this->assertStringContainsString('/service-areas/barrhaven</loc>', $xml);
        $this->assertStringNotContainsString('/service-areas/almonte</loc>', $xml);
        $this->assertStringNotContainsString('/service-areas/stittsville</loc>', $xml);
        $this->assertStringNotContainsString('/service-areas/perth</loc>', $xml);
        $this->assertStringNotContainsString('/service-areas/orleans</loc>', $xml);
    }

    public function test_unpublished_town_pages_are_404(): void
    {
        $this->get('/service-areas/almonte')->assertNotFound();
        $this->get('/service-areas/stittsville')->assertNotFound();
        $this->get('/service-areas/perth')->assertNotFound();
        $this->get('/service-areas/orleans')->assertNotFound();
    }

    public function test_banned_words_are_absent_from_new_pages(): void
    {
        $this->seedPlanProjects();

        foreach (['/service-areas', '/service-areas/carleton-place', '/service-areas/kanata', '/service-areas/barrhaven'] as $path) {
            $html = strtolower($this->get($path)->assertOk()->getContent());
            foreach (self::BANNED as $word) {
                $this->assertStringNotContainsString($word, $html, $path.' contains banned “'.$word.'”');
            }
        }

        foreach (ServiceAreas::publishedPages() as $page) {
            $blob = strtolower(ServiceAreas::uniqueCopyText($page).' '.(string) ($page['title'] ?? '').' '.(string) ($page['meta_description'] ?? '').' '.(string) ($page['h1'] ?? ''));
            foreach (self::BANNED as $word) {
                $this->assertStringNotContainsString($word, $blob, ($page['slug'] ?? '').' copy contains banned “'.$word.'”');
            }
            $this->assertGreaterThanOrEqual(400, ServiceAreas::wordCount($page), ($page['slug'] ?? '').' needs ≥400 unique words');
        }

        $hub = strtolower(implode(' ', (array) config('service_areas.hub.intro_paragraphs', [])));
        foreach (self::BANNED as $word) {
            $this->assertStringNotContainsString($word, $hub, 'hub copy contains banned “'.$word.'”');
        }
    }

    public function test_town_pages_link_plan_projects_and_json_ld(): void
    {
        $this->seedPlanProjects();

        $map = [
            'carleton-place' => (array) config('service_areas.pages.carleton-place.project_slugs'),
            'kanata' => (array) config('service_areas.pages.kanata.project_slugs'),
            'barrhaven' => (array) config('service_areas.pages.barrhaven.project_slugs'),
        ];

        foreach ($map as $slug => $projectSlugs) {
            $html = $this->get('/service-areas/'.$slug)->assertOk()->getContent();
            foreach ($projectSlugs as $projectSlug) {
                $this->assertStringContainsString('/portfolio/'.$projectSlug, $html, $slug.' missing '.$projectSlug);
            }
            $this->assertStringContainsString('LocalBusiness', $html);
            $this->assertStringContainsString('HomeAndConstructionBusiness', $html);
            $this->assertStringContainsString('Service', $html);
            $this->assertStringContainsString('FAQPage', $html);
        }
    }

    public function test_hub_lists_plain_text_towns_with_project_links_and_footer_points_here(): void
    {
        $this->seedPlanProjects();

        $html = $this->get('/service-areas')->assertOk()->getContent();
        $this->assertStringContainsString('/service-areas/carleton-place', $html);
        $this->assertStringContainsString('/service-areas/kanata', $html);
        $this->assertStringContainsString('/service-areas/barrhaven', $html);
        $this->assertStringContainsString('sa-town__name--plain', $html);
        $this->assertStringContainsString('Perth', $html);
        $this->assertStringContainsString('/portfolio/custom-classic-fireplace-mantel', $html);
        $this->assertStringContainsString('/portfolio/gabled-porch-roof', $html);
        $this->assertStringContainsString('Gatineau, QC', $html);
        $this->assertStringNotContainsString('Orléans', $html);
        $this->assertStringNotContainsString('Orleans', $html);

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/service-areas', $home);
        $this->assertStringContainsString('Areas we serve', $home);
    }

    public function test_town_copy_is_not_a_shared_template(): void
    {
        $pages = ServiceAreas::publishedPages();
        $this->assertCount(3, $pages);

        $texts = [];
        foreach ($pages as $page) {
            $texts[$page['slug']] = ServiceAreas::uniqueCopyText($page);
        }

        $slugs = array_keys($texts);
        for ($i = 0; $i < count($slugs); $i++) {
            for ($j = $i + 1; $j < count($slugs); $j++) {
                $a = $this->sentences($texts[$slugs[$i]]);
                $b = $this->sentences($texts[$slugs[$j]]);
                $shared = array_values(array_intersect($a, $b));
                $this->assertSame(
                    [],
                    $shared,
                    $slugs[$i].' and '.$slugs[$j].' share sentences'
                );
            }
        }
    }

    private function seedPlanProjects(): void
    {
        $rows = [
            ['three-fences-at-once', 'Three fences at once', 'Carleton Place'],
            ['basement-flood-restoration', 'Basement flood restoration', 'Carleton Place'],
            ['outside-stairs', 'Outside stairs', 'Carleton Place'],
            ['fence', 'Privacy fence with gate', 'Carleton Place'],
            ['fence-4', 'Backyard privacy fence with gate', 'Carleton Place'],
            ['backyard-privacy-fence-carleton-place', 'Backyard privacy fence', 'Carleton Place'],
            ['deck-stairs-landing-carleton-place', 'Deck stairs and landing', 'Carleton Place'],
            ['side-yard-fence-gates-carleton-place', 'Side yard fence and gates', 'Carleton Place'],
            ['fireplace-surround-niche-carleton-place', 'Fireplace surround with niche', 'Carleton Place'],
            ['floating-deck-with-gravel-around', 'Floating deck with gravel around', 'Kanata'],
            ['fireplace-renovation', 'Fireplace renovation', 'Kanata'],
            ['backsplash-installation', 'Backsplash installation', 'Kanata'],
            ['backyard-patio-pad-kanata', 'Backyard patio pad', 'Kanata'],
            ['built-in-shelving-wall-kanata', 'Built-in shelving wall', 'Kanata'],
            ['fence-3', 'Backyard fence and gate', 'Stittsville'],
            ['basement-finishing', 'Basement finishing', 'Barrhaven'],
            ['built-in-window-storage', 'Built-in window storage', 'Barrhaven'],
            ['fence-2', 'Backyard privacy fence with gate', 'Barrhaven'],
            ['custom-bathroom-vanity-barrhaven', 'Custom bathroom vanity', 'Barrhaven'],
            ['gabled-porch-roof', 'Gabled porch roof', 'Almonte'],
            ['custom-classic-fireplace-mantel', 'Custom classic fireplace mantel', 'Perth'],
            ['kitchen-renovations', 'Kitchen renovations', 'Nepean'],
            ['side-walkway-backyard-patio-riverside-south', 'Side walkway and backyard patio', 'Riverside South'],
            ['basement-closet-doors-gatineau', 'Basement closet doors', 'Gatineau, QC'],
            ['catio', 'Catio', 'South Keys'],
            ['2nd-floor-and-stairs-renovation', '2nd floor and stairs renovation', 'South Keys'],
            ['lattice-fence', 'Lattice fence', 'Alta Vista'],
            ['engineering-hard-wood-flooring-installation', 'Engineered hardwood flooring', 'Ashton'],
            ['custom-shed-doors', 'Custom shed doors', 'West Carleton'],
        ];

        foreach ($rows as [$slug, $title, $town]) {
            Project::query()->create([
                'title' => $title,
                'slug' => $slug,
                'town' => $town,
                'description' => $title.' in '.$town.'.',
                'completed_on' => '2026-06-01',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $norm = strtolower(trim($part, " \t\n\r\0\x0B\"'"));
            if (mb_strlen($norm) >= 20) {
                $out[] = $norm;
            }
        }

        return $out;
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
}
