<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class PublicMobileNavDropdownTest extends TestCase
{
    public function test_custom_scripts_skip_dropdown_toggles_when_closing_mobile_nav(): void
    {
        $js = file_get_contents(public_path('js/custom-scripts.js'));
        $this->assertNotFalse($js);

        $this->assertStringContainsString('zr_is_nav_dropdown_toggle', $js);
        $this->assertMatchesRegularExpression(
            '/\$link\.is\(["\']\.dropdown-toggle["\']\)/',
            $js
        );
        $this->assertMatchesRegularExpression(
            '/\$link\.is\(["\']\[data-toggle=[\'"]dropdown[\'"]\]["\']\)/',
            $js
        );
        $this->assertMatchesRegularExpression(
            '/\$\(["\']\.navbar-collapse a["\']\)\.on\(["\']click["\']/',
            $js
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\$\(document\)\.on\(["\']click["\'],\s*["\']a\.nav_menu["\']/',
            $js
        );
    }

    public function test_public_header_does_not_close_menu_on_every_nav_menu_click(): void
    {
        $header = file_get_contents(resource_path('views/site/partials/public-header.blade.php'));
        $this->assertNotFalse($header);

        $this->assertStringContainsString('dropdown-toggle nav_menu', $header);
        $this->assertStringContainsString("data-toggle=\"dropdown\"", $header);
        $this->assertDoesNotMatchRegularExpression(
            '/\$\([\'"]\.main-header-2 a\.nav_menu[\'"]\)\.on\([\'"]click[\'"]/',
            $header
        );
    }

    public function test_mobile_services_dropdown_menu_is_readable_on_the_light_navbar(): void
    {
        $css = file_get_contents(public_path('css/styles.css'));
        $this->assertNotFalse($css);

        $this->assertStringContainsString(
            '.main-header-2 .navbar-default .navbar-nav .dropdown-menu',
            $css
        );
        $this->assertStringContainsString(
            '.main-header-2 .navbar-default .navbar-nav .dropdown-menu > li > a',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/prefers-color-scheme\s*:\s*dark/i',
            $css
        );
    }

    public function test_contact_form_uses_custom_validation_instead_of_native_bubbles(): void
    {
        $js = file_get_contents(public_path('js/custom-scripts.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('novalidate', $js);
        $this->assertMatchesRegularExpression(
            '/\$contact_form\.on\(["\']submit["\']/',
            $js
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\$contact_form\.find\(["\']button\[type=submit\]["\']\)\.on\(["\']click["\']/',
            $js
        );
        $this->assertStringContainsString('contactSubmitting', $js);
        $this->assertStringContainsString('safety_key', $js);
        $this->assertStringContainsString('sms_opt_in', $js);
    }

    public function test_mobile_public_css_clips_owl_and_enlarges_tap_targets(): void
    {
        $css = file_get_contents(public_path('css/styles.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.owl-carousel .owl-stage-outer', $css);
        $this->assertStringContainsString('.footer-social-icons a', $css);
        $this->assertStringContainsString('.footer-widget-area .footer-useful-links li a', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('min-height: 44px', $css);
        $this->assertStringContainsString('.project-gallery-modal.modal', $css);
        $this->assertStringContainsString('background-color: #fff', $css);
        $this->assertStringContainsString('#contact-form span.error', $css);
        $this->assertStringContainsString('#c0392b', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.about-footer a,\s*\n\s*\.copyright-text a/',
            $css
        );
    }
}
