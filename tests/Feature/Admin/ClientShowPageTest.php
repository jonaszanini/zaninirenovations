<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientTag;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientShowPageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_is_redirected_from_client_show(): void
    {
        $client = Client::create([
            'name' => 'Guest Blocked Client',
            'email' => 'guest.blocked@example.test',
        ]);

        $this->get(route('admin.clients.show', $client))
            ->assertRedirect(route('login'));
    }

    public function test_show_returns_404_for_missing_client(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.clients.show', 999999))
            ->assertNotFound();
    }

    public function test_show_renders_fields_mailto_tel_and_maps_links(): void
    {
        $this->actingAsAdmin();

        $address = '1025 Grenon Ave, Ottawa, ON K2B 8S5';
        $tag = ClientTag::query()->create([
            'name' => 'VIP',
            'color' => '#024768',
        ]);
        $client = Client::create([
            'name' => 'Lucca View',
            'email' => 'lucca.view@example.test',
            'email_secondary' => 'lucca.cc@example.test',
            'phone' => '6475550100',
            'address' => $address,
            'notes' => 'Prefers morning visits.',
        ]);
        $client->tags()->sync([$tag->id]);

        $project = ClientProject::factory()->create([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'name' => 'Kitchen refresh',
        ]);

        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);

        $this->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Lucca View')
            ->assertSee('lucca.view@example.test')
            ->assertSee('lucca.cc@example.test')
            ->assertSee('(647) 555-0100')
            ->assertSee($address)
            ->assertSee('Prefers morning visits.')
            ->assertSee('VIP')
            ->assertSee('Kitchen refresh')
            ->assertSee('mailto:lucca.view@example.test', false)
            ->assertSee('mailto:lucca.cc@example.test', false)
            ->assertSee('tel:+16475550100', false)
            ->assertSee($mapsUrl)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false)
            ->assertSee('aria-label="Copy email"', false)
            ->assertSee('aria-label="Copy secondary email"', false)
            ->assertSee('aria-label="Copy phone"', false)
            ->assertSee('aria-label="Copy address"', false)
            ->assertSee(route('admin.clients.edit', $client), false)
            ->assertSee(route('admin.client-projects.show', $project), false)
            ->assertSee('> Edit', false);
    }

    public function test_index_name_opens_view_and_edit_stays_on_edit(): void
    {
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Directory Link Client',
            'email' => 'dir.link@example.test',
            'phone' => '6135550199',
            'address' => '10 Sample St, Ottawa, ON',
        ]);

        $html = $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Directory Link Client')
            ->assertSee('mailto:dir.link@example.test', false)
            ->assertSee('tel:+16135550199', false)
            ->assertSee('aria-label="Copy email"', false)
            ->assertSee('aria-label="Copy phone"', false)
            ->assertSee('aria-label="Copy address"', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.clients.show', $client), $html);
        $this->assertStringContainsString(route('admin.clients.edit', $client), $html);
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('admin.clients.show', $client), '#').'"[^>]*>Directory Link Client#',
            $html
        );
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('admin.clients.edit', $client), '#').'"[^>]*>[\s\S]*Edit#',
            $html
        );
    }

    public function test_search_api_client_url_is_the_show_route(): void
    {
        $this->actingAsAdmin();

        $client = Client::query()->create([
            'name' => 'Palette View Client',
            'email' => 'palette.view@example.test',
            'phone' => '4165550101',
        ]);

        $this->get(route('admin.search.api', ['q' => 'Palette View']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'clients',
                'title' => 'Palette View Client',
                'url' => route('admin.clients.show', $client),
            ]);
    }
}
