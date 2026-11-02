<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class ClientProjectsIndexViewTest extends TestCase
{
    public function test_index_blade_contains_status_note_under_project_name(): void
    {
        $blade = file_get_contents(resource_path('views/admin/client_projects/index.blade.php'));
        $this->assertNotFalse($blade);

        $this->assertStringContainsString('cp-table-status-note', $blade);
        $this->assertStringContainsString('$project->status_note', $blade);
    }

    public function test_index_blade_contains_mobile_actions_and_desktop_details(): void
    {
        $blade = file_get_contents(resource_path('views/admin/client_projects/index.blade.php'));
        $this->assertNotFalse($blade);

        $this->assertStringContainsString('cp-client-actions', $blade);
        $this->assertStringContainsString('cp-client-action-btn--phone', $blade);
        $this->assertStringContainsString('cp-client-action-btn--email', $blade);
        $this->assertStringContainsString('cp-client-action-btn--address', $blade);
        $this->assertStringContainsString('cp-client-action-btn--disabled', $blade);
        $this->assertStringContainsString('cp-copy-btn', $blade);
        $this->assertStringContainsString('cp-copy-toast', $blade);
        $this->assertStringNotContainsString('cp-client-desktop-details', $blade);
    }

    public function test_css_contains_mobile_actions_and_status_note_styles(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        $this->assertStringContainsString('.cp-table-status-note', $css);
        $this->assertStringContainsString('.cp-client-actions', $css);
        $this->assertStringContainsString('.cp-client-action-btn', $css);
        $this->assertStringContainsString('.cp-client-action-btn--disabled', $css);
        $this->assertStringContainsString('.cp-copy-toast', $css);
    }

    public function test_header_details_grid_uses_auto_fit_to_prevent_narrow_column_email_wrap(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.cp-header-details\s*\{[^}]*grid-template-columns:\s*repeat\(auto-fit,\s*minmax\(260px,\s*1fr\)\)/i',
            $css
        );
    }

    public function test_dark_css_contains_status_note_and_mobile_action_styles(): void
    {
        $darkCss = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($darkCss);

        $this->assertStringContainsString('html.admin-dark .cp-table-status-note', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-client-action-btn', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-client-action-btn--disabled', $darkCss);
        $this->assertStringContainsString('html.admin-dark a.cp-table-phone', $darkCss);
    }
}
