<?php

namespace Tests\Unit\Views;

use App\Models\ClientProject;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ClientProjectUtilitiesDarkThemeTest extends TestCase
{
    public function test_admin_client_projects_css_uses_tokens_for_utilities_item(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        // .cp-utilities-item should not have hardcoded #fafafa background
        $this->assertDoesNotMatchRegularExpression(
            '/\.cp-utilities-item\s*\{[^}]*background:\s*#fafafa/i',
            $css,
            'cp-utilities-item should not use hardcoded #fafafa light background'
        );

        // .cp-utilities-item should use var(--admin-border) and var(--admin-surface-hover)
        $this->assertMatchesRegularExpression(
            '/\.cp-utilities-item\s*\{[^}]*background:\s*var\(--admin-surface-hover\)/i',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-utilities-item\s*\{[^}]*border:\s*1px\s+solid\s+var\(--admin-border\)/i',
            $css
        );
    }

    public function test_admin_dark_css_contains_utilities_dark_theme_styles(): void
    {
        $darkCss = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($darkCss);

        $this->assertStringContainsString('html.admin-dark .cp-utilities-item', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-utilities-section__count', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-utilities-section__head h3 .fa', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-utilities-item__room-area', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-utilities-item__room-photos', $darkCss);
        $this->assertStringContainsString('html.admin-dark .cp-utilities-item__rooms li', $darkCss);
    }

    public function test_utilities_blade_renders_room_area_items(): void
    {
        $project = new ClientProject();
        $project->id = 999;
        $project->name = 'Test Project';

        $linkedUtilities = [
            'has_any' => true,
            'total_count' => 1,
            'room_area' => [
                [
                    'id' => 1,
                    'name' => '',
                    'room_count' => 2,
                    'total_sqft' => 454.05,
                    'total_sqft_label' => '454.05 sq ft',
                    'updated_label' => 'Sep 6, 2026',
                    'open_url' => '/admin/utilities/room-area?project=1',
                    'rooms' => [
                        ['name' => 'Main', 'area_label' => '338.3 sq ft', 'photo_count' => 3],
                        ['name' => 'Small office', 'area_label' => '115.75 sq ft'],
                    ],
                ],
            ],
            'measurement' => [],
            'lumber' => [],
            'pillar' => [],
            'tool_links' => [
                'room_area' => '/admin/utilities/room-area',
                'measurement' => '/admin/utilities/measurement-conversion',
                'lumber' => '/admin/utilities/lumber-board?project=999',
                'pillar' => '/admin/utilities/pillar-spindle',
                'index' => '/admin/utilities',
            ],
        ];

        $html = View::make('admin.client_projects._utilities', [
            'project' => $project,
            'linkedUtilities' => $linkedUtilities,
        ])->render();

        $this->assertStringContainsString('cp-utilities-section', $html);
        $this->assertStringContainsString('cp-utilities-item', $html);
        $this->assertStringContainsString('Main', $html);
        $this->assertStringContainsString('338.3 sq ft', $html);
        $this->assertStringContainsString('3 photos', $html);
        $this->assertStringContainsString('Small office', $html);
        $this->assertStringContainsString('115.75 sq ft', $html);
        $this->assertStringContainsString('454.05 sq ft total', $html);
        $this->assertStringContainsString('Open Room Area tool', $html);
    }
}
