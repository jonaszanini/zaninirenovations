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
}
