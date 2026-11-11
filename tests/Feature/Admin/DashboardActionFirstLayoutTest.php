<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\FenceQuotation;
use App\Services\AdminDashboardService;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class DashboardActionFirstLayoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_dashboard_uses_action_first_structure(): void
    {
        $this->actingAsAdmin();

        $overdueProject = ClientProject::factory()->create([
            'name' => 'Overdue Kitchen Reno',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'total_amount' => 8000,
            'end_date' => now()->subDays(3)->toDateString(),
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $overdueProject->id,
            'invoice_number' => 'INV-OVERDUE-001',
            'due_on' => now()->subDays(5)->toDateString(),
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
        ]);

        ClientProject::factory()->create([
            'name' => 'Chase Deck Quote',
            'client_name' => 'Pat Followup',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'total_amount' => 4000,
        ]);

        ClientProject::factory()->create([
            'name' => 'Soon Start Patio',
            'status' => ClientProject::STATUS_SCHEDULED,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
        ]);

        FenceQuotation::query()->create([
            'quote_number' => 'FQ-CHASE-1',
            'client_name' => 'Fence Lead',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => []],
            'grand_total' => 3210.50,
        ]);

        $response = $this->get(route('admin.dashboard', ['refresh' => 1]));

        $response->assertOk();
        $response->assertSeeInOrder([
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
            'Profit projection — next 6 months',
            'Gross margin (YTD)',
            'Quick actions',
            'Project status overview',
            'Recent activity',
        ]);
        $response->assertDontSee('Avg project value');
        $response->assertSee('INV-OVERDUE-001');
        $response->assertSee('Remind');
        $response->assertSee('CA$1,130.00');
        $response->assertSee('Overdue Kitchen Reno');
        $response->assertSee('Chase Deck Quote');
        $response->assertSee('Pat Followup');
        $response->assertSee('Soon Start Patio');
        $response->assertSee('FQ-CHASE-1');
        $response->assertSee('Fence Lead');
        $response->assertSee('id="dash-finance-glance"', false);
        $response->assertSee('id="dash-chart-projection"', false);
        $response->assertSee('id="dash-chart-ytd"', false);
        $response->assertDontSee('<details class="dash-panel dash-finance-glance" id="dash-finance-glance" open', false);
        $response->assertSee('data-dash-dismiss-alert', false);
        $html = $response->getContent();
        $this->assertStringContainsString('fa-tachometer dash-title-icon', $html);
        $this->assertStringContainsString('fa-credit-card dash-title-icon', $html);
        $this->assertStringContainsString('fa-usd dash-title-icon', $html);
        $this->assertStringContainsString('fa-exclamation-circle dash-title-icon', $html);
        $this->assertStringContainsString('fa-briefcase dash-title-icon', $html);
        $this->assertStringContainsString('fa-check-square-o dash-title-icon', $html);
        $this->assertStringContainsString('fa-file-text-o dash-title-icon', $html);
        $this->assertStringContainsString('fa-exclamation-triangle dash-title-icon', $html);
        $this->assertStringContainsString('fa-line-chart dash-title-icon', $html);
        $this->assertStringContainsString('fa-filter dash-title-icon', $html);
        $this->assertStringContainsString('fa-phone dash-title-icon', $html);
        $this->assertStringContainsString('fa-file-text dash-title-icon', $html);
        $this->assertStringContainsString('fa-calendar dash-title-icon', $html);
        $this->assertStringContainsString('dash-stat-label__copy', $html);
        $this->assertStringContainsString('dash-compact-row', $html);
        $this->assertStringContainsString('dash-row-pill', $html);
        $this->assertStringContainsString('dash-compact-row__client', $html);
        $this->assertStringContainsString('Quote Sent', $html);
        $this->assertStringNotContainsString('dash-attention-meta">—', $html);
        preg_match_all('/<li class="dash-compact-row">[\s\S]*?<\/li>/', $html, $compactRows);
        $this->assertNotEmpty($compactRows[0], 'Rendered dashboard must include compact rows');
        foreach ($compactRows[0] as $rowHtml) {
            $this->assertStringNotContainsString('—', $rowHtml);
            $this->assertStringNotContainsString(' – ', $rowHtml);
        }
        $this->assertStringContainsString('Refresh data', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*refresh=1[^"]*"/', $html);
    }

    public function test_dashboard_payload_includes_overdue_total_and_chase_rows(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Late Invoice Job',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        ClientProjectInvoice::factory()->sent()->create([
            'client_project_id' => $project->id,
            'due_on' => now()->subDays(2)->toDateString(),
            'total' => 2260.00,
        ]);
        ClientProject::factory()->create([
            'name' => 'Open Quote Job',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $payload = app(AdminDashboardService::class)->build(true);

        $this->assertSame(1, $payload['cards']['overdue_invoice_count']);
        $this->assertEquals(2260.00, $payload['cards']['overdue_invoice_total']);
        $this->assertArrayHasKey('average_project_value', $payload['cards']);
        $this->assertArrayHasKey('gross_margin_ytd_pct', $payload['cards']);
        $this->assertNotEmpty($payload['pipeline']['open_quotation_rows']);
        $this->assertSame('Open Quote Job', $payload['pipeline']['open_quotation_rows'][0]['name']);
    }
}
