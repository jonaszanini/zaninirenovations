<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectIndexContactActionsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_index_renders_mobile_contact_links_and_keeps_desktop_icons(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen contact actions',
            'client_name' => 'Pat Client',
            'client_email' => 'pat@example.com',
            'client_phone' => '6135550100',
            'client_address' => '10 Main Street, Ottawa, ON',
            'status' => ClientProject::STATUS_SCHEDULED,
        ]);

        $response = $this->get(route('admin.client-projects.index'));

        $response->assertOk();
        $html = $response->getContent();

        $response->assertSee('tel:+16135550100', false);
        $response->assertSee('(613) 555-0100', false);
        $response->assertSee(
            'https://mail.google.com/mail/?authuser=zaninirenovations@gmail.com&amp;view=cm&amp;fs=1&amp;to=pat%40example.com',
            false
        );
        $response->assertSee('target="_blank"', false);
        $response->assertSee(
            'https://www.google.com/maps/search/?api=1&amp;query='.rawurlencode('10 Main Street, Ottawa, ON'),
            false
        );
        $response->assertSee('data-cp-hold-copy="(613) 555-0100"', false);
        $response->assertSee('data-cp-hold-copy="pat@example.com"', false);
        $response->assertSee('data-cp-hold-copy="10 Main Street, Ottawa, ON"', false);
        $response->assertSee('cp-client-mobile-fields', false);
        $response->assertSee('admin-contact-hold.js', false);

        $this->assertStringContainsString('cp-copy-btn', $html);
        $this->assertStringContainsString('cp-client-action-btn--phone', $html);
        $this->assertStringContainsString('cp-client-action-btn--email', $html);
        $this->assertStringContainsString('cp-client-action-btn--address', $html);
        $this->assertStringContainsString('Kitchen contact actions', $html);
    }
}
