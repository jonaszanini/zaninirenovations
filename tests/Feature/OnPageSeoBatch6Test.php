<?php

namespace Tests\Feature;

use App\Support\PublicServices;
use App\Support\SeoStructuredData;
use App\Support\ServiceAreas;
use Tests\IntegrationTestCase;

class OnPageSeoBatch6Test extends IntegrationTestCase
{
    public function test_barrhaven_slug_matches_the_published_town_page(): void
    {
        $this->assertSame('barrhaven', config('service_areas.pages.barrhaven.slug'));
        $this->assertTrue((bool) config('service_areas.pages.barrhaven.published'));
    }

    public function test_new_service_and_town_pages_use_the_approved_copy(): void
    {
        $this->seedProjects();

        $services = [
            'tile-installation' => [
                'h1' => 'Tile Installation in Carleton Place and Ottawa',
                'service_type' => 'Tile installation',
                'faq' => 'Do you install tile floors as well as walls?',
            ],
            'accent-walls' => [
                'h1' => 'Accent Walls and Wainscoting in Carleton Place and Ottawa',
                'service_type' => 'Accent walls and wainscoting',
                'faq' => 'What\'s the difference between wainscoting and an accent wall?',
            ],
        ];

        foreach ($services as $slug => $expect) {
            $page = PublicServices::publishedPage($slug);
            $this->assertNotNull($page);
            $html = $this->get('/services/'.$slug)->assertOk()->getContent();
            $visible = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertSame((string) $page['title'], $this->documentTitle($html));
            $this->assertStringContainsString('<h1>'.e($expect['h1']).'</h1>', $html);
            $this->assertStringContainsString($expect['faq'], $visible);
            $this->assertStringNotContainsString('[Q:', $visible);
            $this->assertStringNotContainsString('loading="lazy"', $this->heroImg($html));

            $graph = SeoStructuredData::servicePage($page);
            $service = collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'Service');
            $this->assertSame($expect['service_type'], $service['serviceType']);
            $this->assertNotNull(collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'FAQPage'));
            $this->assertNotNull(collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'WebPage'));
            $this->assertNotNull(collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'BreadcrumbList'));
        }

        $towns = [
            'almonte-mississippi-mills' => 'Carpentry and renovations in Almonte and Mississippi Mills',
            'perth' => 'Carpentry and renovations in Perth',
            'nepean' => 'Carpentry and renovations in Nepean',
        ];
        foreach ($towns as $slug => $h1) {
            $page = ServiceAreas::publishedPage($slug);
            $this->assertNotNull($page);
            $html = $this->get('/service-areas/'.$slug)->assertOk()->getContent();
            $this->assertStringContainsString('<h1>'.e($h1).'</h1>', $html);
            $this->assertStringContainsString('FAQPage', $html);
            $this->assertStringContainsString('BreadcrumbList', $html);
            $this->assertStringContainsString('WebPage', $html);
            $this->assertStringNotContainsString('[Q:', $html);
            $this->assertStringNotContainsString('travel fee', strtolower($html));
        }

        $nepean = $this->get('/service-areas/nepean')->assertOk()->getContent();
        $this->assertStringContainsString('/service-areas/barrhaven', $nepean);
        $this->assertStringContainsString('existing cabinet boxes', $nepean);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach ([
            '/services/tile-installation',
            '/services/accent-walls',
            '/service-areas/almonte-mississippi-mills',
            '/service-areas/perth',
            '/service-areas/nepean',
        ] as $path) {
            $this->assertStringContainsString($path.'</loc>', $xml);
        }

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/service-areas/almonte-mississippi-mills', $home);
        $this->assertStringContainsString('/service-areas/perth', $home);
        $this->assertStringContainsString('/service-areas/nepean', $home);
        $this->assertStringContainsString('/services/tile-installation', $home);
        $this->assertStringContainsString('/services/accent-walls', $home);
        $this->assertStringContainsString('>Tile</a>', $home);
        $this->assertStringContainsString('>Accent Walls</a>', $home);
    }

    private function seedProjects(): void
    {
        foreach ([
            ['backsplash-installation', 'Backsplash installation', 'Kanata'],
            ['custom-classic-fireplace-mantel', 'Custom classic fireplace mantel', 'Perth'],
            ['fireplace-renovation', 'Fireplace renovation', 'Kanata'],
            ['fireplace-surround-niche-carleton-place', 'Fireplace surround with niche', 'Carleton Place'],
            ['built-in-shelving-wall-kanata', 'Built-in shelving wall', 'Kanata'],
            ['gabled-porch-roof', 'Gabled porch roof', 'Almonte'],
            ['kitchen-renovations', 'Kitchen renovations', 'Nepean'],
            ['engineering-hard-wood-flooring-installation', 'Engineered Hardwood Flooring Installation in Ashton', 'Ashton'],
            ['basement-finishing', 'Basement finishing', 'Barrhaven'],
            ['custom-bathroom-vanity-barrhaven', 'Custom bathroom vanity', 'Barrhaven'],
        ] as [$slug, $title, $town]) {
            \App\Models\Project::query()->create([
                'title' => $title,
                'slug' => $slug,
                'town' => $town,
                'description' => $title,
                'completed_on' => '2026-06-01',
            ]);
        }
    }

    private function documentTitle(string $html): string
    {
        $this->assertSame(1, preg_match('/<title>(.*?)<\/title>/s', $html, $m));

        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function heroImg(string $html): string
    {
        $this->assertSame(1, preg_match('/<figure class="pp-hero__media">(.*?)<\/figure>/s', $html, $m));

        return $m[1];
    }
}
