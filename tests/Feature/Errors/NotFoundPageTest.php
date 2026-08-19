<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    public function test_unknown_public_url_renders_branded_404_page(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('Page not found', false);
        $response->assertSee('We could not find that page.', false);
        $response->assertSee('Back to home', false);
        $response->assertSee('noindex, follow', false);
        $response->assertSee('text-404', false);
        $response->assertSee('page-header--404', false);
    }
}
