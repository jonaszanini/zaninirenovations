<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientIndexAddressColumnLayoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_index_view_gives_address_a_real_column_share(): void
    {
        $this->actingAsAdmin();

        $address = '1025 Grenon Avenue, Britannia neighbourhood, Ottawa, Ontario K2B 8S5, Canada';
        Client::create([
            'name' => 'Lucca Long Address',
            'email' => 'lucca.long@example.test',
            'phone' => '6475550100',
            'address' => $address,
        ]);

        $html = $this->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Lucca Long Address')
            ->assertSee($address)
            ->assertSee('cp-clients-col-address', false)
            ->assertSee('cl-copy-field--address', false)
            ->assertSee('cp-clients-table', false)
            ->getContent();

        $this->assertStringContainsString('class="cp-clients-col-address"', $html);
        $this->assertStringContainsString('cl-copy-field--address', $html);
        $this->assertStringContainsString('aria-label="Copy address"', $html);

        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table \{\s*width:\s*100%;\s*min-width:\s*0;\s*table-layout:\s*fixed;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table \.cp-clients-col-address \{\s*width:\s*28%;/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.cp-clients-table \.cp-clients-col-address \{\s*width:\s*auto;/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.cp-clients-table \.cp-clients-col-email \{[^}]*width:\s*\d[\d.]*rem/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table \.cl-copy-field--address \{[^}]*width:\s*100%;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table \.cl-copy-field--address \.cl-copy-field__value \{[^}]*word-break:\s*normal;/',
            $css
        );

        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table\.admin-table--stack tr \{\s*display:\s*grid;\s*grid-template-columns:\s*minmax\(0,\s*1fr\) auto;/s',
            $css
        );
    }
}
