<?php

namespace Tests\Feature\Admin;

use App\Models\TileProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class TileDatabaseConfigTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_new_tile_product_is_persisted(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'new_products' => [
                    0 => [
                        'name' => 'Test Tile Save',
                        'url' => '',
                        'length_inches' => '12',
                        'width_inches' => '24',
                        'thickness_inches' => '0.375',
                        'cost_per_sqft' => '3.2500',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tile_products', ['name' => 'Test Tile Save']);
    }

    public function test_new_tile_with_multipart_empty_image_persists(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'new_products' => [
                    0 => [
                        'name' => 'Multipart Tile',
                        'url' => null,
                        'length_inches' => '12',
                        'width_inches' => '12',
                        'thickness_inches' => '0.25',
                        'cost_per_sqft' => '2.5',
                        'image' => UploadedFile::fake()->create('empty.jpg', 0),
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tile_products', ['name' => 'Multipart Tile']);
    }

    public function test_new_tile_accepts_url_without_scheme(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'new_products' => [
                    0 => [
                        'name' => 'Scheme Fixed Tile',
                        'url' => 'www.homedepot.ca/product/example',
                        'length_inches' => '12',
                        'width_inches' => '12',
                        'thickness_inches' => '0.25',
                        'cost_per_sqft' => '1.25',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tile_products', [
            'name' => 'Scheme Fixed Tile',
            'url' => 'https://www.homedepot.ca/product/example',
        ]);
    }

    public function test_new_tile_rejects_invalid_url(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'new_products' => [
                    0 => [
                        'name' => 'Bad Url Tile',
                        'url' => 'not a url at all !!!',
                        'length_inches' => '12',
                        'width_inches' => '12',
                        'thickness_inches' => '0.25',
                        'cost_per_sqft' => '1',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('tile_products', ['name' => 'Bad Url Tile']);
    }

    public function test_updating_existing_and_adding_new_together(): void
    {
        $this->actingAsAdmin();

        $existing = TileProduct::query()->create([
            'name' => 'Existing Tile',
            'url' => null,
            'length_inches' => 12,
            'width_inches' => 12,
            'thickness_inches' => 0.25,
            'cost_per_sqft' => 1.5,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'products' => [
                    0 => [
                        'id' => $existing->id,
                        'name' => 'Existing Tile Updated',
                        'url' => '',
                        'length_inches' => '12',
                        'width_inches' => '12',
                        'thickness_inches' => '0.25',
                        'cost_per_sqft' => '1.5000',
                        'sort_order' => 1,
                        'is_active' => '1',
                    ],
                ],
                'new_products' => [
                    0 => [
                        'name' => 'Brand New Tile',
                        'url' => '',
                        'length_inches' => '6',
                        'width_inches' => '24',
                        'thickness_inches' => '0.3',
                        'cost_per_sqft' => '4.99',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tile_products', ['name' => 'Existing Tile Updated']);
        $this->assertDatabaseHas('tile_products', ['name' => 'Brand New Tile']);
    }

    public function test_new_tile_with_comma_money_persists(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.tile.price-configuration'))
            ->put(route('admin.quotations.tile.price-configuration.update'), [
                'new_products' => [
                    0 => [
                        'name' => 'Money Mask Tile',
                        'length_inches' => '12',
                        'width_inches' => '12',
                        'thickness_inches' => '0.25',
                        'cost_per_sqft' => '1,234.5678',
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $product = TileProduct::query()->where('name', 'Money Mask Tile')->first();
        $this->assertNotNull($product);
        $this->assertEqualsWithDelta(1234.5678, (float) $product->cost_per_sqft, 0.0001);
    }
}
