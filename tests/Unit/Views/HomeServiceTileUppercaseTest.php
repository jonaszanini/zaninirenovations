<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class HomeServiceTileUppercaseTest extends TestCase
{
    public function test_home_service_tile_titles_use_scoped_uppercase_css(): void
    {
        $css = file_get_contents(public_path('css/styles.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.section-core-services\s+\.service-block-2\s+h3\s*\{[^}]*text-transform:\s*uppercase/s',
            $css,
            'Home service tile titles must be uppercase via a .section-core-services scoped rule.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/prefers-color-scheme\s*:\s*dark/i',
            $css
        );
    }

    public function test_shared_service_block_title_rule_is_not_unconditionally_uppercase(): void
    {
        $css = file_get_contents(public_path('css/styles.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/(?<!section-core-services )\.service-block-2 h3,\s*\n\.service-block-2 h3 a \{[^}]*\}/s',
            $css
        );

        if (preg_match(
            '/(?<!section-core-services )\.service-block-2 h3,\s*\n\.service-block-2 h3 a \{([^}]*)\}/s',
            $css,
            $matches
        ) === 1) {
            $this->assertDoesNotMatchRegularExpression(
                '/text-transform:\s*uppercase/i',
                $matches[1],
                'Do not uppercase .service-block-2 titles outside the home services grid.'
            );
        }
    }

    public function test_home_blade_keeps_title_case_tile_copy(): void
    {
        $blade = file_get_contents(resource_path('views/site/home.blade.php'));
        $this->assertNotFalse($blade);

        $this->assertStringContainsString('section-core-services', $blade);
        $this->assertStringContainsString('<h3>Basement Renovation</h3>', $blade);
        $this->assertStringContainsString('<h3>Kitchen Remodel</h3>', $blade);
        $this->assertStringContainsString('<h3>Trim &amp; Moulding</h3>', $blade);
        $this->assertStringNotContainsString('<h3>BASEMENT RENOVATION</h3>', $blade);
        $this->assertStringNotContainsString('<h3>KITCHEN REMODEL</h3>', $blade);
    }
}
