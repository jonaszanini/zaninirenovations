<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class PendingCustomerStatusTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_pending_customer_saves_and_filters(): void
    {
        $this->actingAsAdmin();

        $waiting = ClientProject::factory()->create([
            'name' => 'Kitchen waiting on client',
            'client_name' => 'Ada Client',
            'client_email' => 'ada@example.com',
            'client_phone' => '4165550100',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_FIXED,
            'contacted_via' => ClientProject::CONTACTED_VIA_EMAIL,
        ]);
        $other = ClientProject::factory()->create([
            'name' => 'Bathroom still quoting',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $this->put(route('admin.client-projects.update', $waiting), [
            'name' => $waiting->name,
            'client_name' => $waiting->client_name,
            'client_email' => $waiting->client_email,
            'client_phone' => '4165550100',
            'billing_type' => ClientProject::BILLING_FIXED,
            'contacted_via' => ClientProject::CONTACTED_VIA_EMAIL,
            'status' => ClientProject::STATUS_PENDING_CUSTOMER,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $waiting->refresh();
        $this->assertSame(ClientProject::STATUS_PENDING_CUSTOMER, $waiting->status);
        $this->assertSame('Pending Customer', $waiting->statusLabel());
        $this->assertSame('#64748b', $waiting->statusColor());

        $this->get(route('admin.client-projects.edit', $waiting))
            ->assertOk()
            ->assertSee('Pending Customer')
            ->assertSee('value="'.ClientProject::STATUS_PENDING_CUSTOMER.'"', false);

        $this->get(route('admin.client-projects.show', $waiting))
            ->assertOk()
            ->assertSee('Pending Customer')
            ->assertSee('#64748b');

        $filtered = $this->get(route('admin.client-projects.index', [
            'status' => ClientProject::STATUS_PENDING_CUSTOMER,
            'list' => 'all',
        ]));

        $filtered->assertOk();
        $filtered->assertSee('Kitchen waiting on client');
        $filtered->assertDontSee('Bathroom still quoting');

        $this->assertSame(ClientProject::STATUS_QUOTE_PENDING, $other->fresh()->status);

        $keys = ClientProject::statuses();
        $this->assertGreaterThan(
            array_search(ClientProject::STATUS_IN_PROGRESS, $keys, true),
            array_search(ClientProject::STATUS_PENDING_CUSTOMER, $keys, true)
        );
        $this->assertLessThan(
            array_search(ClientProject::STATUS_AWAITING_FINAL_PAYMENT, $keys, true),
            array_search(ClientProject::STATUS_PENDING_CUSTOMER, $keys, true)
        );
        $labels = array_keys(ClientProject::statusLabels());
        $this->assertGreaterThan(
            array_search(ClientProject::STATUS_IN_PROGRESS, $labels, true),
            array_search(ClientProject::STATUS_PENDING_CUSTOMER, $labels, true)
        );

        $this->get(route('admin.client-projects.show', $waiting))
            ->assertSee('Scheduled → In Progress → Pending Customer → Awaiting Final Payment → Completed', false);
    }
}
