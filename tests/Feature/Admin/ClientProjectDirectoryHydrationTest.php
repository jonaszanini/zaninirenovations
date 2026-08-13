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
            // Directory client selected but contact fields left empty.
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

    public function test_store_hydrates_when_client_id_posted_as_duplicate_fields(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Patricia Oliveira Garcia',
            'email' => 'patitita@gmail.com',
        ]);

        $response = $this->post(route('admin.client-projects.store'), [
            'name' => 'Second coat',
            'contacted_via' => ClientProject::CONTACTED_VIA_WHATSAPP,
            // Duplicate client_id fields (empty plus the chosen id).
            'client_id' => ['', (string) $client->id],
            'client_name' => '',
            'billing_type' => ClientProject::BILLING_FIXED,
            'send_client_email' => '0',
            'sync_client_directory' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors(['client_name', 'client_id']);
        $response->assertRedirect();

        $project = ClientProject::query()->latest('id')->first();
        $this->assertNotNull($project);
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame('Patricia Oliveira Garcia', $project->client_name);
    }

    public function test_store_copies_directory_coordinates_when_address_text_is_already_posted(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Amanda Wrobel',
            'email' => 'wsgamanda@gmail.com',
            'address' => '209 Bert Hall St, Arnprior, ON K7S 3G3, Canada',
            'address_place_id' => 'ChIJtestcoords',
            'address_latitude' => '45.4332100',
            'address_longitude' => '-76.3565400',
        ]);

        $response = $this->post(route('admin.client-projects.store'), [
            'name' => 'Vent replace',
            'contacted_via' => ClientProject::CONTACTED_VIA_WHATSAPP,
            'client_id' => $client->id,
            'client_name' => 'Amanda Wrobel',
            'client_address' => '209 Bert Hall St, Arnprior, ON K7S 3G3, Canada',
            'client_address_place_id' => '',
            'client_address_latitude' => '',
            'client_address_longitude' => '',
            'billing_type' => ClientProject::BILLING_FIXED,
            'send_client_email' => '0',
            'sync_client_directory' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        $project = ClientProject::query()->latest('id')->first();
        $this->assertNotNull($project);
        $this->assertTrue($project->hasServiceCoordinates());
        $this->assertEqualsWithDelta(45.43321, (float) $project->client_address_latitude, 0.0001);
        $this->assertEqualsWithDelta(-76.35654, (float) $project->client_address_longitude, 0.0001);

        $client->refresh();
        $this->assertEqualsWithDelta(45.43321, (float) $client->address_latitude, 0.0001);
        $this->assertEqualsWithDelta(-76.35654, (float) $client->address_longitude, 0.0001);
    }

    public function test_store_still_requires_a_name_when_no_directory_client_is_chosen(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.client-projects.create'))
            ->post(route('admin.client-projects.store'), [
                'name' => 'Second coat',
                'contacted_via' => ClientProject::CONTACTED_VIA_WHATSAPP,
                'client_id' => '',
                'client_name' => '',
                'billing_type' => ClientProject::BILLING_FIXED,
                'send_client_email' => '0',
            ]);

        $response->assertSessionHasErrors(['client_name']);
        $this->assertSame(0, ClientProject::query()->count());
    }
}
