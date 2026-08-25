<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectDuplicateCheckTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_store_blocks_similar_project_for_the_same_client(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '6135550100',
        ]);

        ClientProject::create([
            'name' => 'Front deck',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
            'client_email' => 'jane@example.com',
            'client_phone' => '6135550100',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->from(route('admin.client-projects.create'))
            ->post(route('admin.client-projects.store'), $this->validPayload([
                'name' => 'Front Deck Repair',
                'client_id' => $client->id,
                'client_name' => 'Jane Doe',
                'client_email' => 'jane@example.com',
                'client_phone' => '6135550100',
            ]));

        $response->assertRedirect(route('admin.client-projects.create'));
        $response->assertSessionHas('duplicate_project_candidates');
        $response->assertSessionHas('warning');
        $this->assertSame(1, ClientProject::query()->count());
    }

    public function test_store_creates_after_duplicate_is_confirmed(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        ClientProject::create([
            'name' => 'Front deck',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
            'client_email' => 'jane@example.com',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->post(route('admin.client-projects.store'), $this->validPayload([
            'name' => 'Front Deck Repair',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
            'client_email' => 'jane@example.com',
            'confirm_duplicate_project' => '1',
        ]));

        $response->assertRedirect();
        $this->assertSame(2, ClientProject::query()->count());
        $this->assertTrue(ClientProject::query()->where('name', 'Front Deck Repair')->exists());
    }

    public function test_store_allows_a_different_job_for_the_same_client(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        ClientProject::create([
            'name' => 'Front deck',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->post(route('admin.client-projects.store'), $this->validPayload([
            'name' => 'Kitchen remodel',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
        ]));

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_project_candidates');
        $this->assertSame(2, ClientProject::query()->count());
    }

    public function test_same_name_for_a_different_client_is_not_a_duplicate(): void
    {
        $this->actingAsAdmin();

        ClientProject::create([
            'name' => 'Fence',
            'client_name' => 'Alice Smith',
            'client_email' => 'alice@example.com',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->post(route('admin.client-projects.store'), $this->validPayload([
            'name' => 'Fence',
            'client_name' => 'Bob Jones',
            'client_email' => 'bob@example.com',
        ]));

        $response->assertRedirect();
        $response->assertSessionMissing('duplicate_project_candidates');
        $this->assertSame(2, ClientProject::query()->count());
    }

    public function test_json_check_returns_matching_projects(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $existing = ClientProject::create([
            'name' => 'Front deck',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
            'client_email' => 'jane@example.com',
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->getJson(route('admin.client-projects.duplicate-check', [
            'name' => 'Front Deck Repair',
            'client_id' => $client->id,
            'client_name' => 'Jane Doe',
        ]));

        $response->assertOk();
        $response->assertJsonPath('candidates.0.id', $existing->id);
        $response->assertJsonPath('candidates.0.reference', $existing->referenceCode());
    }

    /** @param  array<string, mixed>  $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New job',
            'contacted_via' => ClientProject::CONTACTED_VIA_EMAIL,
            'client_name' => 'Pat Client',
            'billing_type' => ClientProject::BILLING_FIXED,
            'send_client_email' => '0',
            'sync_client_directory' => '0',
        ], $overrides);
    }
}
