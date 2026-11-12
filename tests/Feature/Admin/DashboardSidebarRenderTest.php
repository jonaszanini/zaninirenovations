<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class DashboardSidebarRenderTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_empty_dashboard_and_sidebar_render_and_every_nav_link_resolves(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard', ['refresh' => 1]));
        $response->assertOk();
        $html = $response->getContent();

        $response->assertSeeInOrder([
            'What needs action',
            'Outstanding receivables',
            'Cash this month',
            'Overdue',
            'Active projects',
            'Do today',
            'Overdue invoices',
            'Attention',
            'Pipeline / follow-up',
            'Who to chase',
            'Upcoming (30 days)',
            'Finance glance',
            'Quick actions',
            'Project status overview',
            'Recent activity',
        ]);
        $response->assertSee('CA$0.00');
        $response->assertSee('No overdue sent invoices with a due date.');
        $response->assertSee('No history entries yet.');
        $response->assertSee('No open visit or quote stage projects.');
        $response->assertSee('Refresh data');
        $response->assertDontSee('Avg project value');

        foreach ([
            'ZR',
            'Zanini Renovations',
            'Admin',
            'Dashboard',
            'Projects',
            'All Projects',
            'Project Calendar',
            'Latest Projects Photos',
            'Clients',
            'Tasks',
            'Blog',
            'Posts',
            'Categories',
            'Tags',
            'Comments',
            'Quotations',
            'Shared prices',
            'Materials',
            'Financials',
            'Invoices',
            'Incoming e-transfers',
            'Expenses',
            'Vehicles',
            'Communication',
            'Email/SMS Log',
            'Tools',
            'AI Instructions',
            'AI Image',
            'Utilities',
            'Suppliers',
            'Team',
            'Dark theme',
            'Log out',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Sidebar or dashboard missing {$label}");
        }

        $this->assertStringNotContainsString('licensed', strtolower($html));
        $this->assertStringNotContainsString('wsib', strtolower($html));
        $this->assertStringNotContainsString('one-person', strtolower($html));
        $this->assertStringNotContainsString('carpet', strtolower($html));

        preg_match('/<aside[^>]*id="admin-sidebar"[\s\S]*?<\/aside>/', $html, $aside);
        $this->assertNotEmpty($aside[0] ?? null, 'Sidebar markup missing');
        preg_match_all('/href="([^"]+)"/', $aside[0], $hrefs);
        $paths = [];
        foreach ($hrefs[1] as $href) {
            $decoded = html_entity_decode($href);
            $path = parse_url($decoded, PHP_URL_PATH) ?: $decoded;
            if ($path === '' || str_starts_with($path, '#')) {
                continue;
            }
            $paths[$path] = true;
        }

        $this->assertGreaterThanOrEqual(20, count($paths), 'Expected every sidebar destination to be linked');

        foreach (array_keys($paths) as $path) {
            $follow = $this->get($path);
            $status = $follow->getStatusCode();
            $this->assertTrue(
                $status >= 200 && $status < 400,
                "Sidebar link {$path} resolved with HTTP {$status}"
            );
        }
    }

    public function test_seeded_dashboard_renders_live_figures_inside_the_new_layout(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Seeded Chase Job',
            'client_name' => 'Casey Client',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'total_amount' => 2500,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $project->id,
            'invoice_number' => 'INV-SEED-009',
            'due_on' => now()->subDay()->toDateString(),
            'subtotal' => 100,
            'hst' => 13,
            'total' => 113,
        ]);

        $response = $this->get(route('admin.dashboard', ['refresh' => 1]));
        $response->assertOk();
        $response->assertSee('Seeded Chase Job');
        $response->assertSee('Casey Client');
        $response->assertSee('INV-SEED-009');
        $response->assertSee('CA$113.00');
        $response->assertSee('Remind');
        $response->assertSee('admin-sidebar-brand__mark', false);
        $response->assertSee('id="dash-finance-glance"', false);
        $response->assertDontSee('<details class="dash-panel dash-finance-glance" id="dash-finance-glance" open', false);
    }
}
