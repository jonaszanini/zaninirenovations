<?php

namespace Tests\Unit\Support;

use App\Models\Material;
use App\Support\CatalogMaterialDelivery;
use App\Support\MaterialCatalogPickerOptions;
use Tests\TestCase;

class CatalogMaterialDeliveryTest extends TestCase
{
    public function test_cad_rounds_and_floors_at_zero(): void
    {
        $this->assertSame(0.0, CatalogMaterialDelivery::cad(null));
        $this->assertSame(80.0, CatalogMaterialDelivery::cad(new Material(['delivery_cad' => 80])));
        $this->assertSame(0.0, CatalogMaterialDelivery::cad(new Material(['delivery_cad' => -12])));
    }

    public function test_picker_exposes_delivery_on_the_option(): void
    {
        $options = MaterialCatalogPickerOptions::fromMaterials([
            new Material([
                'id' => 9,
                'name' => 'Debris bin / dumpster',
                'short_name' => 'Debris bin',
                'price_cad' => 400,
                'delivery_cad' => 80,
            ]),
        ]);

        $this->assertCount(1, $options);
        $this->assertSame('80.00', $options[0]['delivery']);
        $this->assertSame('Delivery CA$80.00', $options[0]['delivery_label']);
        $this->assertStringContainsString('delivery', strtolower($options[0]['label']));
    }

    public function test_picker_exposes_preview_image(): void
    {
        $withUrl = MaterialCatalogPickerOptions::fromMaterials([
            new Material([
                'id' => 3,
                'name' => 'Drywall sheet',
                'price_cad' => 18,
                'image_url' => 'https://example.com/drywall.jpg',
            ]),
        ]);
        $this->assertSame('https://example.com/drywall.jpg', $withUrl[0]['image']);

        $fromSku = MaterialCatalogPickerOptions::fromMaterials([
            new Material([
                'id' => 4,
                'name' => '2x4 stud',
                'price_cad' => 5.5,
                'store_sku' => '1000123456',
            ]),
        ]);
        $this->assertStringContainsString('images.homedepot.ca', $fromSku[0]['image']);
        $this->assertStringContainsString('1000123456', $fromSku[0]['image']);
    }

    public function test_append_named_rows_adds_flat_delivery_line(): void
    {
        $rows = CatalogMaterialDelivery::appendNamedRows([
            [
                'key' => 'dump_bin',
                'name' => 'Debris bin',
                'quantity' => 2,
                'unit' => 'ea',
                'unit_price' => 400,
                'subtotal' => 800,
                'notes' => '2 bins',
            ],
        ], static fn (array $row): array => [75.0, (string) $row['name']]);

        $this->assertCount(2, $rows);
        $this->assertSame('dump_bin_delivery', $rows[1]['key']);
        $this->assertSame('Debris bin delivery', $rows[1]['name']);
        $this->assertSame(1, $rows[1]['quantity']);
        $this->assertSame('flat', $rows[1]['unit']);
        $this->assertEqualsWithDelta(75.0, $rows[1]['subtotal'], 0.01);
    }

    public function test_append_once_per_vendor_keeps_one_delivery_line(): void
    {
        $rows = CatalogMaterialDelivery::appendOncePerVendor([
            ['key' => 'post_10', 'name' => 'Post', 'quantity' => 4, 'unit' => 'ea', 'unit_price' => 40, 'subtotal' => 160],
            ['key' => 'rail', 'name' => 'Rail', 'quantity' => 8, 'unit' => 'ea', 'unit_price' => 7, 'subtotal' => 56],
        ], static function (array $row): array {
            return [40.0, 'Home Depot', 'vendor:1'];
        });

        $deliveries = array_values(array_filter($rows, fn (array $row): bool => str_ends_with((string) $row['key'], '_delivery')));
        $this->assertCount(1, $deliveries);
        $this->assertEqualsWithDelta(40.0, $deliveries[0]['subtotal'], 0.01);
        $this->assertStringContainsString('Home Depot', $deliveries[0]['name']);
    }
}
