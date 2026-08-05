<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectHelperPayment;
use App\Models\ClientProjectHelperTimeEntry;
use App\Models\Helper;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectHelperPaymentAcknowledgeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_bulk_acknowledge_accepts_duplicate_payment_ids_from_a_shared_batch(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $helper = Helper::create([
            'name' => 'Test Helper',
            'email' => 'helper@example.com',
            'hourly_rate' => 25,
            'is_active' => true,
        ]);

        $payment = ClientProjectHelperPayment::create([
            'client_project_id' => $project->id,
            'helper_id' => $helper->id,
            'payment_method' => ClientProjectHelperPayment::METHOD_CASH,
            'total_amount' => 200,
            'total_hours' => 8,
            'hourly_rate' => 25,
            'paid_at' => now(),
        ]);

        // A single payment batch commonly covers multiple time entries — the
        // "mark selected as acknowledged" UI renders one checkbox per entry,
        // all sharing this same payment id.
        ClientProjectHelperTimeEntry::create([
            'client_project_id' => $project->id,
            'helper_id' => $helper->id,
            'helper_payment_id' => $payment->id,
            'entry_type' => ClientProjectHelperTimeEntry::TYPE_TIME,
            'occurred_on' => now()->subDay()->toDateString(),
            'hours' => 4,
            'hourly_rate' => 25,
        ]);

        ClientProjectHelperTimeEntry::create([
            'client_project_id' => $project->id,
            'helper_id' => $helper->id,
            'helper_payment_id' => $payment->id,
            'entry_type' => ClientProjectHelperTimeEntry::TYPE_TIME,
            'occurred_on' => now()->toDateString(),
            'hours' => 4,
            'hourly_rate' => 25,
        ]);

        $response = $this->post(
            route('admin.client-projects.helper-payments.acknowledge-bulk', $project),
            ['payment_ids' => [$payment->id, $payment->id]]
        );

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
        $this->assertNotNull($payment->fresh()->acknowledged_at);
    }
}
