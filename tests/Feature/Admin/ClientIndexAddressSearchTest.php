<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientIndexAddressSearchTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_index_shows_urlencoded_google_maps_link_for_client_address(): void
    {
        $this->actingAsAdmin();

        $address = '1025 Grenon Ave, Ottawa, ON K2B 8S5';
        $client = Client::create([
            'name' => 'Lucca Maps',
            'email' => 'lucca.maps@example.com',
            'phone' => '6135550101',
            'address' => $address,
        ]);

        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);

        $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Lucca Maps')
            ->assertSee($address)
            ->assertSee($mapsUrl)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false);
    }

    public function test_index_falls_back_to_latest_project_address(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'No Directory Address',
            'email' => 'fallback@example.com',
        ]);

        ClientProject::factory()->create([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_address' => '10 Older Street, Ottawa, ON',
        ]);

        $latestAddress = '99 Newest Ave, Ottawa, ON K1A 0B1';
        ClientProject::factory()->create([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_address' => $latestAddress,
        ]);

        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($latestAddress);

        $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('No Directory Address')
            ->assertSee($latestAddress)
            ->assertDontSee('10 Older Street, Ottawa, ON')
            ->assertSee($mapsUrl);
    }

    public function test_index_omits_address_row_when_client_and_projects_have_none(): void
    {
        $this->actingAsAdmin();

        Client::create([
            'name' => 'No Address Client',
            'email' => 'none@example.com',
        ]);

        $html = $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('No Address Client')
            ->getContent();

        $this->assertStringNotContainsString('google.com/maps/search', $html);
    }

    public function test_index_search_filters_by_name_email_phone_and_address(): void
    {
        $this->actingAsAdmin();

        $named = Client::create([
            'name' => 'Lucca Rivera',
            'email' => 'other.named@example.com',
            'phone' => '6135550202',
        ]);
        $emailed = Client::create([
            'name' => 'Email Match',
            'email' => 'lucca.email@example.com',
            'phone' => '6135550303',
        ]);
        $phoned = Client::create([
            'name' => 'Phone Match',
            'email' => 'phone.match@example.com',
            'phone' => '2893819029',
        ]);
        $addressed = Client::create([
            'name' => 'Address Match',
            'email' => 'address.match@example.com',
            'address' => '12 Lucca Lane, Ottawa, ON',
        ]);
        $unrelated = Client::create([
            'name' => 'Allison Linde',
            'email' => 'allybuist@example.com',
            'phone' => '2893811111',
            'address' => '1 Unrelated Rd, Ottawa, ON',
        ]);

        $this->get(route('admin.clients.index', ['q' => 'Lucc']))
            ->assertOk()
            ->assertSee($named->name)
            ->assertSee($emailed->name)
            ->assertSee($addressed->name)
            ->assertDontSee($unrelated->name)
            ->assertDontSee($phoned->name);

        $this->get(route('admin.clients.index', ['q' => '2893819029']))
            ->assertOk()
            ->assertSee($phoned->name)
            ->assertDontSee($named->name)
            ->assertDontSee($unrelated->name);

        $this->get(route('admin.clients.index', ['q' => '(289) 381-9029']))
            ->assertOk()
            ->assertSee($phoned->name)
            ->assertDontSee($unrelated->name);
    }

    public function test_index_search_matches_linked_project_address(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Project Address Client',
            'email' => 'project.addr@example.com',
        ]);
        ClientProject::factory()->create([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_address' => '44 Hidden Lucca Way, Ottawa, ON',
        ]);

        $other = Client::create([
            'name' => 'Amanda Wrobel',
            'email' => 'amanda@example.com',
        ]);

        $this->get(route('admin.clients.index', ['q' => 'Lucc']))
            ->assertOk()
            ->assertSee($client->name)
            ->assertSee('44 Hidden Lucca Way, Ottawa, ON')
            ->assertDontSee($other->name);
    }
}
