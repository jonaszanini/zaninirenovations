<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectDirectoryHydrationTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_store_hydrates_blank_client_name_from_selected_directory_client(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Amanda Wrobel',
            'email' => 'wsgamanda@gmail.com',
            'phone' => '6135550199',
        ]);

        $response = $this->post(route('admin.client-projects.store'), [
            'name' => 'Replace 2 outside vents',
            'contacted_via' => ClientProject::CONTACTED_VIA_WHATSAPP,
            'client_id' => $client->id,
            // Simulate Tom Select autofill failing: directory client selected,
            // but contact fields left empty.
            'client_name' => '',
            'client_email' => '',
            'client_phone' => '',
            'billing_type' => ClientProject::BILLING_FIXED,
            'send_client_email' => '0',
            'sync_client_directory' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors(['client_name']);
        $response->assertRedirect();

        $project = ClientProject::query()->latest('id')->first();
        $this->assertNotNull($project);
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame('Amanda Wrobel', $project->client_name);
        $this->assertSame('wsgamanda@gmail.com', $project->client_email);
        $this->assertSame('6135550199', $project->client_phone);
    }
}
