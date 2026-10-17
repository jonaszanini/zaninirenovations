<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\GoogleReviews;
use App\Support\PublicServices;
use App\Support\SeoStructuredData;
use Tests\IntegrationTestCase;

class OnPageSeoBatch3Test extends IntegrationTestCase
{
    /** @var list<string> */
    private const EDITOR_NOTES = [
        'j7',
        'jonas confirmed',
        '[jonas]',
        '[placeholder',
        'final copy for approval',
        'editor notes',
        'page settings',
    ];

    public function test_service_pages_about_reviews_and_contact_return_200_with_seo_tags(): void
    {
        $this->seedPlanProjects();

        $pages = [
            '/services' => (string) config('public_services.hub.title'),
            '/about' => (string) config('public_services.about.title'),
            '/reviews' => (string) config('public_services.reviews.title'),
            '/contact' => (string) config('public_services.contact.title'),
        ];
        foreach (PublicServices::publishedPages() as $page) {
            $pages['/services/'.$page['slug']] = (string) $page['title'];
        }

        foreach ($pages as $path => $title) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame($title, $this->documentTitle($html), $path);
            $this->assertNotSame('', $this->metaDescription($html), $path);
            $this->assertLessThanOrEqual(155, mb_strlen($this->metaDescription($html)), $path);
            $this->assertLessThanOrEqual(60, mb_strlen($this->documentTitle($html)), $path);
            $this->assertSame(url($path), $this->canonicalHref($html), $path);
            $this->assertStringNotContainsString('data-sticky_header', $html, $path);
            $this->assertStringContainsString('sa-btn', $html, $path);
        }

        $this->assertCount(11, PublicServices::publishedPages());
    }

    public function test_service_pages_include_exact_h1_faqs_and_json_ld(): void
    {
        $this->seedPlanProjects();

        foreach (PublicServices::publishedPages() as $page) {
            $path = '/services/'.$page['slug'];
            $html = $this->get($path)->assertOk()->getContent();
            $visible = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertStringContainsString('<h1>'.e($page['h1']).'</h1>', $html, $path);
            $this->assertStringContainsString('Service', $html);
            $this->assertStringContainsString('FAQPage', $html);
            $this->assertStringContainsString('BreadcrumbList', $html);
            $this->assertStringContainsString((string) $page['service_type'], $html);

            foreach ((array) $page['faqs'] as $faq) {
                $this->assertStringContainsString($faq['question'], $visible, $path);
                $this->assertStringContainsString($faq['answer'], $visible, $path);
            }

            $graph = SeoStructuredData::servicePage($page);
            $faqNode = collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'FAQPage');
            $this->assertNotNull($faqNode, $page['slug'].' missing FAQPage');
            $this->assertCount(count($page['faqs']), $faqNode['mainEntity']);
            foreach ($page['faqs'] as $i => $faq) {
                $this->assertSame($faq['question'], $faqNode['mainEntity'][$i]['name']);
                $this->assertSame($faq['answer'], $faqNode['mainEntity'][$i]['acceptedAnswer']['text']);
            }

            foreach ((array) $page['projects'] as $project) {
                $this->assertStringContainsString('/portfolio/'.$project['slug'], $html, $path.' missing '.$project['slug']);
            }

            if ($page['slug'] === 'decks') {
                $this->assertStringContainsString('</a>: a 290 sq ft', $html);
                $this->assertStringNotContainsString('</a> :', $html);
            }
            if (! empty($page['reviews'])) {
                $this->assertStringContainsString('pp-quote-cite', $html, $path);
            }

            $this->assertStringContainsString('Fully insured', $html, $path);
            $this->assertStringContainsString('/contact', $html, $path);
        }
    }

    public function test_kitchen_legacy_url_redirects_and_hub_lists_nine_services(): void
    {
        $this->get('/services/custom-kitchen-remodel')
            ->assertStatus(301)
            ->assertRedirect('/services/kitchen-remodel');

        $hub = $this->get('/services')->assertOk()->getContent();
        foreach (PublicServices::publishedPages() as $page) {
            $this->assertStringContainsString('/services/'.$page['slug'], $hub);
        }

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('/services/kitchen-remodel', $home);
        $this->assertStringContainsString('Kitchen Remodel', $home);
        $this->assertStringContainsString('hasOfferCatalog', $home);
    }

    public function test_sitemap_lists_new_public_pages(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['/about', '/reviews', '/contact', '/services', '/services/decks', '/services/kitchen-remodel'] as $path) {
            $this->assertStringContainsString($path.'</loc>', $xml, $path);
        }
    }

    public function test_about_reviews_and_contact_copy_and_schema(): void
    {
        $about = $this->get('/about')->assertOk()->getContent();
        $this->assertStringContainsString('About Jonas Zanini', $about);
        $this->assertStringContainsString('Mayara Zanini, Office &amp; Marketing', $about);
        $this->assertStringContainsString('AboutPage', $about);
        $this->assertStringContainsString('pp-quote-cite', $about);
        $this->assertStringContainsString('#person-jonas', $about);
        $this->assertStringContainsString('Ontario Building Code', $about);
        $this->assertStringContainsString('fully insured', strtolower($about));

        $reviews = $this->get('/reviews')->assertOk()->getContent();
        $this->assertStringContainsString('Client Reviews', $reviews);
        $this->assertStringContainsString('5.0 on Google from 26 reviews', $reviews);
        $this->assertStringContainsString('enquires', $reviews);
        $this->assertStringContainsString('dry walling', $reviews);
        $this->assertStringContainsString('AggregateRating', $reviews);
        $this->assertStringContainsString('reviewCount', $reviews);
        $this->assertStringContainsString('Beatriz C.', $reviews);
        $this->assertStringContainsString('pp-quote-cite', $reviews);
        $this->assertStringContainsString('pp-quote-grid', $reviews);
        $this->assertStringContainsString('Leave a Google review', $reviews);
        $this->assertStringNotContainsString('Houzz', $reviews);

        $graph = SeoStructuredData::reviewsPage((array) config('public_services.reviews'));
        $pageNode = collect($graph['@graph'])->first(fn ($node) => ($node['@type'] ?? '') === 'WebPage');
        $this->assertSame('5.0', $pageNode['mainEntity']['aggregateRating']['ratingValue']);
        $this->assertSame('26', $pageNode['mainEntity']['aggregateRating']['reviewCount']);
        $this->assertCount(count(GoogleReviews::textualReviews()), $pageNode['mainEntity']['review']);

        $contact = $this->get('/contact')->assertOk()->getContent();
        $this->assertStringContainsString('Get a Free Estimate', $contact);
        $this->assertStringContainsString('ContactPage', $contact);
        $this->assertStringContainsString('sms:+16478347570', $contact);
        $this->assertStringContainsString('id="contact-form"', $contact);
        $this->assertStringContainsString('Open in Google Maps', $contact);
        $this->assertStringContainsString('7676669856408637146', $contact);
        $this->assertStringNotContainsString('output=embed', $contact);
        $this->assertStringNotContainsString('<iframe', $contact);
        $this->assertStringNotContainsString('Orléans', $contact);
        $this->assertStringNotContainsString('Orleans', $contact);
    }

    public function test_editor_notes_and_banned_claims_are_absent_from_batch3_pages(): void
    {
        $this->seedPlanProjects();

        $paths = ['/services', '/about', '/reviews', '/contact'];
        foreach (PublicServices::publishedPages() as $page) {
            $paths[] = '/services/'.$page['slug'];
        }

        foreach ($paths as $path) {
            $html = strtolower($this->get($path)->assertOk()->getContent());
            $visible = strtolower(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            foreach (self::EDITOR_NOTES as $phrase) {
                $this->assertStringNotContainsString($phrase, $visible, $path.' still has “'.$phrase.'”');
            }
            $this->assertStringNotContainsString('licensed', $html, $path);
            $this->assertStringNotContainsString('licence', $html, $path);
            $this->assertStringNotContainsString('wsib', $html, $path);
            $this->assertStringNotContainsString('one-person', $html, $path);
            $this->assertStringNotContainsString('one person', $html, $path);
            $this->assertStringNotContainsString('solo-operator', $html, $path);
        }
    }

    public function test_unpublished_service_slug_is_404(): void
    {
        $this->get('/services/not-a-real-service')->assertNotFound();
    }

    private function seedPlanProjects(): void
    {
        $rows = [
            ['floating-deck-with-gravel-around', 'Floating deck with gravel around', 'Kanata'],
            ['deck-stairs-landing-carleton-place', 'Deck stairs and landing', 'Carleton Place'],
            ['outside-stairs', 'Outside stairs', 'Carleton Place'],
            ['catio', 'Catio', 'South Keys'],
            ['side-yard-fence-gates-carleton-place', 'Side yard fence and gates', 'Carleton Place'],
            ['three-fences-at-once', 'Three fences at once', 'Carleton Place'],
            ['fence-4', 'Backyard privacy fence with gate', 'Carleton Place'],
            ['backyard-privacy-fence-carleton-place', 'Backyard privacy fence', 'Carleton Place'],
            ['basement-finishing', 'Basement finishing', 'Barrhaven'],
            ['basement-flood-restoration', 'Basement flood restoration', 'Carleton Place'],
            ['basement-closet-doors-gatineau', 'Basement closet doors', 'Gatineau, QC'],
            ['custom-classic-fireplace-mantel', 'Custom classic fireplace mantel', 'Perth'],
            ['fireplace-surround-niche-carleton-place', 'Fireplace surround with niche', 'Carleton Place'],
            ['fireplace-renovation', 'Fireplace renovation', 'Kanata'],
            ['built-in-shelving-wall-kanata', 'Built-in shelving wall', 'Kanata'],
            ['built-in-window-storage', 'Built-in window storage', 'Barrhaven'],
            ['custom-bathroom-vanity-barrhaven', 'Custom bathroom vanity', 'Barrhaven'],
            ['2nd-floor-and-stairs-renovation', '2nd floor and stairs renovation', 'South Keys'],
            ['engineering-hard-wood-flooring-installation', 'Engineered hardwood flooring', 'Ashton'],
            ['kitchen-renovations', 'Kitchen renovations', 'Nepean'],
            ['backsplash-installation', 'Backsplash installation', 'Kanata'],
            ['lattice-fence', 'Lattice fence', 'Alta Vista'],
            ['fence-3', 'Backyard fence and gate', 'Stittsville'],
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
