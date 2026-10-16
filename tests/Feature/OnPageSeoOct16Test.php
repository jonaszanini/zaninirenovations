<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Support\BusinessContact;
use App\Support\SeoStructuredData;
use Tests\IntegrationTestCase;

class OnPageSeoOct16Test extends IntegrationTestCase
{
    /** @var list<string> */
    private const JSONLD_AREA_SERVED = [
        'Carleton Place',
        'Beckwith',
        'Ottawa',
        'Kanata',
        'Stittsville',
        'Barrhaven',
        'Nepean',
        'Almonte',
        'Perth',
        'Smiths Falls',
        'Gatineau',
    ];

    /** @var list<string> */
    private const JSONLD_KNOWS_ABOUT = [
        'Carpentry',
        'Decks',
        'Fences',
        'Basements',
        'Kitchens',
        'Flooring',
    ];

    public function test_header_nav_includes_service_areas_for_desktop_and_mobile_menu(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match(
            '/<div class="navbar-collapse collapse pull-left" id="main-nav-collapse">(.*?)<div class="navbar-header pull-right">/s',
            $html,
            $nav
        ));
        $menu = $nav[1];

        $this->assertStringContainsString('Service Areas', $menu);
        $this->assertStringContainsString('href="'.url('/service-areas').'"', $menu);
        $this->assertStringContainsString('All service areas', $menu);
        $this->assertStringContainsString('/service-areas/carleton-place', $menu);
        $this->assertStringContainsString('/service-areas/kanata', $menu);
        $this->assertStringContainsString('/service-areas/barrhaven', $menu);
        $this->assertStringContainsString('data-toggle="dropdown"', $menu);
        $this->assertStringContainsString('navbar-toggle', $html);
        $this->assertStringContainsString('data-target="#main-nav-collapse"', $html);
    }

    public function test_footer_services_has_one_link_per_url(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match(
            '/<h3>SERVICES<\/h3>(.*?)<h3>BUSINESS HOURS<\/h3>/s',
            $html,
            $block
        ));
        $this->assertSame(1, preg_match_all('/<ul class="footer-useful-links[^"]*">(.*?)<\/ul>/s', $block[1], $lists));
        $this->assertGreaterThan(0, preg_match_all('/href="([^"]+)"/', $lists[1][0], $hrefs));

        $urls = $hrefs[1];
        $this->assertNotEmpty($urls);
        $this->assertSame($urls, array_values(array_unique($urls)), 'Footer SERVICES must keep only one link per URL.');
        $this->assertContains(url('/services/finish-carpentry-trim'), $urls);
        $this->assertContains(url('/services/flooring-installation'), $urls);
        $this->assertSame(1, substr_count($lists[1][0], url('/services/finish-carpentry-trim')));
        $this->assertSame(1, substr_count($lists[1][0], url('/services/flooring-installation')));
        $this->assertStringNotContainsString('Accent Walls', $lists[1][0]);
        $this->assertDoesNotMatchRegularExpression('/>Tile</', $lists[1][0]);
    }

    public function test_homepage_does_not_duplicate_headings_team_cards_or_reviews(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches);

        $counts = [];
        foreach ($matches as $match) {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($text === '') {
                continue;
            }
            $counts[$text] = ($counts[$text] ?? 0) + 1;
        }

        foreach ($counts as $text => $count) {
            $this->assertSame(1, $count, 'Homepage heading duplicated: '.$text);
        }

        $this->assertSame(1, $counts['Jonas Zanini'] ?? 0);
        $this->assertSame(1, $counts['Mayara Zanini'] ?? 0);

        $this->assertSame(1, substr_count($html, 'I highly recommend Jonas!'));
        $this->assertSame(1, substr_count($html, 'Jonas was an excellent professional'));
        $this->assertSame(1, substr_count($html, 'beautiful fence in our backyard'));

        $home = file_get_contents(resource_path('views/site/home.blade.php'));
        $this->assertIsString($home);
        $this->assertStringNotContainsString('owl-carousel', $home);
        $this->assertStringContainsString('snap-carousel', $home);
        $this->assertStringContainsString('snap-carousel__track', $home);

        $js = file_get_contents(public_path('js/custom-scripts.js'));
        $this->assertIsString($js);
        $this->assertStringContainsString('zr_init_snap_carousels', $js);
        $this->assertStringContainsString('scroll-snap', $js);
        $this->assertStringContainsString('skipOwl', $js);
    }

    public function test_home_json_ld_parses_with_required_business_fields_and_no_price_range(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, preg_match(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $html,
            $script
        ));

        $decoded = json_decode($script[1], true);
        $this->assertIsArray($decoded, 'JSON-LD must be valid JSON.');
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame('https://schema.org', $decoded['@context'] ?? null);

        $graph = $decoded['@graph'] ?? [];
        $this->assertIsArray($graph);

        $business = collect($graph)->first(
            fn ($node) => is_array($node) && ($node['@type'] ?? '') === 'HomeAndConstructionBusiness'
        );
        $this->assertIsArray($business);
        $this->assertSame('Zanini Renovations', $business['name'] ?? null);
        $this->assertNotSame('', (string) ($business['url'] ?? ''));
        $this->assertStringContainsString('/images/logo.png', (string) ($business['logo'] ?? ''));
        $this->assertSame('+1-647-834-7570', $business['telephone'] ?? null);
        $this->assertSame('zaninirenovations@gmail.com', $business['email'] ?? null);
        $this->assertSame('2020', $business['foundingDate'] ?? null);
        $this->assertSame('Person', $business['founder']['@type'] ?? null);
        $this->assertSame('Jonas Zanini', $business['founder']['name'] ?? null);
        $this->assertSame('Carleton Place', $business['address']['addressLocality'] ?? null);
        $this->assertSame('ON', $business['address']['addressRegion'] ?? null);
        $this->assertSame('CA', $business['address']['addressCountry'] ?? null);
        $this->assertArrayNotHasKey('streetAddress', $business['address']);
        $this->assertSame('Mo-Fr 09:00-18:00', $business['openingHours'] ?? null);
        $this->assertSame(self::JSONLD_KNOWS_ABOUT, $business['knowsAbout'] ?? null);

        $areaNames = array_map(
            static fn ($place) => is_array($place) ? (string) ($place['name'] ?? '') : (string) $place,
            $business['areaServed'] ?? []
        );
        $this->assertEqualsCanonicalizing(self::JSONLD_AREA_SERVED, $areaNames);

        $this->assertArrayNotHasKey('priceRange', $business);
        $this->assertStringNotContainsString('priceRange', $script[1]);
        $this->assertStringNotContainsString('licensed', strtolower($script[1]));
        $this->assertStringNotContainsString('$3m', strtolower($script[1]));

        $faq = collect($graph)->first(
            fn ($node) => is_array($node) && ($node['@type'] ?? '') === 'FAQPage'
        );
        $this->assertIsArray($faq);
        $this->assertNotEmpty($faq['mainEntity'] ?? []);

        $sameAs = $business['sameAs'] ?? [];
        $this->assertIsArray($sameAs);
        foreach ($sameAs as $url) {
            $this->assertMatchesRegularExpression('/^https?:\/\//', (string) $url);
            $this->assertStringNotContainsString('wa.me', (string) $url);
            $this->assertStringNotContainsString('whatsapp', strtolower((string) $url));
        }
        $this->assertContains((string) config('social.facebook_url'), $sameAs);

        $fromHelper = SeoStructuredData::homeGraph();
        $this->assertSame($decoded, json_decode(SeoStructuredData::toJsonLd($fromHelper), true));
    }

    public function test_portfolio_cards_omit_toronto_and_plumbing_fixtures_filter(): void
    {
        $plumbing = ProjectCategory::query()->firstOrCreate(
            ['slug' => 'plumbing-fixtures'],
            ['name' => 'Plumbing Fixtures', 'sort_order' => 90]
        );
        $plumbing->name = 'Plumbing Fixtures';
        $plumbing->save();

        $decks = ProjectCategory::query()->firstOrCreate(
            ['slug' => 'deck'],
            ['name' => 'Deck', 'sort_order' => 0]
        );

        $rows = [
            ['Small deck for shed', 'small-deck-for-shed'],
            ['Deck for Gazebo', 'deck-for-gazebo'],
            ['Flagstones patio renovation', 'flagstones-patio-renovation'],
        ];
        foreach ($rows as [$title, $slug]) {
            $project = Project::query()->create([
                'title' => $title,
                'slug' => $slug,
                'town' => 'Toronto',
                'description' => $title.' in Toronto.',
                'completed_on' => '2026-06-01',
            ]);
            $project->categories()->attach([$plumbing->id, $decks->id]);
        }

        $local = Project::query()->create([
            'title' => 'Floating deck with gravel around',
            'slug' => 'floating-deck-with-gravel-around',
            'town' => 'Kanata',
            'description' => 'Ground-level deck with steps.',
            'completed_on' => '2026-06-02',
        ]);
        $local->categories()->attach($decks->id);

        $home = $this->get('/')->assertOk()->getContent();
        $portfolio = $this->get('/portfolio')->assertOk()->getContent();

        foreach ([$home, $portfolio] as $html) {
            $this->assertStringContainsString('Small deck for shed', $html);
            $this->assertStringContainsString('Deck for Gazebo', $html);
            $this->assertStringContainsString('Flagstones patio renovation', $html);
            $this->assertStringContainsString('Kanata', $html);
            $this->assertStringNotContainsString('portfolio-card-town">Toronto', $html);
            $this->assertStringNotContainsString('Plumbing Fixtures', $html);
        }

        $this->assertStringContainsString('data-filter="deck"', $home);
        $this->assertStringNotContainsString('data-filter="plumbing-fixtures"', $home);
        $this->assertFalse($plumbing->isPublicFilter());
        $this->assertTrue($decks->isPublicFilter());
    }

    public function test_warranty_header_phone_uses_space_after_area_code(): void
    {
        $this->assertSame('(647) 834-7570', BusinessContact::normalizePhoneDisplay('(647)-834-7570'));
        $this->assertSame('(647) 834-7570', BusinessContact::phoneDisplay());

        $html = $this->get('/warranty')->assertOk()->getContent();
        $this->assertStringNotContainsString('(647)-834-7570', $html);
        $this->assertStringNotContainsString('(647)-834', $html);
        $this->assertStringContainsString('(647) 834-7570', $html);
        $this->assertStringNotContainsString('licensed', strtolower($html));
        $this->assertStringNotContainsString('wsib', strtolower($html));
        $this->assertStringNotContainsString('one-person', strtolower($html));
        $this->assertStringNotContainsString('$3m', strtolower($html));
    }
}
