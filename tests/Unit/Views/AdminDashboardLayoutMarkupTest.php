<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class AdminDashboardLayoutMarkupTest extends TestCase
{
    public function test_action_titles_use_font_awesome_icons_and_compact_rows(): void
    {
        $blade = file_get_contents(resource_path('views/admin/dashboard/index.blade.php'));
        $this->assertNotFalse($blade);

        foreach ([
            'What needs action' => 'fa-tachometer',
            'Outstanding receivables' => 'fa-credit-card',
            'Cash this month' => 'fa-usd',
            'Overdue' => 'fa-exclamation-circle',
            'Active projects' => 'fa-briefcase',
            'Do today' => 'fa-check-square-o',
            'Overdue invoices' => 'fa-file-text-o',
            'Attention' => 'fa-exclamation-triangle',
            'Top contract values' => 'fa-line-chart',
            'Pipeline / follow-up' => 'fa-filter',
            'Who to chase' => 'fa-phone',
            'Open quotations' => 'fa-file-text',
            'Upcoming (30 days)' => 'fa-calendar',
        ] as $title => $icon) {
            $this->assertMatchesRegularExpression(
                '/class="fa '.$icon.' dash-title-icon"[^>]*>[\s\S]{0,80}'.preg_quote($title, '/').'/',
                $blade,
                "Missing {$icon} next to {$title}"
            );
        }

        $this->assertStringContainsString('dash-stat-label__copy', $blade);
        $this->assertMatchesRegularExpression(
            '/dash-stat-label__copy">\s*Active projects\s*<x-admin.info-tip/',
            $blade
        );
        $this->assertStringContainsString('dash-compact-row', $blade);
        $this->assertStringContainsString('dash-compact-row__amt', $blade);
        $this->assertStringContainsString('dash-row-pill', $blade);
        $this->assertStringContainsString('dash-compact-row__client', $blade);
        $this->assertStringContainsString('Refresh data', $blade);
        $this->assertStringContainsString('Remind', $blade);
        $this->assertStringContainsString('ClientProject::formatMoney', $blade);
        $this->assertStringNotContainsString('dash-attention-list', $blade);
        $this->assertStringNotContainsString('dash-chase-list', $blade);
        preg_match_all('/<li class="dash-compact-row">[\s\S]*?<\/li>/', $blade, $compactRows);
        $this->assertNotEmpty($compactRows[0], 'Dashboard blade must include compact rows');
        foreach ($compactRows[0] as $rowHtml) {
            $this->assertStringNotContainsString('—', $rowHtml);
            $this->assertStringNotContainsString(' – ', $rowHtml);
        }
        $this->assertStringNotContainsString('licensed', $blade);
        $this->assertStringNotContainsString('WSIB', $blade);
        $this->assertStringNotContainsString('one-person', $blade);
        $this->assertStringNotContainsString('carpet', strtolower($blade));
    }

    public function test_dashboard_css_uses_primary_title_accent_and_compact_rows(): void
    {
        $css = file_get_contents(public_path('css/admin-dashboard.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.dash-toolbar-title\s*\{[^}]*color:\s*var\(--admin-heading\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.dash-block-title\s*\{[^}]*color:\s*var\(--dash-sky\)/s',
            $css
        );
        $this->assertStringContainsString('.dash-card--rose', $css);
        $this->assertStringContainsString('.dash-card--emerald', $css);
        $this->assertStringContainsString('.dash-section-title--indigo', $css);
        $this->assertMatchesRegularExpression(
            '/\.dash-grid--cards\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s',
            $css
        );
        $this->assertStringContainsString('repeat(4, minmax(0, 1fr))', $css);

        $this->assertMatchesRegularExpression(
            '/\.dash-card--stat \.dash-stat-label\s*\{[^}]*flex-wrap:\s*nowrap/s',
            $css
        );
        $this->assertStringContainsString('.dash-stat-label__copy', $css);
        $this->assertMatchesRegularExpression(
            '/\.dash-stat-label__copy\s*\{[^}]*flex:\s*0 1 auto/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.dash-stat-label__copy > \.admin-info-tip-wrap\s*\{[^}]*max-width:\s*12px/s',
            $css
        );
        $this->assertStringContainsString(
            '.admin-content .dash-card--stat .dash-stat-label .admin-info-tip-wrap > .admin-info-tip',
            $css
        );
        $this->assertStringContainsString('.dash-compact-row', $css);
        $this->assertStringContainsString('.dash-compact-row__amt', $css);
        $this->assertStringContainsString('margin-left: auto', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringNotContainsString('font-awesome-5', $css);
        $this->assertStringNotContainsString('fontawesome-free', $css);
    }
}
