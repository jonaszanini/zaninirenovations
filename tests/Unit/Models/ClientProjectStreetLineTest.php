<?php

namespace Tests\Unit\Models;

use App\Models\ClientProject;
use PHPUnit\Framework\TestCase;

class ClientProjectStreetLineTest extends TestCase
{
    public function test_keeps_street_number_and_name(): void
    {
        $this->assertSame(
            '1025 Grenon Ave',
            ClientProject::streetLineFromAddress('1025 Grenon Ave, Ottawa, ON K2B 8S5'),
        );
    }

    public function test_keeps_accented_street_names(): void
    {
        $this->assertSame(
            '3 Rue des Orchidées',
            ClientProject::streetLineFromAddress('3 Rue des Orchidées, Gatineau, QC J9A 2L9'),
        );
    }

    public function test_skips_a_leading_unit_and_uses_the_numbered_street(): void
    {
        $this->assertSame(
            '100 Conservatory Dr',
            ClientProject::streetLineFromAddress('Unit 4, 100 Conservatory Dr, Ottawa, ON'),
        );
    }

    public function test_hides_city_only_addresses(): void
    {
        $this->assertSame('', ClientProject::streetLineFromAddress('Orléans, Ottawa, ON'));
        $this->assertSame('', ClientProject::streetLineFromAddress(null));
        $this->assertSame('', ClientProject::streetLineFromAddress('   '));
    }

    public function test_maps_url_uses_the_full_address_and_place_id(): void
    {
        $this->assertNull(ClientProject::googleMapsSearchUrlFor(null));
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query='.rawurlencode('1025 Grenon Ave, Ottawa, ON K2B 8S5'),
            ClientProject::googleMapsSearchUrlFor('1025 Grenon Ave, Ottawa, ON K2B 8S5'),
        );
        $this->assertStringContainsString(
            'query_place_id=ChIJ_abc',
            ClientProject::googleMapsSearchUrlFor('1025 Grenon Ave, Ottawa, ON', 'ChIJ_abc'),
        );
    }

    public function test_tel_href_normalises_digits_and_adds_plus_one(): void
    {
        $this->assertNull(ClientProject::telHref(null));
        $this->assertNull(ClientProject::telHref(''));
        $this->assertSame('tel:+16475550100', ClientProject::telHref('6475550100'));
        $this->assertSame('tel:+16475550100', ClientProject::telHref('(647) 555-0100'));
        $this->assertSame('tel:+16475550100', ClientProject::telHref('16475550100'));
        $this->assertSame('tel:5550199', ClientProject::telHref('555-0199'));
    }
}
