<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectIndexListTotalTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_sent_quotation_total_shows_until_one_is_accepted(): void
    {
        $this->actingAsAdmin();

        $pending = ClientProject::factory()->create([
            'name' => 'Pending Sent Bath',
            'client_name' => 'Pending Client',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
            'total_amount' => 0,
        ]);
        $older = $this->quotation($pending, 400, ClientProjectQuotation::STATUS_SENT, now()->subDay());
        $latest = $this->quotation($pending, 1840.50, ClientProjectQuotation::STATUS_SENT, now());

        $acceptedProject = ClientProject::factory()->create([
            'name' => 'Accepted Contract Bath',
            'client_name' => 'Accepted Client',
            'status' => ClientProject::STATUS_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
            'total_amount' => 2500,
        ]);
        $this->quotation($acceptedProject, 2500, ClientProjectQuotation::STATUS_ACCEPTED, now()->subDays(3));
        $this->quotation($acceptedProject, 9999, ClientProjectQuotation::STATUS_SENT, now());

        $empty = ClientProject::factory()->create([
            'name' => 'No Quote Yet Bath',
            'client_name' => 'Empty Client',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
            'total_amount' => 0,
        ]);

        $older->refresh();
        $latest->refresh();

        $html = $this->get(route('admin.client-projects.index'))->assertOk()->getContent();

        $pendingRow = $this->rowHtml($html, 'Pending Sent Bath');
        $this->assertStringContainsString(ClientProject::formatMoney((float) $latest->total_amount), $pendingRow);
        $this->assertStringNotContainsString(ClientProject::formatMoney((float) $older->total_amount), $pendingRow);
        $this->assertStringContainsString('Pending approval', $pendingRow);
        $this->assertStringContainsString('cp-list-total__note', $pendingRow);
        $this->assertStringContainsString('admin-project-list-rows.js', $html);
        $this->assertStringContainsString('class="cp-row-hit"', $pendingRow);
        $this->assertStringContainsString('href="'.route('admin.client-projects.show', $pending).'"', $pendingRow);

        $acceptedRow = $this->rowHtml($html, 'Accepted Contract Bath');
        $this->assertStringContainsString('CA$2,500.00', $acceptedRow);
        $this->assertStringNotContainsString('Pending approval', $acceptedRow);
        $this->assertStringNotContainsString('CA$9,999.00', $acceptedRow);

        $emptyRow = $this->rowHtml($html, 'No Quote Yet Bath');
        $this->assertStringContainsString('CA$0.00', $emptyRow);
        $this->assertStringNotContainsString('Pending approval', $emptyRow);
        $this->assertNotSame($empty->id, $pending->id);
    }

    private function quotation(ClientProject $project, float $amount, string $status, $sentAt): ClientProjectQuotation
    {
        return ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => $project->name,
            'status' => $status,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-LIST-'.$project->id.'-'.substr(md5($status.$amount), 0, 6),
            'items' => [[
                'description' => 'Basement renovation',
                'amount' => $amount,
                'apply_hst' => false,
            ]],
            'payment_installments' => 1,
            'payment_percentages' => [100],
            'sent_at' => $status === ClientProjectQuotation::STATUS_DRAFT ? null : $sentAt,
            'accepted_at' => $status === ClientProjectQuotation::STATUS_ACCEPTED ? $sentAt : null,
        ]);
    }

    private function rowHtml(string $html, string $name): string
    {
        $quoted = preg_quote($name, '/');
        $this->assertSame(1, preg_match('/<tr class="cp-row-link"[\s\S]*?'.$quoted.'[\s\S]*?<\/tr>/', $html, $match), $name.' row missing');

        return $match[0];
    }
}
