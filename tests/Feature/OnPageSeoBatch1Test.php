<?php

namespace Tests\Feature;

use App\Support\LlmsTxtGenerator;
use App\Support\SeoStructuredData;
use Tests\TestCase;

/**
 * On-page SEO batch 1: remove unconfirmed claims and geo coordinates,
 * fix redirects, and prevent OG/Twitter double-encoding.
 */
class OnPageSeoBatch1Test extends TestCase
{
    /**
     * Homepage must not contain "licensed" anywhere.
     */
    public function test_homepage_does_not_claim_licensed(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = strtolower($response->getContent());
        $this->assertStringNotContainsString('licensed', $content);
    }

    /**
     * Homepage must not expose exact map coordinates.
     */
    public function test_homepage_does_not_expose_coordinates(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('45.120446', $content);
        $this->assertStringNotContainsString('-76.0081985', $content);
        $this->assertStringNotContainsString('76.0081985', $content);
    }

    /**
     * OG/Twitter meta tags should not double-encode ampersands.
     */
    public function test_og_tags_do_not_double_encode_ampersands(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('&amp;amp;', $content);
        $this->assertStringContainsString('og:title', $content);
    }

    /**
     * /public/ prefix should 301 redirect to the clean URL.
     */
    public function test_public_prefix_redirects_to_clean_url(): void
    {
        $response = $this->get('/public/');

        $response->assertRedirect('/');
        $response->assertStatus(301);
    }

    /**
     * /public/warranty should 301 redirect to /warranty.
     */
    public function test_public_warranty_redirects_to_warranty(): void
    {
        $response = $this->get('/public/warranty');

        $response->assertRedirect('/warranty');
        $response->assertStatus(301);
    }

    /**
     * /index.php should 301 redirect to /.
     */
    public function test_index_php_redirects_to_root(): void
    {
        $response = $this->get('/index.php');

        $response->assertRedirect('/');
        $response->assertStatus(301);
    }

    /**
     * llms.txt must not contain "licensed" or exact coordinates.
     */
    public function test_llms_txt_no_licensed_or_coordinates(): void
    {
        $txt = LlmsTxtGenerator::generate(collect(), collect());

        $this->assertStringNotContainsString('licensed', strtolower($txt));
        $this->assertStringNotContainsString('Licensed', $txt);
        $this->assertStringNotContainsString('45.120446', $txt);
        $this->assertStringNotContainsString('-76.0081985', $txt);
        $this->assertStringContainsString('insured', strtolower($txt));
    }

    /**
     * JSON-LD structured data must not contain "licensed" or geo coordinates.
     */
    public function test_json_ld_no_licensed_or_geo(): void
    {
        $graph = SeoStructuredData::homeGraph();
        $encoded = SeoStructuredData::toJsonLd($graph);

        $this->assertStringNotContainsString('"licensed"', strtolower($encoded));
        $this->assertStringNotContainsString('45.120446', $encoded);
        $this->assertStringNotContainsString('-76.0081985', $encoded);
        $this->assertStringNotContainsString('"geo"', $encoded);
    }

    /**
     * JSON-LD address must have no streetAddress or postalCode (service-area business).
     */
    public function test_json_ld_address_has_no_street_or_postal(): void
    {
        $graph = SeoStructuredData::homeGraph();
        $business = collect($graph['@graph'])->first(
            fn ($node) => ($node['@type'] ?? '') === 'HomeAndConstructionBusiness'
        );

        $this->assertNotNull($business);
        $this->assertArrayHasKey('address', $business);
        $this->assertArrayNotHasKey('streetAddress', $business['address']);
        $this->assertArrayNotHasKey('postalCode', $business['address']);
        $this->assertSame('Carleton Place', $business['address']['addressLocality']);
        $this->assertSame('ON', $business['address']['addressRegion']);
        $this->assertSame('CA', $business['address']['addressCountry']);
    }

    /**
     * Phone format must be "(647) 834-7570", not "(647)-834-7570".
     */
    public function test_phone_format_is_correct(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('(647)-834', $content);
        $this->assertStringContainsString('(647) 834-7570', $content);

        $warranty = $this->get('/warranty');
        $warranty->assertStatus(200);
        $warrantyHtml = $warranty->getContent();
        $this->assertStringNotContainsString('(647)-834', $warrantyHtml);
        $this->assertStringContainsString('(647) 834-7570', $warrantyHtml);
    }

    /**
     * llms.txt summary must not say "one-person".
     */
    public function test_llms_txt_does_not_say_one_person(): void
    {
        $txt = strtolower(LlmsTxtGenerator::generate(collect(), collect()));

        $this->assertStringNotContainsString('one-person', $txt);
        $this->assertStringNotContainsString('one person', $txt);
        $this->assertStringNotContainsString('wsib', $txt);
    }

    /**
     * Homepage title should be the shortened version (60 chars max).
     */
    public function test_homepage_title_is_shortened(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('<title>Carpenter &amp; Renovation Contractor in Carleton Place | Zanini</title>', false);
    }

    /**
     * Confirmed terms (Kitchen Remodel, commercial, Gatineau) must still be present.
     */
    public function test_confirmed_terms_are_present(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Kitchen Remodel', $content);
        $this->assertStringContainsString('commercial', $content);
        $this->assertStringContainsString('Gatineau', $content);
    }

    /**
     * Even if env sets a long /maps/place/ URL, output must use the CID URL.
     */
    public function test_long_maps_url_is_replaced_with_cid(): void
    {
        $longUrl = 'https://www.google.com/maps/place/Zanini+Renovations/@45.0145689,-76.3839057,10z/data=!4m6';
        $cidUrl = 'https://maps.google.com/?cid=7676669856408637146';

        config(['seo.google_business_url' => $longUrl]);

        $this->assertSame($cidUrl, SeoStructuredData::googleBusinessUrl());

        $graph = SeoStructuredData::homeGraph();
        $encoded = SeoStructuredData::toJsonLd($graph);
        $this->assertStringNotContainsString('/maps/place/', $encoded);
        $this->assertStringContainsString($cidUrl, $encoded);

        $txt = LlmsTxtGenerator::generate(collect(), collect());
        $this->assertStringNotContainsString('/maps/place/', $txt);
        $this->assertStringContainsString($cidUrl, $txt);
    }

    /**
     * Homepage must not contain /maps/place/ URLs.
     */
    public function test_homepage_has_no_long_maps_url(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('/maps/place/', $content);
        $this->assertStringContainsString('maps.google.com/?cid=', $content);
    }
}
