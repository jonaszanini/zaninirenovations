<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminDarkThemeCoverageTest extends TestCase
{
    public function test_stat_tile_tokens_exist_in_light_and_dark_css(): void
    {
        $light = file_get_contents(public_path('css/admin.css'));
        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($light);
        $this->assertNotFalse($dark);

        foreach ([
            '--admin-stat-tile-bg',
            '--admin-stat-tile-border',
            '--admin-stat-highlight-bg',
            '--admin-stat-highlight-border',
            '--admin-info-tile-bg',
            '--admin-info-tile-border',
            '--admin-warn-tile-bg',
            '--admin-warn-tile-border',
            '--admin-warn-tile-text',
        ] as $token) {
            $this->assertStringContainsString($token, $light, "Light tokens missing {$token}");
            $this->assertStringContainsString($token, $dark, "Dark tokens missing {$token}");
        }
    }

    public function test_hourly_summary_tiles_use_shared_tokens(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        $this->assertDoesNotMatchRegularExpression(
            '/\.cp-hourly-summary-tile\s*\{[^}]*background:\s*#f9fafb/i',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-hourly-summary-tile\s*\{[^}]*background:\s*var\(--admin-stat-tile-bg\)/i',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-hourly-summary-value\s*\{[^}]*color:\s*var\(--admin-heading\)/i',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-hourly-summary-tile--highlight\s*\{[^}]*background:\s*var\(--admin-stat-highlight-bg\)/i',
            $css
        );
    }

    public function test_admin_dark_css_covers_remaining_surfaces(): void
    {
        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($dark);

        foreach ([
            'html.admin-dark .cp-hourly-summary-tile',
            'html.admin-dark .cp-profit-tile--highlight',
            'html.admin-dark .cp-invoice-bill-to',
            'html.admin-dark .admin-btn--primary:disabled',
            'html.admin-dark .asp__panel',
            'html.admin-dark .q-commute-metric',
            'html.admin-dark .pt-step-card',
            'html.admin-dark .util-ps-diagram',
            'html.admin-dark .fq-badge',
            'html.admin-dark .pq-hint',
            'html.admin-dark .pt-readiness',
            'html.admin-dark .gf-total-tile__value--success',
            'html.admin-dark .admin-btn--success',
            'html.admin-dark .et-chip',
            'html.admin-dark .et-card',
            'html.admin-dark .et-paste-advanced',
            'html.admin-dark .et-paste-advanced > summary',
            'html.admin-dark .dash-etransfer-today',
            'html.admin-dark .util-lb-offcut-used',
            'html.admin-dark .util-lb-sheet-group',
            'html.admin-dark .util-oc-pill',
            'html.admin-dark .util-oc-card',
            'html.admin-dark .util-ra-full-header',
            'html.admin-dark .util-ra-full-header__name',
            'html.admin-dark .util-ra--fullpage .util-ra-side',
            'html.admin-dark .util-ra-sheet-handle__bar',
            'html.admin-dark .util-ra--fullpage .util-ra-canvas-card',
            'html.admin-dark .util-ra-help-pop',
            'html.admin-dark .util-ra-first-tip',
            'html.admin-dark .util-ra-guide',
            'html.admin-dark .util-ra-guide__body',
            'html.admin-dark .util-ra-guide__samples',
            'html.admin-dark .admin-filters-accordion .bp-list-filters',
        ] as $selector) {
            $this->assertStringContainsString($selector, $dark, "Missing dark rule {$selector}");
        }
    }

    public function test_dark_mode_audit_script_is_in_the_repo(): void
    {
        $this->assertFileExists(base_path('dark-mode-audit.js'));
        $this->assertFileExists(base_path('screenshot-all.js'));
        $this->assertFileExists(base_path('docs/DARK_MODE_AUDIT.md'));
    }
}
